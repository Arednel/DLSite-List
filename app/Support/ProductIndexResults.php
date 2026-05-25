<?php

namespace App\Support;

use App\Enums\ProductIndexSortField;
use App\Models\Option;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class ProductIndexResults
{
    private const INDEX_COLUMNS = [
        'id',
        'work_name',
        'work_name_english',
        'notes',
        'score',
        'series',
        'age_category',
        'progress',
        'work_image',
        'priority',
        'num_re_listen_times',
        're_listen_value',
        'start_date',
        'end_date',
        'created_at',
    ];

    public function getProducts(ProductIndexFilters $filters, int|string $perPage): EloquentCollection|LengthAwarePaginator
    {
        $query = $this->filteredQuery($filters);
        $query = $this->applySqlSorting($query, $filters->sorts());

        return $perPage === Option::INDEX_PER_PAGE_UNLIMITED
            ? $query->get()
            : $query->paginate((int) $perPage);
    }

    public function containsProduct(ProductIndexFilters $filters, Product $product): bool
    {
        return $this->filteredQuery($filters)
            ->whereKey($product->getKey())
            ->exists();
    }

    public function pageContainsProduct(
        ProductIndexFilters $filters,
        Product $product,
        int|string $perPage,
        int $page,
    ): bool {
        if ($perPage === Option::INDEX_PER_PAGE_UNLIMITED) {
            return $this->containsProduct($filters, $product);
        }

        return $this->applySqlSorting($this->filteredQuery($filters), $filters->sorts())
            ->forPage(max(1, $page), max(1, (int) $perPage))
            ->pluck('id')
            ->contains((string) $product->getKey());
    }

    public function pageForProduct(ProductIndexFilters $filters, Product $product, int|string $perPage): ?int
    {
        if ($perPage === Option::INDEX_PER_PAGE_UNLIMITED) {
            return null;
        }

        $position = $this->applySqlSorting($this->filteredQuery($filters), $filters->sorts())
            ->pluck('id')
            ->search((string) $product->getKey(), true);

        return $position === false
            ? null
            : intdiv($position, max(1, (int) $perPage)) + 1;
    }

    public function lastPage(ProductIndexFilters $filters, int|string $perPage): ?int
    {
        if ($perPage === Option::INDEX_PER_PAGE_UNLIMITED) {
            return null;
        }

        $perPage = max(1, (int) $perPage);
        $total = $this->filteredQuery($filters)->count();

        return max(1, (int) ceil($total / $perPage));
    }

    private function filteredQuery(ProductIndexFilters $filters): Builder
    {
        return Product::query()
            ->select(self::INDEX_COLUMNS)
            ->when(
                $filters->ageCategory !== null,
                fn($query) => $query->where('age_category', $filters->ageCategory->value)
            )
            ->when(
                $filters->progress !== null,
                fn($query) => $query->where('progress', $filters->progress->value)
            )
            ->when(
                $filters->genre !== '',
                fn($query) => $query->filterGenre($filters->genre)
            )
            ->when(
                $filters->series !== '',
                fn($query) => $query->filterSeries($filters->series)
            )
            ->when(
                $filters->title !== '',
                fn($query) => $query->filterTitle($filters->title)
            )
            ->when(
                $filters->notes !== '',
                fn($query) => $query->filterNotes($filters->notes)
            )
            ->when(
                $filters->score !== null,
                fn($query) => $query->where('score', (int) $filters->score->value)
            )
            ->when(
                $filters->priority !== null,
                fn($query) => $query->where('priority', (int) $filters->priority->value)
            )
            ->when(
                $filters->numReListenTimes !== null,
                fn($query) => $query->where('num_re_listen_times', $filters->numReListenTimes)
            )
            ->when(
                $filters->reListenValue !== null,
                fn($query) => $query->where('re_listen_value', (int) $filters->reListenValue->value)
            )
            ->when(
                $filters->tags !== '',
                fn($query) => $query->filterTags(
                    $filters->parsedTags(),
                    $filters->resolvedTagMatch(),
                )
            )
            ->when(
                $filters->search !== '',
                fn($query) => $query->searchIndex($filters->search)
            );
    }

    public function loadVisibleGenres(array $productIds): \Illuminate\Support\Collection
    {
        if ($productIds === []) {
            return collect();
        }

        // Index only needs visible EN/custom tags, so use one lightweight query
        // instead of hydrating genre relationships for every listed product.
        return DB::table('genre_product')
            ->join('genres', 'genres.id', '=', 'genre_product.genre_id')
            ->whereIn('genre_product.product_id', $productIds)
            ->where(VisibleGenreAttachment::query())
            ->orderBy('genres.title')
            ->get([
                'genre_product.product_id',
                'genres.id',
                'genres.title',
            ])
            ->groupBy('product_id');
    }

    /**
     * @param  list<ProductIndexSort>  $sorts
     */
    private function applySqlSorting(Builder $query, array $sorts): Builder
    {
        $hasRjSort = false;

        foreach ($sorts as $sort) {
            if ($sort->field === ProductIndexSortField::RJ) {
                $hasRjSort = true;
            }

            $this->orderByNullableColumn($query, $sort->field->sqlColumn(), $sort->direction->value);
        }

        if (! $hasRjSort) {
            $this->orderByNullableColumn($query, ProductIndexSortField::RJ->sqlColumn(), 'desc');
        }

        return $query;
    }

    private function orderByNullableColumn(Builder $query, string $column, string $direction): void
    {
        $query
            ->orderByRaw($query->getQuery()->getGrammar()->wrap($column) . ' IS NULL')
            ->orderBy($column, $direction);
    }
}
