<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BlogFilterHelpers;
use App\Http\Requests\BlogPostRequest;
use App\Http\Requests\CommunityBlogPostRequest;
use App\Models\BlogAttachment;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Usuario as User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class BlogController extends Controller
{
    use BlogFilterHelpers;

    public function home(Request $request): View|JsonResponse
    {
        $rawFilters = [
            'q' => trim((string) $request->query('q', '')),
            'tag' => trim((string) $request->query('tag', '')),
        ];

        $normalizedFilters = $this->normalizeBlogFilters($rawFilters);
        $displaySearch = $rawFilters['q'];
        if ($displaySearch === '' && $rawFilters['tag'] !== '') {
            $displaySearch = '#' . ltrim($rawFilters['tag'], '#');
        }

        $activeTab = $request->query('tab', 'novedades');
        if (!in_array($activeTab, ['novedades', 'miembros'], true)) {
            $activeTab = 'novedades';
        }

        $officialQuery = BlogPost::query()
            ->published()
            ->where('is_community', false)
            ->latest('published_at')
            ->with(['author', 'tags']);

        $communityQuery = BlogPost::query()
            ->published()
            ->community()
            ->latest('published_at')
            ->with(['author', 'tags']);

        $this->applyBlogFilters($officialQuery, $normalizedFilters);
        $this->applyBlogFilters($communityQuery, $normalizedFilters);

        $officialCount = (clone $officialQuery)->count();
        $communityCount = (clone $communityQuery)->count();

        /** @var LengthAwarePaginator $posts */
        $posts = ($activeTab === 'miembros' ? $communityQuery : $officialQuery)
            ->paginate(10)
            ->withQueryString();

        // ===== Historial =====
        $historyQuery = BlogPost::query()
            ->published()
            ->latest('published_at');

        $this->applyBlogFilters($historyQuery, $normalizedFilters);

        $history = $historyQuery
            ->get(['id', 'title', 'slug', 'published_at'])
            ->groupBy(function (BlogPost $post) {
                return $post->published_at?->format('Y');
            })
            ->filter()
            ->sortKeysDesc()
            ->map(function ($postsByYear, $year) {
                $months = $postsByYear
                    ->groupBy(function (BlogPost $post) {
                        return $post->published_at?->format('n');
                    })
                    ->filter()
                    ->sortKeysDesc()
                    ->map(function ($postsByMonth) {
                        /** @var BlogPost $first */
                        $first = $postsByMonth->first();

                        return [
                            'label' => $first->published_at?->translatedFormat('F') ?? '',
                            'month' => (int) ($first->published_at?->format('n') ?? 0),
                            'posts' => $postsByMonth->map(function (BlogPost $post) {
                                return [
                                    'title' => $post->title,
                                    'slug' => $post->slug,
                                    'published_at' => $post->published_at,
                                ];
                            })->all(),
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'year' => (int) $year,
                    'months' => $months,
                ];
            })
            ->values()
            ->all();

        $hasActiveFilters = $this->blogFiltersAreActive($normalizedFilters);

        // ===== Tendencia (1) y Top contributor =====
        $topTag = $this->topTag();
        // Para compatibilidad con la vista que usa $suggestedTags, pasamos solo 1
        $suggestedTags = $topTag
            ? collect([['id' => (int) $topTag->id, 'name' => $topTag->name, 'slug' => $topTag->slug]])
            : collect();

        $topContributor = $this->topContributor(); // ['author' => User, 'count' => int] | null

        $canSubmitCommunity = $request->user()?->can('createCommunity', BlogPost::class) ?? false;

        $tabQueryDefaults = array_filter([
            'q' => $rawFilters['q'],
            'tag' => $rawFilters['tag'],
        ], fn($value) => $value !== null && $value !== '');

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $posts->getCollection()->map(function (BlogPost $post) {
                    return [
                        'id' => $post->id,
                        'slug' => $post->slug,
                        'title' => $post->title,
                        'excerpt' => $post->excerpt_computed,
                        'published_at' => $post->published_at?->toIso8601String(),
                        'author' => $post->author === null ? null : [
                            'id' => $post->author->id,
                            'name' => $post->author->name,
                        ],
                        'tags' => $post->tags->map(function ($tag) {
                            return [
                                'id' => $tag->id,
                                'name' => $tag->name,
                                'slug' => $tag->slug,
                            ];
                        })->all(),
                    ];
                })->all(),
                'meta' => [
                    'current_page' => $posts->currentPage(),
                    'last_page' => $posts->lastPage(),
                    'per_page' => $posts->perPage(),
                    'total' => $posts->total(),
                ],
                'links' => [
                    'first' => $posts->url(1),
                    'last' => $posts->url($posts->lastPage()),
                    'prev' => $posts->previousPageUrl(),
                    'next' => $posts->nextPageUrl(),
                ],
                'filters' => [
                    'input' => ['q' => $displaySearch],
                    'applied' => ['search' => $normalizedFilters['search']],
                    'active' => $this->blogFiltersAreActive($normalizedFilters),
                ],
                'tab' => [
                    'active' => $activeTab,
                    'counts' => [
                        'novedades' => $officialCount,
                        'miembros' => $communityCount,
                    ],
                ],
                // JSON también expone estos dos
                'top_tag' => $topTag ? [
                    'id' => (int) $topTag->id,
                    'name' => $topTag->name,
                    'slug' => $topTag->slug,
                ] : null,
                'top_contributor' => $topContributor ? [
                    'author' => [
                        'id' => (int) $topContributor['author']->id,
                        'name' => (string) $topContributor['author']->name,
                        'avatar_url' => $topContributor['author']->avatar_url_computed
                            ?? $topContributor['author']->profile_photo_url
                            ?? null,
                    ],
                    'count' => (int) $topContributor['count'],
                ] : null,
                // mantenemos suggested_tags por compatibilidad (1 sola)
                'suggested_tags' => $suggestedTags->all(),
            ]);
        }

        return view('blog.index', [
            'posts' => $posts,
            'history' => $history,
            'filters' => [
                'input' => ['q' => $displaySearch],
                'applied' => ['search' => $normalizedFilters['search']],
                'active' => $hasActiveFilters,
            ],
            'suggestedTags' => $suggestedTags,  // 1 tag (top)
            'topTag' => $topTag,
            'topContributor' => $topContributor,
            'canSubmitCommunity' => $canSubmitCommunity,
            'activeTab' => $activeTab,
            'tabCounts' => [
                'novedades' => $officialCount,
                'miembros' => $communityCount,
            ],
            'tabQueryDefaults' => $tabQueryDefaults,
        ]);
    }

    public function show(Request $request, BlogPost $post): View
    {
        if ($post->published_at === null || $post->published_at->isFuture()) {
            $user = auth()->user();
            if ($user === null || (!$user->hasAnyRole(['admin', 'moderator']) && $user->id !== $post->user_id)) {
                abort(404);
            }
        }

        $post->load(['author', 'attachments', 'tags']);

        $comments = $post->comments()
            ->with(['author'])
            ->latest('created_at')
            ->get();

        $ratingRow = $post->comments()
            ->whereNotNull('rating')
            ->selectRaw('COUNT(*) as c, AVG(rating) as avg_rating')
            ->first();

        $ratingAverage = $ratingRow?->avg_rating ? round((float) $ratingRow->avg_rating, 1) : 0.0;
        $ratingCount = (int) ($ratingRow->c ?? 0);
        $ratingFull = (int) floor($ratingAverage);
        $ratingPartial = max(0, min(1, $ratingAverage - $ratingFull));

        $userComment = null;
        $user = $request->user();
        if ($user !== null) {
            $userComment = $comments->firstWhere('user_id', $user->id);
        }

        $canComment = false;
        if ($user !== null) {
            $canComment = method_exists($user, 'estaAprobado') ? (bool) $user->estaAprobado() : true;
        }

        return view('blog.show', [
            'post' => $post,
            'comments' => $comments,
            'userComment' => $userComment,
            'canComment' => $canComment,
            'ratingSummary' => [
                'average' => $ratingAverage,
                'count' => $ratingCount,
                'full' => $ratingFull,
                'partial' => $ratingPartial,
            ],
        ]);
    }

    public function community(Request $request): RedirectResponse
    {
        $query = $request->query();
        $query['tab'] = 'miembros';

        return redirect()->route('blog.index', $query);
    }

    public function communityCreate(): View
    {
        $this->authorize('createCommunity', BlogPost::class);

        $post = new BlogPost([
            'is_community' => true,
        ]);

        return $this->communityForm($post, 'create');
    }

    public function communityStore(CommunityBlogPostRequest $request): RedirectResponse
    {
        $this->authorize('createCommunity', BlogPost::class);

        $data = $request->validated();

        $post = new BlogPost();
        $post->title = (string) $data['title'];
        $post->excerpt = $data['excerpt'] ?? null;
        $post->content = $this->sanitizeHtml($data['content'] ?? '');
        $post->is_community = true;
        $post->user_id = (int) $request->user()->id;
        $post->published_at = null;
        $post->approved_at = null;
        $post->approved_by = null;
        $post->save();

        $this->syncTags($post, $request);

        return redirect()
            ->route('blog.community.mine')
            ->with('status', 'Tu aporte se envió para revisión.');
    }

    public function communityMine(Request $request): View
    {
        $this->authorize('createCommunity', BlogPost::class);

        $posts = BlogPost::query()
            ->where('user_id', $request->user()->id)
            ->where('is_community', true)
            ->latest('created_at')
            ->paginate(15);

        return view('blog.community.mine', [
            'posts' => $posts,
        ]);
    }

    public function communityEdit(BlogPost $post): View
    {
        if (!$post->is_community) {
            abort(404);
        }

        $this->authorize('update', $post);

        if ($post->approved_at !== null) {
            abort(403, 'El aporte ya fue aprobado.');
        }

        return $this->communityForm($post, 'edit');
    }

    public function communityUpdate(CommunityBlogPostRequest $request, BlogPost $post): RedirectResponse
    {
        if (!$post->is_community) {
            abort(404);
        }

        $this->authorize('update', $post);

        if ($post->approved_at !== null) {
            abort(403, 'El aporte ya fue aprobado.');
        }

        $data = $request->validated();
        $post->title = (string) $data['title'];
        $post->excerpt = $data['excerpt'] ?? null;
        $post->content = $this->sanitizeHtml($data['content'] ?? '');
        $post->save();

        $this->syncTags($post, $request);

        return redirect()
            ->route('blog.community.mine')
            ->with('status', 'Aporte actualizado.');
    }

    public function communityDestroy(BlogPost $post): RedirectResponse
    {
        if (!$post->is_community) {
            abort(404);
        }

        $this->authorize('delete', $post);

        if ($post->approved_at !== null) {
            abort(403, 'El aporte ya fue aprobado.');
        }

        $this->deletePost($post);

        return redirect()
            ->route('blog.community.mine')
            ->with('status', 'Aporte eliminado.');
    }

    private function communityForm(BlogPost $post, string $mode): View
    {
        if ($post->exists) {
            $post->load(['tags']);
        } else {
            $post->setRelation('tags', collect());
        }

        $pageTitle = $mode === 'edit' ? 'Editar aporte comunitario' : 'Nuevo aporte comunitario';

        return view('blog.manage.form', [
            'post' => $post,
            'availableTags' => $this->availableTagsForForm(),
            'popularTags' => $this->popularTagsForForm(),
            'context' => 'community',
            'pageTitle' => $pageTitle,
            'backUrl' => route('blog.community.mine'),
            'backLabel' => '← Mis aportes',
            'formAction' => $mode === 'edit'
                ? route('blog.community.update', $post)
                : route('blog.community.store'),
            'formMethod' => $mode === 'edit' ? 'put' : 'post',
            'submitLabel' => $mode === 'edit' ? 'Guardar cambios' : 'Enviar para revisión',
            'showSlugField' => false,
            'showMediaSection' => false,
            'showStyleSection' => false,
            'showViewLink' => false,
        ]);
    }

    public function manage(): View
    {
        $this->authorize('viewAny', BlogPost::class);

        $posts = BlogPost::query()
            ->with(['author', 'approver'])
            ->orderByRaw('CASE WHEN is_community = 1 AND approved_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->paginate(20);

        $pendingCount = BlogPost::query()
            ->pendingApproval()
            ->count();

        return view('blog.manage.index', [
            'posts' => $posts,
            'pendingCount' => $pendingCount,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', BlogPost::class);

        return view('blog.manage.form', [
            'post' => new BlogPost(),
            'availableTags' => $this->availableTagsForForm(),
            'popularTags' => $this->popularTagsForForm(),
        ]);
    }

    public function store(BlogPostRequest $request): RedirectResponse
    {
        $this->authorize('create', BlogPost::class);

        $data = Arr::except($request->validated(), ['tags', 'new_tags']);
        if (!empty($data['slug'])) {
            $data['slug'] = Str::slug($data['slug']);
        }

        $data = $this->prepareCustomization($data);

        $post = new BlogPost();
        $data['content'] = $this->sanitizeHtml($data['content'] ?? '');
        $post->fill($data);
        $post->user_id = (int) $request->user()->id;
        $post->published_at = $data['published_at'] ?? now();
        $post->save();

        $this->syncTags($post, $request);
        $this->syncAttachments($post, $request);

        return redirect()
            ->route('blog.manage')
            ->with('status', 'Entrada creada correctamente.');
    }

    public function edit(BlogPost $post): View
    {
        $this->authorize('update', $post);

        return view('blog.manage.form', [
            'post' => $post->load(['attachments', 'tags']),
            'availableTags' => $this->availableTagsForForm(),
            'popularTags' => $this->popularTagsForForm(),
        ]);
    }

    public function update(BlogPostRequest $request, BlogPost $post): RedirectResponse
    {
        $this->authorize('update', $post);

        $data = Arr::except($request->validated(), ['tags', 'new_tags']);
        if (!empty($data['slug'])) {
            $data['slug'] = Str::slug($data['slug']);
        }

        $data = $this->prepareCustomization($data, $post);

        $data['content'] = $this->sanitizeHtml($data['content'] ?? '');
        $post->fill($data);
        if (empty($data['published_at'])) {
            if ($post->published_at === null) {
                $post->published_at = now();
            }
        } else {
            $post->published_at = $data['published_at'];
        }
        $post->save();

        $this->syncTags($post, $request);
        $this->syncAttachments($post, $request);

        return redirect()
            ->route('blog.manage')
            ->with('status', 'Entrada actualizada.');
    }

    public function destroy(BlogPost $post): RedirectResponse
    {
        $this->authorize('delete', $post);

        $this->deletePost($post);

        return redirect()
            ->route('blog.manage')
            ->with('status', 'Entrada eliminada.');
    }

    public function destroyAttachment(BlogPost $post, BlogAttachment $attachment): RedirectResponse
    {
        $this->authorize('update', $post);

        if ($attachment->blog_post_id !== $post->id) {
            abort(404);
        }

        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();

        return redirect()
            ->route('blog.edit', $post)
            ->with('status', 'Archivo eliminado.');
    }

    private function deletePost(BlogPost $post): void
    {
        foreach ($post->attachments as $attachment) {
            Storage::disk('public')->delete($attachment->path);
            $attachment->delete();
        }

        $post->delete();
    }

    public function approve(Request $request, BlogPost $post): RedirectResponse
    {
        if (!$post->is_community) {
            abort(404);
        }

        $this->authorize('review', $post);

        if ($post->approved_at !== null) {
            return redirect()
                ->route('blog.manage')
                ->with('status', 'El aporte ya estaba aprobado.');
        }

        $post->approved_at = now();
        $post->approved_by = (int) $request->user()->id;

        if ($post->published_at === null || $post->published_at->isFuture()) {
            $post->published_at = now();
        }

        $post->save();

        Cache::forget('blog.top_tag');
        Cache::forget('blog.top_contributor_row');

        return redirect()
            ->route('blog.manage')
            ->with('status', 'Aporte aprobado y publicado.');
    }

    /**
     * @return Collection<int, array{id:int,name:string,slug:string}>
     */
    private function availableTagsForForm(): Collection
    {
        return BlogTag::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn(BlogTag $tag) => [
                'id' => (int) $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ])
            ->values();
    }

    /**
     * @return Collection<int, array{id:int,name:string,slug:string}>
     */
    private function popularTagsForForm(): Collection
    {
        return BlogTag::query()
            ->withCount('posts')
            ->orderByDesc('posts_count')
            ->orderBy('name')
            ->take(4)
            ->get(['id', 'name', 'slug'])
            ->map(fn(BlogTag $tag) => [
                'id' => (int) $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ])
            ->values();
    }

    /**
     * Tendencia principal: tag con más posts publicados.
     */
    private function topTag(): ?BlogTag
    {
        return Cache::remember('blog.top_tag', now()->addMinutes(30), function () {
            return BlogTag::query()
                ->whereHas('posts', fn($query) => $query->published())
                ->withCount([
                    'posts as published_posts_count' => fn($query) => $query->published(),
                ])
                ->orderByDesc('published_posts_count')
                ->orderBy('name')
                ->first(['id', 'name', 'slug']);
        });
    }

    /**
     * Top contributor: usuario con más posts publicados.
     * @return array{author:\App\Models\User,count:int}|null
     */
    private function topContributor(): ?array
    {
        $row = Cache::remember('blog.top_contributor_row', now()->addMinutes(30), function () {
            return BlogPost::query()
                ->whereNotNull('published_at')
                ->select('user_id', DB::raw('COUNT(*) as c'))
                ->groupBy('user_id')
                ->orderByDesc('c')
                ->first();
        });

        if (!$row || empty($row->user_id)) {
            return null;
        }

        $author = User::find($row->user_id);
        if (!$author) {
            // Fallback si la relación cambiara
            $author = BlogPost::query()
                ->with('author')
                ->where('user_id', $row->user_id)
                ->latest('published_at')
                ->first()?->author;
        }

        if (!$author) {
            return null;
        }

        return [
            'author' => $author,
            'count' => (int) $row->c,
        ];
    }

    /**
     * @return Collection<int, array{id:int,name:string,slug:string}>
     */
    private function heroSuggestedTags(): Collection
    {
        // Ya no se usa directamente en home(), pero lo dejamos para otros screens
        return BlogTag::query()
            ->whereHas('posts', fn($query) => $query->published())
            ->withCount([
                'posts as published_posts_count' => fn($query) => $query->published(),
            ])
            ->orderByDesc('published_posts_count')
            ->orderBy('name')
            ->take(4)
            ->get(['id', 'name', 'slug'])
            ->map(fn(BlogTag $tag) => [
                'id' => (int) $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ])
            ->values();
    }

    private function syncTags(BlogPost $post, Request $request): void
    {
        $existingTagIds = collect($request->input('tags', []))
            ->filter(fn($value) => is_numeric($value))
            ->map(fn($value) => (int) $value);

        $rawNewTags = preg_split('/[\n,;]+/', (string) $request->input('new_tags', ''));
        $newTags = collect($rawNewTags ?: [])
            ->map(fn($value) => Str::of((string) $value)->squish()->toString())
            ->filter(fn($value) => $value !== '')
            ->unique(fn($value) => Str::lower($value))
            ->values();

        $createdIds = [];
        foreach ($newTags as $tagName) {
            $existing = BlogTag::query()->where('name', $tagName)->first();
            if ($existing !== null) {
                $createdIds[] = (int) $existing->id;
                continue;
            }

            $baseSlug = Str::slug($tagName);
            if ($baseSlug === '') {
                $baseSlug = Str::slug(Str::random(8));
            }

            $slug = $baseSlug;
            $suffix = 1;
            while (BlogTag::where('slug', $slug)->exists()) {
                $suffix++;
                $slug = $baseSlug . '-' . $suffix;
            }

            $tag = BlogTag::create([
                'name' => $tagName,
                'slug' => $slug,
            ]);

            $createdIds[] = (int) $tag->id;
        }

        $post->tags()->sync($existingTagIds->merge($createdIds)->unique()->all());
    }

    private function syncAttachments(BlogPost $post, Request $request): void
    {
        if (!$request->hasFile('attachments')) {
            return;
        }

        foreach ((array) $request->file('attachments', []) as $upload) {
            if ($upload === null) {
                continue;
            }

            $path = $upload->store('blog', 'public');
            $post->attachments()->create([
                'path' => $path,
                'original_name' => $upload->getClientOriginalName(),
                'mime_type' => $upload->getClientMimeType(),
                'size' => $upload->getSize(),
            ]);
        }
    }

    private function sanitizeHtml(?string $html): string
    {
        $html = (string) ($html ?? '');
        if (trim($html) === '') {
            return '';
        }

        $allowedTags = [
            'a' => ['href', 'title', 'target', 'rel'],
            'blockquote' => ['style', 'class'],
            'br' => [],
            'code' => ['class'],
            'div' => ['style', 'class'],
            'em' => ['style', 'class'],
            'figure' => ['style', 'class'],
            'figcaption' => ['style', 'class'],
            'h1' => ['style', 'class'],
            'h2' => ['style', 'class'],
            'h3' => ['style', 'class'],
            'h4' => ['style', 'class'],
            'hr' => ['class'],
            'img' => ['src', 'alt', 'title', 'style', 'class'],
            'li' => ['style', 'class'],
            'ol' => ['style', 'class'],
            'p' => ['style', 'class'],
            'pre' => ['style', 'class'],
            'span' => ['style', 'class'],
            'strong' => ['style', 'class'],
            'table' => ['style', 'class'],
            'tbody' => ['style', 'class'],
            'td' => ['style', 'class'],
            'th' => ['style', 'class'],
            'thead' => ['style', 'class'],
            'tr' => ['style', 'class'],
            'u' => ['style', 'class'],
            'ul' => ['style', 'class'],
        ];

        $allowedClassPrefixes = ['blog-'];
        $allowedStyles = [
            'background',
            'background-color',
            'border',
            'border-color',
            'border-radius',
            'border-style',
            'border-width',
            'color',
            'display',
            'font-size',
            'font-style',
            'font-weight',
            'gap',
            'grid-template-columns',
            'line-height',
            'margin',
            'margin-bottom',
            'margin-left',
            'margin-right',
            'margin-top',
            'max-width',
            'padding',
            'padding-bottom',
            'padding-left',
            'padding-right',
            'padding-top',
            'text-align',
            'text-decoration',
            'width',
        ];

        $allowedUriSchemes = ['http', 'https', 'mailto'];

        $document = new \DOMDocument();
        $internalErrors = libxml_use_internal_errors(true);

        $document->loadHTML(
            '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>',
            LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        $nodes = [];
        foreach ($document->getElementsByTagName('*') as $node) {
            $nodes[] = $node;
        }

        foreach ($nodes as $node) {
            $tag = strtolower($node->nodeName);

            if (in_array($tag, ['html', 'head', 'body'], true)) {
                continue;
            }
            if (in_array($tag, ['script', 'style'], true)) {
                $node->parentNode?->removeChild($node);
                continue;
            }

            if (!array_key_exists($tag, $allowedTags)) {
                $this->unwrapNode($node);
                continue;
            }

            $allowedAttributes = $allowedTags[$tag];
            if ($node->hasAttributes()) {
                $attributes = [];
                foreach ($node->attributes as $attribute) {
                    $attributes[$attribute->nodeName] = $attribute->nodeValue;
                }

                foreach ($attributes as $name => $value) {
                    if (!in_array($name, $allowedAttributes, true)) {
                        $node->removeAttribute($name);
                        continue;
                    }

                    if ($name === 'style') {
                        $filteredStyle = $this->filterInlineStyles($value, $allowedStyles);
                        if ($filteredStyle === '') {
                            $node->removeAttribute('style');
                        } else {
                            $node->setAttribute('style', $filteredStyle);
                        }
                        continue;
                    }

                    if ($name === 'class') {
                        $filteredClass = $this->filterClasses($value, $allowedClassPrefixes);
                        if ($filteredClass === '') {
                            $node->removeAttribute('class');
                        } else {
                            $node->setAttribute('class', $filteredClass);
                        }
                        continue;
                    }

                    if ($tag === 'a' && $name === 'href') {
                        if (!$this->isAllowedUrl($value, $allowedUriSchemes)) {
                            $node->removeAttribute('href');
                        } else {
                            $target = $node->getAttribute('target');
                            if ($target === '_blank') {
                                $rel = $node->getAttribute('rel');
                                $relParts = array_filter(explode(' ', strtolower($rel)));
                                $relParts = array_unique(array_merge($relParts, ['noopener', 'noreferrer']));
                                $node->setAttribute('rel', implode(' ', $relParts));
                            }
                        }
                        continue;
                    }

                    if ($tag === 'img' && $name === 'src') {
                        if (!$this->isAllowedUrl($value, $allowedUriSchemes)) {
                            $node->removeAttribute('src');
                        }
                        continue;
                    }
                }
            }

            if ($tag === 'img' && !$node->hasAttribute('alt')) {
                $node->setAttribute('alt', '');
            }
        }

        $body = $document->getElementsByTagName('body')->item(0);
        if ($body === null) {
            return trim(strip_tags($html));
        }

        $output = '';
        foreach ($body->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    private function unwrapNode(\DOMNode $node): void
    {
        $parent = $node->parentNode;
        if ($parent === null) {
            $node->parentNode?->removeChild($node);
            return;
        }

        while ($node->firstChild !== null) {
            $parent->insertBefore($node->firstChild, $node);
        }

        $parent->removeChild($node);
    }

    private function filterInlineStyles(string $style, array $allowedStyles): string
    {
        $rules = explode(';', $style);
        $clean = [];

        foreach ($rules as $rule) {
            if (trim($rule) === '') {
                continue;
            }

            [$property, $value] = array_pad(explode(':', $rule, 2), 2, '');
            $property = strtolower(trim($property));
            $value = trim($value);

            if ($property === '' || !in_array($property, $allowedStyles, true)) {
                continue;
            }

            if (stripos($value, 'expression') !== false || stripos($value, 'javascript:') !== false || stripos($value, 'url(') !== false) {
                continue;
            }

            $clean[] = $property . ':' . $value;
        }

        return implode(';', $clean);
    }

    private function filterClasses(string $classAttr, array $allowedPrefixes): string
    {
        $classes = preg_split('/\s+/', trim($classAttr));
        $classes = array_filter($classes, function (string $class) use ($allowedPrefixes): bool {
            foreach ($allowedPrefixes as $prefix) {
                if (str_starts_with($class, $prefix)) {
                    return true;
                }
            }

            return false;
        });

        return implode(' ', $classes);
    }

    private function isAllowedUrl(string $url, array $allowedSchemes): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        $parsed = parse_url($url);
        if ($parsed === false) {
            return false;
        }

        if (!isset($parsed['scheme'])) {
            return true;
        }

        return in_array(strtolower($parsed['scheme']), $allowedSchemes, true);
    }

    private function prepareCustomization(array $data, ?BlogPost $post = null): array
    {
        $themes = (array) config('blog.themes', []);
        $defaultTheme = (string) config('blog.default_theme', 'classic');

        $resolveTheme = static function () use ($themes, $defaultTheme): string {
            if ($themes === []) {
                return $defaultTheme;
            }

            $keys = array_keys($themes);
            if ($keys === []) {
                return $defaultTheme;
            }

            $randomKey = $keys[array_rand($keys)];
            if (!array_key_exists($randomKey, $themes)) {
                return $defaultTheme;
            }

            return $randomKey;
        };

        if ($post !== null && $post->exists) {
            $theme = (string) ($post->theme ?? $defaultTheme);
            if (!array_key_exists($theme, $themes)) {
                $theme = $defaultTheme;
            }

            $accent = $this->normalizeHexColor($post->accent_color)
                ?? $this->normalizeHexColor($themes[$theme]['accent'] ?? null)
                ?? $this->normalizeHexColor((string) config('blog.default_accent', '#2563EB'));

            $textAccent = $this->normalizeHexColor($post->accent_text_color)
                ?? $this->normalizeHexColor($themes[$theme]['text'] ?? null)
                ?? $this->normalizeHexColor((string) config('blog.default_text_color', '#0F172A'));
        } else {
            $theme = $resolveTheme();

            if (!array_key_exists($theme, $themes)) {
                $theme = $defaultTheme;
            }

            $accent = $this->normalizeHexColor($themes[$theme]['accent'] ?? null)
                ?? $this->normalizeHexColor((string) config('blog.default_accent', '#2563EB'));

            $textAccent = $this->normalizeHexColor($themes[$theme]['text'] ?? null)
                ?? $this->normalizeHexColor((string) config('blog.default_text_color', '#0F172A'));
        }

        $data['theme'] = $theme;
        $data['accent_color'] = $accent;
        $data['accent_text_color'] = $textAccent;
        $data['hero_image_url'] = isset($data['hero_image_url']) && $data['hero_image_url'] !== ''
            ? trim((string) $data['hero_image_url'])
            : null;
        $data['hero_image_caption'] = isset($data['hero_image_caption']) && $data['hero_image_caption'] !== ''
            ? trim((string) $data['hero_image_caption'])
            : null;

        return $data;
    }

    private function normalizeHexColor(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = strtoupper(ltrim(trim($value), '#'));
        if ($trimmed === '') {
            return null;
        }

        if (!preg_match('/^[0-9A-F]{6}$/', $trimmed)) {
            return null;
        }

        return '#' . $trimmed;
    }
}
