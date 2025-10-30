<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\BlogPostRequest;
use App\Models\BlogAttachment;
use App\Models\BlogPost;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class BlogController extends Controller
{
    public function home(Request $request): View
    {
        /** @var LengthAwarePaginator $posts */
        $posts = BlogPost::query()
            ->published()
            ->latest('published_at')
            ->with(['author'])
            ->paginate(10);

        $history = BlogPost::query()
            ->published()
            ->latest('published_at')
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

        if ($request->wantsJson()) {
            return view('blog.index', [
                'posts' => $posts,
                'history' => $history,
            ]);
        }

        return view('blog.index', [
            'posts' => $posts,
            'history' => $history,
        ]);
    }

    public function show(BlogPost $post): View
    {
        if ($post->published_at === null || $post->published_at->isFuture()) {
            $user = auth()->user();
            if ($user === null || (!$user->hasAnyRole(['admin', 'moderator']) && $user->id !== $post->user_id)) {
                abort(404);
            }
        }

        return view('blog.show', [
            'post' => $post->load(['author', 'attachments']),
        ]);
    }

    public function manage(): View
    {
        $this->authorize('viewAny', BlogPost::class);

        $posts = BlogPost::query()
            ->with(['author'])
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('blog.manage.index', [
            'posts' => $posts,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', BlogPost::class);

        return view('blog.manage.form', [
            'post' => new BlogPost(),
        ]);
    }

    public function store(BlogPostRequest $request): RedirectResponse
    {
        $this->authorize('create', BlogPost::class);

        $data = $request->validated();
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

        $this->syncAttachments($post, $request);

        return redirect()
            ->route('blog.manage')
            ->with('status', 'Entrada creada correctamente.');
    }

    public function edit(BlogPost $post): View
    {
        $this->authorize('update', $post);

        return view('blog.manage.form', [
            'post' => $post->load('attachments'),
        ]);
    }

    public function update(BlogPostRequest $request, BlogPost $post): RedirectResponse
    {
        $this->authorize('update', $post);

        $data = $request->validated();
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

        $this->syncAttachments($post, $request);

        return redirect()
            ->route('blog.manage')
            ->with('status', 'Entrada actualizada.');
    }

    public function destroy(BlogPost $post): RedirectResponse
    {
        $this->authorize('delete', $post);

        foreach ($post->attachments as $attachment) {
            Storage::disk('public')->delete($attachment->path);
            $attachment->delete();
        }

        $post->delete();

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
            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        $nodes = [];
        foreach ($document->getElementsByTagName('*') as $node) {
            $nodes[] = $node;
        }

        foreach ($nodes as $node) {
            $tag = strtolower($node->nodeName);
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
            return '';
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
        $theme = (string) ($data['theme'] ?? ($post?->theme ?? $defaultTheme));
        if (!array_key_exists($theme, $themes)) {
            $theme = $defaultTheme;
        }

        $data['theme'] = $theme;

        $accent = $this->normalizeHexColor($data['accent_color'] ?? null);
        if ($accent === null) {
            $accent = $this->normalizeHexColor($themes[$theme]['accent'] ?? null)
                ?? $this->normalizeHexColor((string) config('blog.default_accent', '#2563EB'));
        }

        $textAccent = $this->normalizeHexColor($data['accent_text_color'] ?? null);
        if ($textAccent === null) {
            $textAccent = $this->normalizeHexColor($themes[$theme]['text'] ?? null)
                ?? $this->normalizeHexColor((string) config('blog.default_text_color', '#0F172A'));
        }

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
