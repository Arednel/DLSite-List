<?php

namespace App\Support;

use App\Enums\ProductField;
use App\Enums\ProductIndexSortField;
use App\Models\Option;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class ProductIndexResults
{
    private const BASE_INDEX_COLUMNS = [
        'id',
        'work_name',
        'work_name_english',
        'notes',
        'progress',
    ];

    private const VISIBLE_FIELD_COLUMNS = [
        'image' => ['work_image'],
        'score' => ['score'],
        'series' => ['series'],
        'age_category' => ['age_category'],
        'circle' => ['circle', 'maker_id'],
        'description' => ['description', 'description_english'],
    ];

    public function getProducts(
        ProductIndexFilters $filters,
        int|string $perPage,
        array $visibleFields = [],
    ): EloquentCollection|LengthAwarePaginator {
        $query = $this->filteredQuery($filters, $this->indexColumns($visibleFields));
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

    private function filteredQuery(ProductIndexFilters $filters, ?array $columns = null): Builder
    {
        return Product::query()
            ->select($columns ?? ['id'])
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
                $filters->circle !== '',
                fn($query) => $query->filterCircle($filters->circle)
            )
            ->when(
                $filters->scenario !== '',
                fn($query) => $query->filterContributor('scenario', $filters->scenario)
            )
            ->when(
                $filters->voiceActor !== '',
                fn($query) => $query->filterContributor('voice_actor', $filters->voiceActor)
            )
            ->when(
                $filters->illustration !== '',
                fn($query) => $query->filterContributor('illustration', $filters->illustration)
            )
            ->when(
                $filters->author !== '',
                fn($query) => $query->filterContributor('author', $filters->author)
            )
            ->when(
                $filters->description !== '',
                fn($query) => $query->filterDescription($filters->description)
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

    public function loadContributors(array $productIds, array $visibleFields): \Illuminate\Support\Collection
    {
        $roles = collect($visibleFields)
            ->map(fn(string $field): ?string => ProductField::tryFrom($field)?->contributorRole()?->value)
            ->filter()
            ->values()
            ->all();

        if ($productIds === [] || $roles === []) {
            return collect();
        }

        return DB::table('contributor_product')
            ->join('contributors', 'contributors.id', '=', 'contributor_product.contributor_id')
            ->whereIn('contributor_product.product_id', $productIds)
            ->whereIn('contributor_product.role', $roles)
            ->orderBy('contributors.name')
            ->get([
                'contributor_product.product_id',
                'contributor_product.role',
                'contributors.id',
                'contributors.name',
                'contributors.maker_id',
            ])
            ->groupBy('product_id')
            ->map(fn($rows) => $rows->groupBy('role'));
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

    private function indexColumns(array $visibleFields): array
    {
        $columns = self::BASE_INDEX_COLUMNS;

        foreach ($visibleFields as $field) {
            $columns = array_merge($columns, self::VISIBLE_FIELD_COLUMNS[$field] ?? []);
        }

        return array_values(array_unique($columns));
    }
}
