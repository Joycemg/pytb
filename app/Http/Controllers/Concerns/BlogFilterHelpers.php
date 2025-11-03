<?php declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

trait BlogFilterHelpers
{
    /**
     * @param array{q:string,tag:string} $filters
     * @return array{search:string}
     */
    protected function normalizeBlogFilters(array $filters): array
    {
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search === '') {
            $tag = trim((string) ($filters['tag'] ?? ''));
            if ($tag !== '') {
                $search = ltrim($tag, '#');
            }
        }

        return [
            'search' => $search,
        ];
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query
     * @param array{search:string} $filters
     */
    protected function applyBlogFilters($query, array $filters): void
    {
        if ($filters['search'] === '') {
            return;
        }

        $search = $filters['search'];
        $normalized = ltrim($search, '#');

        $escapeLike = static function (string $value): string {
            return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value) . '%';
        };

        $titleLike = $escapeLike($normalized);
        $rawLike = $escapeLike($search);

        $query->where(function ($builder) use ($titleLike, $rawLike, $normalized, $search): void {
            $builder->where('title', 'like', $titleLike);

            if ($normalized !== $search) {
                $builder->orWhere('title', 'like', $rawLike);
            }

            $builder->orWhereHas('tags', function ($tagQuery) use ($titleLike, $rawLike, $normalized, $search): void {
                $tagQuery->where(function ($inner) use ($titleLike, $rawLike, $normalized, $search): void {
                    $inner->where('name', 'like', $titleLike)
                        ->orWhere('slug', 'like', $titleLike);

                    if ($normalized !== $search) {
                        $inner->orWhere('name', 'like', $rawLike)
                            ->orWhere('slug', 'like', $rawLike);
                    }
                });
            });

            $builder->orWhereHas('author', function ($authorQuery) use ($titleLike, $rawLike, $normalized, $search): void {
                $authorQuery->where(function ($inner) use ($titleLike, $rawLike, $normalized, $search): void {
                    $inner->where('name', 'like', $titleLike)
                        ->orWhere('email', 'like', $titleLike)
                        ->orWhere('username', 'like', $titleLike);

                    if ($normalized !== $search) {
                        $inner->orWhere('name', 'like', $rawLike)
                            ->orWhere('email', 'like', $rawLike)
                            ->orWhere('username', 'like', $rawLike);
                    }
                });
            });
        });
    }

    /**
     * @param array{search:string} $filters
     */
    protected function blogFiltersAreActive(array $filters): bool
    {
        return $filters['search'] !== '';
    }
}
