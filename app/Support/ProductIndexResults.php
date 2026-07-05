<?php

namespace App\Support;

use App\Enums\ProductContributorRole;
use App\Enums\ProductField;
use App\Enums\ProductIndexSortField;
use App\Enums\ProductPriority;
use App\Enums\ProductReListenValue;
use App\Models\Genre;
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
        'description_japanese' => ['description'],
        'description_english' => ['description_english'],
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
        array $visibleFields,
        bool $searchHiddenDescriptionsEnabled = false,
    ): EloquentCollection|LengthAwarePaginator {
        $query = $this->filteredQuery(
            $filters,
            $this->indexColumns($visibleFields),
            $this->descriptionSearchColumns($visibleFields, $searchHiddenDescriptionsEnabled),
            in_array(ProductField::Tags->value, $visibleFields, true),
        );
        $query = $this->applySqlSorting($query, $filters->sorts());

        return $perPage === Option::INDEX_PER_PAGE_UNLIMITED
            ? $query->get()
            : $query->paginate((int) $perPage);
    }

    public function containsProduct(
        ProductIndexFilters $filters,
        Product $product,
        array $descriptionSearchColumns,
        bool $searchTags = true,
    ): bool {
        return $this->filteredQuery($filters, null, $descriptionSearchColumns, $searchTags)
            ->whereKey($product->getKey())
            ->exists();
    }

    public function pageContainsProduct(
        ProductIndexFilters $filters,
        Product $product,
        int|string $perPage,
        int $page,
        array $descriptionSearchColumns,
        bool $searchTags = true,
    ): bool {
        if ($perPage === Option::INDEX_PER_PAGE_UNLIMITED) {
            return $this->containsProduct($filters, $product, $descriptionSearchColumns, $searchTags);
        }

        return $this->applySqlSorting($this->filteredQuery($filters, null, $descriptionSearchColumns, $searchTags), $filters->sorts())
            ->forPage(max(1, $page), max(1, (int) $perPage))
            ->pluck('id')
            ->containsStrict((string) $product->getKey());
    }

    public function pageForProduct(
        ProductIndexFilters $filters,
        Product $product,
        int|string $perPage,
        array $descriptionSearchColumns,
        bool $searchTags = true,
    ): ?int {
        if ($perPage === Option::INDEX_PER_PAGE_UNLIMITED) {
            return null;
        }

        $position = $this->applySqlSorting($this->filteredQuery($filters, null, $descriptionSearchColumns, $searchTags), $filters->sorts())
            ->pluck('id')
            ->search((string) $product->getKey(), true);

        return $position === false
            ? null
            : intdiv($position, max(1, (int) $perPage)) + 1;
    }

    public function lastPage(
        ProductIndexFilters $filters,
        int|string $perPage,
        array $descriptionSearchColumns,
        bool $searchTags = true,
    ): ?int {
        if ($perPage === Option::INDEX_PER_PAGE_UNLIMITED) {
            return null;
        }

        $perPage = max(1, (int) $perPage);
        $total = $this->filteredQuery($filters, null, $descriptionSearchColumns, $searchTags)->count();

        return max(1, (int) ceil($total / $perPage));
    }

    public function descriptionSearchColumns(array $visibleFields, bool $searchHiddenDescriptionsEnabled): array
    {
        if ($searchHiddenDescriptionsEnabled) {
            return ['description', 'description_english'];
        }

        $columns = [];

        if (in_array(ProductField::DescriptionJapanese->value, $visibleFields, true)) {
            $columns[] = 'description';
        }

        if (in_array(ProductField::DescriptionEnglish->value, $visibleFields, true)) {
            $columns[] = 'description_english';
        }

        return $columns;
    }

    private function filteredQuery(
        ProductIndexFilters $filters,
        ?array $columns = null,
        array $descriptionSearchColumns = [],
        bool $searchTags = true,
    ): Builder {
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
                $filters->descriptionEnglish !== '',
                fn($query) => $query->filterDescriptionEnglish($filters->descriptionEnglish)
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
                $filters->startDateFrom !== '',
                fn($query) => $query->where('start_date_sort', '>=', $this->dateSortValueFromInput($filters->startDateFrom))
            )
            ->when(
                $filters->startDateTo !== '',
                fn($query) => $query->where('start_date_sort', '<=', $this->dateSortValueFromInput($filters->startDateTo))
            )
            ->when(
                $filters->endDateFrom !== '',
                fn($query) => $query->where('end_date_sort', '>=', $this->dateSortValueFromInput($filters->endDateFrom))
            )
            ->when(
                $filters->endDateTo !== '',
                fn($query) => $query->where('end_date_sort', '<=', $this->dateSortValueFromInput($filters->endDateTo))
            )
            ->when(
                $filters->createdAtFrom !== '',
                fn($query) => $query->whereDate('created_at', '>=', $filters->createdAtFrom)
            )
            ->when(
                $filters->createdAtTo !== '',
                fn($query) => $query->whereDate('created_at', '<=', $filters->createdAtTo)
            )
            ->when(
                $filters->updatedAtFrom !== '',
                fn($query) => $query->whereDate('updated_at', '>=', $filters->updatedAtFrom)
            )
            ->when(
                $filters->updatedAtTo !== '',
                fn($query) => $query->whereDate('updated_at', '<=', $filters->updatedAtTo)
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
                fn($query) => $query->searchIndex($filters->search, $descriptionSearchColumns, $searchTags)
            );
    }

    public function loadVisibleGenres(
        array $productIds,
        bool $useGroupOrdering,
        bool $includeColors = false,
        bool $hasHiddenGroups = false,
        array $visibleTagBuckets = ['custom' => true, 'fetched_english' => true],
    ): Collection {
        if ($productIds === [] || ! (($visibleTagBuckets['custom'] ?? false) || ($visibleTagBuckets['fetched_english'] ?? false))) {
            return collect();
        }

        // Index only needs visible EN/custom tags, so use one lightweight query
        // instead of hydrating genre relationships for every listed product.
        if (! $useGroupOrdering) {
            $query = DB::table('genre_product')
                ->join('genres', 'genres.id', '=', 'genre_product.genre_id')
                ->whereIn('genre_product.product_id', $productIds)
                ->where(VisibleGenreAttachment::query())
                ->where('genres.hidden_on_index', false)
                ->orderBy('genres.title')
                ->orderBy('genres.id');

            if ($hasHiddenGroups) {
                $this->excludeGenresInHiddenGroups($query);
            }

            $this->applyVisibleTagBuckets($query, $visibleTagBuckets);

            if ($includeColors) {
                return $this->applyEffectiveGenreColors(
                    $query->get([
                        'genre_product.product_id',
                        'genres.id',
                        'genres.title',
                        'genres.color as tag_color',
                        'genres.text_color as tag_text_color',
                    ])
                )->groupBy('product_id');
            }

            return $query
                ->get([
                    'genre_product.product_id',
                    'genres.id',
                    'genres.title',
                ])
                ->groupBy('product_id');
        }

        $groupedQuery = DB::table('genre_product')
            ->join('genres', 'genres.id', '=', 'genre_product.genre_id')
            ->join('genre_group_genre', 'genre_group_genre.genre_id', '=', 'genres.id')
            ->join('genre_groups', 'genre_groups.id', '=', 'genre_group_genre.genre_group_id')
            ->whereIn('genre_product.product_id', $productIds)
            ->where(VisibleGenreAttachment::query())
            ->where('genres.hidden_on_index', false)
            ->where('genre_groups.hidden_on_index', false)
            ->orderBy('genre_groups.order')
            ->orderBy('genre_groups.title')
            ->orderBy('genre_groups.id')
            ->orderBy('genre_group_genre.order')
            ->orderBy('genres.title')
            ->orderBy('genres.id');

        if ($hasHiddenGroups) {
            $this->excludeGenresInHiddenGroups($groupedQuery);
        }

        $this->applyVisibleTagBuckets($groupedQuery, $visibleTagBuckets);

        $groupedSelect = [
            'genre_product.product_id',
            'genres.id',
            'genres.title',
        ];

        if ($includeColors) {
            $groupedSelect[] = 'genres.color as tag_color';
            $groupedSelect[] = 'genres.text_color as tag_text_color';
        }

        $groupedGenres = $groupedQuery->get($groupedSelect);

        $groupedGenres = $groupedGenres
            ->unique(fn($genre): string => $genre->product_id . '|' . $genre->id)
            ->values();

        $ungroupedSelect = [
            'genre_product.product_id',
            'genres.id',
            'genres.title',
        ];

        if ($includeColors) {
            $ungroupedSelect[] = 'genres.color as tag_color';
            $ungroupedSelect[] = 'genres.text_color as tag_text_color';
        }

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
            ->orderBy('genres.title')
            ->orderBy('genres.id');

        $this->applyVisibleTagBuckets($ungroupedGenres, $visibleTagBuckets);

        $ungroupedGenres = $ungroupedGenres->get($ungroupedSelect);

        $genres = $groupedGenres->concat($ungroupedGenres);

        if ($includeColors) {
            $genres = $this->applyEffectiveGenreColors($genres);
        }

        return $genres->groupBy('product_id');
    }

    public function loadContributors(array $productIds, array $visibleFields): Collection
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
            ->orderByRaw($query->getQuery()->getGrammar()->wrap($column) . ' IS NULL')
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
            'COALESCE(' . $contributorExpression . ', ' . $query->getQuery()->getGrammar()->wrap('circle') . ')',
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
            ->select('contributors.name')
            ->orderBy('contributors.name')
            ->limit(1);

        return [
            '(' . $subquery->toSql() . ')',
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

    private function excludeGenresInHiddenGroups(\Illuminate\Database\Query\Builder $query): void
    {
        $query->whereNotExists(function ($query): void {
            $query
                ->select('hidden_genre_group_genre.genre_id')
                ->from('genre_group_genre as hidden_genre_group_genre')
                ->join('genre_groups as hidden_genre_groups', 'hidden_genre_groups.id', '=', 'hidden_genre_group_genre.genre_group_id')
                ->whereColumn('hidden_genre_group_genre.genre_id', 'genres.id')
                ->where('hidden_genre_groups.hidden_on_index', true);
        });
    }

    private function applyVisibleTagBuckets(\Illuminate\Database\Query\Builder $query, array $visibleTagBuckets): void
    {
        $customVisible = (bool) ($visibleTagBuckets['custom'] ?? false);
        $fetchedEnglishVisible = (bool) ($visibleTagBuckets['fetched_english'] ?? false);

        if ($customVisible && $fetchedEnglishVisible) {
            return;
        }

        if ($customVisible) {
            $query->where('genre_product.source', Genre::PIVOT_SOURCE_CUSTOM);

            return;
        }

        if ($fetchedEnglishVisible) {
            $query->where('genre_product.source', Genre::PIVOT_SOURCE_FETCHED)
                ->whereExists(function ($languageQuery): void {
                    $languageQuery->select('genre_product_languages.id')
                        ->from('genre_product_languages')
                        ->whereColumn('genre_product_languages.genre_product_id', 'genre_product.id')
                        ->where('genre_product_languages.language', Genre::LANGUAGE_ENGLISH);
                });
        }
    }

    private function orderedGroupColorsByGenreId(Collection $genres): Collection
    {
        $genreIds = $genres
            ->pluck('id')
            ->map(fn($genreId): int => (int) $genreId)
            ->unique()
            ->values();

        if ($genreIds->isEmpty()) {
            return collect();
        }

        $rows = DB::table('genre_group_genre')
            ->join('genre_groups', 'genre_groups.id', '=', 'genre_group_genre.genre_group_id')
            ->whereIn('genre_group_genre.genre_id', $genreIds->all())
            ->where('genre_groups.hidden_on_index', false)
            ->whereAny(['genre_groups.color', 'genre_groups.text_color'], '<>', '')
            ->orderBy('genre_group_genre.genre_id')
            ->orderBy('genre_groups.order')
            ->orderBy('genre_group_genre.order')
            ->orderBy('genre_groups.title')
            ->get([
                'genre_group_genre.genre_id',
                'genre_groups.color',
                'genre_groups.text_color',
            ]);

        $colorsByGenreId = collect();

        foreach ($rows as $row) {
            $genreId = (int) $row->genre_id;
            $colors = $colorsByGenreId->get($genreId, (object) [
                'color' => null,
                'text_color' => null,
            ]);

            $colors->color ??= TagColor::normalize($row->color ?? null);
            $colors->text_color ??= TagColor::normalize($row->text_color ?? null);

            $colorsByGenreId->put($genreId, $colors);
        }

        return $colorsByGenreId;
    }

    private function applyEffectiveGenreColors(Collection $genres): Collection
    {
        $groupColorsByGenreId = $this->orderedGroupColorsByGenreId($genres);
        $colorViewDataByGenreId = $genres
            ->unique('id')
            ->mapWithKeys(function ($genre) use ($groupColorsByGenreId): array {
                $groupColors = $groupColorsByGenreId->get((int) $genre->id);

                return [
                    (int) $genre->id => TagColor::viewData(
                        TagColor::normalize($groupColors->color ?? null)
                            ?? TagColor::normalize($genre->tag_color ?? null),
                        TagColor::normalize($groupColors->text_color ?? null)
                            ?? TagColor::normalize($genre->tag_text_color ?? null),
                    ),
                ];
            });
        $emptyColorViewData = TagColor::viewData(null, null);

        return $genres->map(function ($genre) use ($colorViewDataByGenreId, $emptyColorViewData) {
            $colors = $colorViewDataByGenreId->get((int) $genre->id, $emptyColorViewData);

            foreach ($colors as $key => $value) {
                $genre->{$key} = $value;
            }

            unset($genre->tag_color, $genre->tag_text_color);

            return $genre;
        });
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
