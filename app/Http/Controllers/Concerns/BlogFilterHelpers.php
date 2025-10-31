<?php declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Carbon;

trait BlogFilterHelpers
{
    /**
     * @param array{q:string,author:mixed,tag:string,from:string,to:string} $filters
     * @return array{search:string,author_id:?int,tag_slug:string,from:?Carbon,to:?Carbon}
     */
    protected function normalizeBlogFilters(array $filters): array
    {
        $search = trim((string) ($filters['q'] ?? ''));

        $authorId = null;
        if (isset($filters['author']) && $filters['author'] !== '' && $filters['author'] !== null) {
            if (is_numeric($filters['author'])) {
                $authorId = (int) $filters['author'];
                if ($authorId <= 0) {
                    $authorId = null;
                }
            }
        }

        $tagSlug = trim((string) ($filters['tag'] ?? ''));

        $from = $this->parseBlogDateFilter($filters['from'] ?? null, false);
        $to = $this->parseBlogDateFilter($filters['to'] ?? null, true);

        return [
            'search' => $search,
            'author_id' => $authorId,
            'tag_slug' => $tagSlug,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query
     * @param array{search:string,author_id:?int,tag_slug:string,from:?Carbon,to:?Carbon} $filters
     */
    protected function applyBlogFilters($query, array $filters): void
    {
        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search): void {
                $builder->where('title', 'like', '%' . $search . '%')
                    ->orWhere('excerpt', 'like', '%' . $search . '%')
                    ->orWhere('content', 'like', '%' . $search . '%');
            });
        }

        if ($filters['author_id'] !== null) {
            $query->where('user_id', $filters['author_id']);
        }

        if ($filters['tag_slug'] !== '') {
            $tagSlug = $filters['tag_slug'];
            $query->whereHas('tags', function ($tagQuery) use ($tagSlug): void {
                $tagQuery->where('slug', $tagSlug);
            });
        }

        if ($filters['from'] !== null) {
            $query->where('published_at', '>=', $filters['from']);
        }

        if ($filters['to'] !== null) {
            $query->where('published_at', '<=', $filters['to']);
        }
    }

    /**
     * @param array{search:string,author_id:?int,tag_slug:string,from:?Carbon,to:?Carbon} $filters
     */
    protected function blogFiltersAreActive(array $filters): bool
    {
        return $filters['search'] !== ''
            || $filters['author_id'] !== null
            || $filters['tag_slug'] !== ''
            || $filters['from'] !== null
            || $filters['to'] !== null;
    }

    private function parseBlogDateFilter(?string $value, bool $endOfDay): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        try {
            $date = Carbon::parse($trimmed);
        } catch (\Throwable $exception) {
            return null;
        }

        return $endOfDay ? $date->endOfDay() : $date->startOfDay();
    }
}
