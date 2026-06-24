<?php

namespace App\Support;

use App\Enums\ProductContributorRole;
use App\Enums\ProductField;
use App\Enums\ProductIndexSortField;
use App\Enums\ProductPriority;
use App\Enums\ProductReListenValue;
use App\Models\Option;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
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
        'notes' => ['notes'],
        'start_date' => ['start_date'],
        'end_date' => ['end_date'],
        'num_re_listen_times' => ['num_re_listen_times'],
        're_listen_value' => ['re_listen_value'],
        'priority' => ['priority'],
        'circle' => ['circle', 'maker_id'],
        'description' => ['description', 'description_english'],
    ];

    private const DISPLAY_VALUE_FIELDS = [
        'start_date',
        'end_date',
        'num_re_listen_times',
        're_listen_value',
        'priority',
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
            ->containsStrict((string) $product->getKey());
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
                fn ($query) => $query->where('age_category', $filters->ageCategory->value)
            )
            ->when(
                $filters->progress !== null,
                fn ($query) => $query->where('progress', $filters->progress->value)
            )
            ->when(
                $filters->genre !== '',
                fn ($query) => $query->filterGenre($filters->genre)
            )
            ->when(
                $filters->series !== '',
                fn ($query) => $query->filterSeries($filters->series)
            )
            ->when(
                $filters->circle !== '',
                fn ($query) => $query->filterCircle($filters->circle)
            )
            ->when(
                $filters->scenario !== '',
                fn ($query) => $query->filterContributor('scenario', $filters->scenario)
            )
            ->when(
                $filters->voiceActor !== '',
                fn ($query) => $query->filterContributor('voice_actor', $filters->voiceActor)
            )
            ->when(
                $filters->illustration !== '',
                fn ($query) => $query->filterContributor('illustration', $filters->illustration)
            )
            ->when(
                $filters->author !== '',
                fn ($query) => $query->filterContributor('author', $filters->author)
            )
            ->when(
                $filters->description !== '',
                fn ($query) => $query->filterDescription($filters->description)
            )
            ->when(
                $filters->title !== '',
                fn ($query) => $query->filterTitle($filters->title)
            )
            ->when(
                $filters->notes !== '',
                fn ($query) => $query->filterNotes($filters->notes)
            )
            ->when(
                $filters->score !== null,
                fn ($query) => $query->where('score', (int) $filters->score->value)
            )
            ->when(
                $filters->priority !== null,
                fn ($query) => $query->where('priority', (int) $filters->priority->value)
            )
            ->when(
                $filters->numReListenTimes !== null,
                fn ($query) => $query->where('num_re_listen_times', $filters->numReListenTimes)
            )
            ->when(
                $filters->reListenValue !== null,
                fn ($query) => $query->where('re_listen_value', (int) $filters->reListenValue->value)
            )
            ->when(
                $filters->startDateFrom !== '',
                fn ($query) => $query->where('start_date_sort', '>=', $this->dateSortValueFromInput($filters->startDateFrom))
            )
            ->when(
                $filters->startDateTo !== '',
                fn ($query) => $query->where('start_date_sort', '<=', $this->dateSortValueFromInput($filters->startDateTo))
            )
            ->when(
                $filters->endDateFrom !== '',
                fn ($query) => $query->where('end_date_sort', '>=', $this->dateSortValueFromInput($filters->endDateFrom))
            )
            ->when(
                $filters->endDateTo !== '',
                fn ($query) => $query->where('end_date_sort', '<=', $this->dateSortValueFromInput($filters->endDateTo))
            )
            ->when(
                $filters->createdAtFrom !== '',
                fn ($query) => $query->whereDate('created_at', '>=', $filters->createdAtFrom)
            )
            ->when(
                $filters->createdAtTo !== '',
                fn ($query) => $query->whereDate('created_at', '<=', $filters->createdAtTo)
            )
            ->when(
                $filters->updatedAtFrom !== '',
                fn ($query) => $query->whereDate('updated_at', '>=', $filters->updatedAtFrom)
            )
            ->when(
                $filters->updatedAtTo !== '',
                fn ($query) => $query->whereDate('updated_at', '<=', $filters->updatedAtTo)
            )
            ->when(
                $filters->tags !== '',
                fn ($query) => $query->filterTags(
                    $filters->parsedTags(),
                    $filters->resolvedTagMatch(),
                )
            )
            ->when(
                $filters->search !== '',
                fn ($query) => $query->searchIndex($filters->search)
            );
    }

    public function loadVisibleGenres(array $productIds, bool $useGroupOrdering): Collection
    {
        if ($productIds === []) {
            return collect();
        }

        // Index only needs visible EN/custom tags, so use one lightweight query
        // instead of hydrating genre relationships for every listed product.
        if (! $useGroupOrdering) {
            return DB::table('genre_product')
                ->join('genres', 'genres.id', '=', 'genre_product.genre_id')
                ->whereIn('genre_product.product_id', $productIds)
                ->where(VisibleGenreAttachment::query())
                ->where('genres.hidden_on_index', false)
                ->whereNotExists(function ($query): void {
                    $query
                        ->select('hidden_genre_group_genre.genre_id')
                        ->from('genre_group_genre as hidden_genre_group_genre')
                        ->join('genre_groups as hidden_genre_groups', 'hidden_genre_groups.id', '=', 'hidden_genre_group_genre.genre_group_id')
                        ->whereColumn('hidden_genre_group_genre.genre_id', 'genres.id')
                        ->where('hidden_genre_groups.hidden_on_index', true);
                })
                ->orderBy('genres.order')
                ->orderBy('genres.title')
                ->orderBy('genres.id')
                ->get([
                    'genre_product.product_id',
                    'genres.id',
                    'genres.title',
                ])
                ->groupBy('product_id');
        }

        $groupedGenres = DB::table('genre_product')
            ->join('genres', 'genres.id', '=', 'genre_product.genre_id')
            ->join('genre_group_genre', 'genre_group_genre.genre_id', '=', 'genres.id')
            ->join('genre_groups', 'genre_groups.id', '=', 'genre_group_genre.genre_group_id')
            ->whereIn('genre_product.product_id', $productIds)
            ->where(VisibleGenreAttachment::query())
            ->where('genres.hidden_on_index', false)
            ->where('genre_groups.hidden_on_index', false)
            ->whereNotExists(function ($query): void {
                $query
                    ->select('hidden_genre_group_genre.genre_id')
                    ->from('genre_group_genre as hidden_genre_group_genre')
                    ->join('genre_groups as hidden_genre_groups', 'hidden_genre_groups.id', '=', 'hidden_genre_group_genre.genre_group_id')
                    ->whereColumn('hidden_genre_group_genre.genre_id', 'genres.id')
                    ->where('hidden_genre_groups.hidden_on_index', true);
            })
            ->orderBy('genre_groups.order')
            ->orderBy('genre_group_genre.order')
            ->orderBy('genre_groups.title')
            ->orderBy('genres.title')
            ->get([
                'genre_product.product_id',
                'genres.id',
                'genres.title',
            ])
            ->unique(fn ($genre): string => $genre->product_id.'|'.$genre->id)
            ->values();

        $ungroupedGenres = DB::table('genre_product')
            ->join('genres', 'genres.id', '=', 'genre_product.genre_id')
            ->whereIn('genre_product.product_id', $productIds)
            ->where(VisibleGenreAttachment::query())
            ->where('genres.hidden_on_index', false)
            ->whereNotExists(function ($query): void {
                $query
                    ->select('genre_group_genre.genre_id')
                    ->from('genre_group_genre')
                    ->whereColumn('genre_group_genre.genre_id', 'genres.id');
            })
            ->orderBy('genres.order')
            ->orderBy('genres.title')
            ->get([
                'genre_product.product_id',
                'genres.id',
                'genres.title',
            ]);

        return $groupedGenres
            ->concat($ungroupedGenres)
            ->groupBy('product_id');
    }

    public function loadContributors(array $productIds, array $visibleFields): Collection
    {
        $roles = collect($visibleFields)
            ->map(fn (string $field): ?string => ProductField::tryFrom($field)?->contributorRole()?->value)
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
            ->map(fn ($rows) => $rows->groupBy('role'));
    }

    public function displayValues(EloquentCollection $products, array $visibleFields): Collection
    {
        $fields = array_intersect(self::DISPLAY_VALUE_FIELDS, $visibleFields);

        if ($products->isEmpty() || $fields === []) {
            return collect();
        }

        return $products->mapWithKeys(function (Product $product) use ($fields): array {
            $values = [];

            foreach ($fields as $field) {
                $values[$field] = $this->displayValue($product, $field);
            }

            return [$product->getKey() => $values];
        });
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

            if ($this->orderBySpecialSortField($query, $sort->field, $sort->direction->value)) {
                continue;
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
            ->orderByRaw($query->getQuery()->getGrammar()->wrap($column).' IS NULL')
            ->orderBy($column, $direction);
    }

    private function orderBySpecialSortField(Builder $query, ProductIndexSortField $field, string $direction): bool
    {
        [$expression, $bindings] = match ($field) {
            ProductIndexSortField::Circle => $this->circleSortExpression($query),
            ProductIndexSortField::Scenario => $this->contributorSortExpression(ProductContributorRole::Scenario),
            ProductIndexSortField::Illustration => $this->contributorSortExpression(ProductContributorRole::Illustration),
            ProductIndexSortField::VoiceActor => $this->contributorSortExpression(ProductContributorRole::VoiceActor),
            ProductIndexSortField::Author => $this->contributorSortExpression(ProductContributorRole::Author),
            default => [null, []],
        };

        if ($expression === null) {
            return false;
        }

        $this->orderByNullableExpression($query, $expression, $direction, $bindings);

        return true;
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function circleSortExpression(Builder $query): array
    {
        [$contributorExpression, $bindings] = $this->contributorSortExpression(ProductContributorRole::Circle);

        return [
            'COALESCE('.$contributorExpression.', '.$query->getQuery()->getGrammar()->wrap('circle').')',
            $bindings,
        ];
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function contributorSortExpression(ProductContributorRole $role): array
    {
        $subquery = DB::table('contributor_product')
            ->join('contributors', 'contributors.id', '=', 'contributor_product.contributor_id')
            ->whereColumn('contributor_product.product_id', 'products.id')
            ->where('contributor_product.role', $role->value)
            ->selectRaw('min(contributors.name)');

        return [
            '('.$subquery->toSql().')',
            $subquery->getBindings(),
        ];
    }

    /**
     * @param  list<mixed>  $bindings
     */
    private function orderByNullableExpression(Builder $query, string $expression, string $direction, array $bindings): void
    {
        $query
            ->orderByRaw("({$expression}) IS NULL", $bindings)
            ->orderByRaw("{$expression} {$direction}", $bindings);
    }

    private function dateSortValueFromInput(string $date): int
    {
        return (int) str_replace('-', '', $date);
    }

    private function displayValue(Product $product, string $field): string
    {
        return match ($field) {
            'start_date' => PartialDateFormatter::format($product->start_date) ?? '-',
            'end_date' => PartialDateFormatter::format($product->end_date) ?? '-',
            'num_re_listen_times' => $product->num_re_listen_times === null
                ? '-'
                : (string) $product->num_re_listen_times,
            're_listen_value' => $product->re_listen_value === null
                ? '-'
                : (ProductReListenValue::tryFrom((string) $product->re_listen_value)?->label() ?? '-'),
            'priority' => $product->priority === null
                ? '-'
                : (ProductPriority::tryFrom((string) $product->priority)?->label() ?? '-'),
            default => '-',
        };
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
