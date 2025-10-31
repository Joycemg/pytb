<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BlogFilterHelpers;
use App\Models\BlogPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

final class BlogFeedController extends Controller
{
    use BlogFilterHelpers;

    public function atom(): Response
    {
        $posts = BlogPost::query()
            ->published()
            ->latest('published_at')
            ->with(['author', 'tags'])
            ->take(30)
            ->get();

        $items = $this->mapPostsForFeeds($posts);
        $firstItem = $items->first();
        $updatedAt = is_array($firstItem) ? ($firstItem['published_at'] ?? now()) : now();

        $content = view('blog.feeds.atom', [
            'items' => $items,
            'updatedAt' => $updatedAt,
            'siteTitle' => config('app.name', 'La Taberna') . ' · Blog',
            'siteUrl' => route('blog.index'),
        ])->render();

        return response($content, 200)
            ->header('Content-Type', 'application/atom+xml; charset=UTF-8');
    }

    public function json(Request $request): JsonResponse
    {
        $rawFilters = [
            'q' => trim((string) $request->query('q', '')),
            'author' => $request->query('author', ''),
            'tag' => trim((string) $request->query('tag', '')),
            'from' => trim((string) $request->query('from', '')),
            'to' => trim((string) $request->query('to', '')),
        ];

        $normalizedFilters = $this->normalizeBlogFilters($rawFilters);
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 50));

        $query = BlogPost::query()
            ->published()
            ->latest('published_at')
            ->with(['author', 'tags']);

        $this->applyBlogFilters($query, $normalizedFilters);

        $posts = $query
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => $posts->getCollection()->map(fn (BlogPost $post) => $this->mapPost($post))->all(),
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
                'input' => $rawFilters,
                'applied' => [
                    'search' => $normalizedFilters['search'],
                    'author_id' => $normalizedFilters['author_id'],
                    'tag' => $normalizedFilters['tag_slug'],
                    'from' => $normalizedFilters['from']?->toDateString(),
                    'to' => $normalizedFilters['to']?->toDateString(),
                ],
                'active' => $this->blogFiltersAreActive($normalizedFilters),
            ],
            'feeds' => [
                'atom' => route('blog.atom'),
            ],
        ]);
    }

    /**
     * @param Collection<int, BlogPost> $posts
     * @return Collection<int, array<string, mixed>>
     */
    private function mapPostsForFeeds(Collection $posts): Collection
    {
        return $posts->map(function (BlogPost $post): array {
            $url = route('blog.show', ['post' => $post->slug]);
            $summary = $post->meta_description
                ?? $post->excerpt
                ?? $post->excerpt_computed;

            return [
                'id' => $url,
                'title' => $post->title,
                'url' => $url,
                'summary' => $summary,
                'content' => $post->content,
                'published_at' => $post->published_at ?? now(),
                'author' => $post->author->name ?? null,
                'tags' => $post->tags->pluck('name')->filter()->values()->all(),
                'meta_image' => $post->meta_image_url ?? $post->hero_image_url,
            ];
        });
    }

    private function mapPost(BlogPost $post): array
    {
        return [
            'id' => $post->id,
            'slug' => $post->slug,
            'title' => $post->title,
            'excerpt' => $post->excerpt_computed,
            'content' => $post->content,
            'published_at' => $post->published_at?->toIso8601String(),
            'author' => $post->author === null ? null : [
                'id' => $post->author->id,
                'name' => $post->author->name,
            ],
            'tags' => $post->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ])->all(),
            'links' => [
                'html' => route('blog.show', ['post' => $post->slug]),
            ],
        ];
    }
}
