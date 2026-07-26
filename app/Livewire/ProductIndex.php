<?php

namespace App\Livewire;

use App\Enums\ProductField;
use App\Enums\ProductIndexSortDirection;
use App\Enums\ProductIndexSortField;
use App\Enums\ProductIndexTagMatch;
use App\Models\GenreGroup;
use App\Models\Option;
use App\Support\ProductFieldLayout;
use App\Support\ProductIndexFilters;
use App\Support\ProductIndexResults;
use App\Support\ProductIndexRowBuilder;
use App\Support\TagColor;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class ProductIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $title = '';

    public string $notes = '';

    public string $genre = '';

    public string $series = '';

    public string $circle = '';

    public string $scenario = '';

    public string $voice_actor = '';

    public string $illustration = '';

    public string $author = '';

    public string $description = '';

    public string $description_english = '';

    public string $tags = '';

    public string $tag_match = '';

    public string $age_category = '';

    public string $progress = '';

    public string $score = '';

    public string $priority = '';

    public string $num_re_listen_times = '';

    public string $re_listen_value = '';

    public string $start_date_from = '';

    public string $start_date_to = '';

    public string $end_date_from = '';

    public string $end_date_to = '';

    public string $created_at_from = '';

    public string $created_at_to = '';

    public string $updated_at_from = '';

    public string $updated_at_to = '';

    public string $sort_first_field = '';

    public string $sort_first_direction = '';

    public string $sort_second_field = '';

    public string $sort_second_direction = '';

    public string $searchInput = '';

    protected function queryString(): array
    {
        return collect(ProductIndexFilters::INPUT_KEYS)
            ->mapWithKeys(fn(string $key): array => [$key => []])
            ->all();
    }

    /**
     * @var array<string, string>
     */
    public array $draft = [];

    public function mount(): void
    {
        $this->syncStateFromFilters(ProductIndexFilters::fromQuery($this->currentInput()));
        $this->searchInput = $this->search;
        $this->syncDraftFromCurrent();
    }

    public function render(): View
    {
        $productIndexResults = app(ProductIndexResults::class);

        $filters = $this->currentFilters();
        $filterQuery = $filters->toQuery();
        $settings = Option::productIndexSettings();

        $hydratedIndexFields = $settings->visibleIndexFields;

        if ($settings->dlsiteAgeAppropriateLinksEnabled) {
            $hydratedIndexFields[] = ProductField::AgeCategory->value;
            $hydratedIndexFields = array_values(array_unique($hydratedIndexFields));
        }

        $products = $productIndexResults->getProducts(
            $filters,
            $settings->perPage,
            $hydratedIndexFields,
            $settings->searchHiddenDescriptionsEnabled,
        );

        $isUnlimited = $settings->perPage === Option::INDEX_PER_PAGE_UNLIMITED;

        // Normalize the current page to a collection for grouped lookups and row building.
        $visibleProducts = $products instanceof LengthAwarePaginator
            ? $products->getCollection()
            : $products;

        $visibleProductIds = $visibleProducts->modelKeys();

        $visibleTagBuckets = ProductFieldLayout::visibleIndexTagBuckets($settings->indexFieldLayout);
        $tagsColumnVisible = $visibleTagBuckets['custom'] || $visibleTagBuckets['fetched'];
        $indexTagColorsEnabled = $tagsColumnVisible
            && $visibleProductIds !== []
            && ($settings->tagColorSurfaces[Option::TAG_COLOR_SURFACE_INDEX] ?? false)
            && TagColor::hasAnyConfiguredColors();

        // Load optional table data only when the current column layout can actually show it.
        $productGenres = $tagsColumnVisible
            ? $productIndexResults->loadVisibleGenres(
                $visibleProductIds,
                $settings->indexGroupOrderingEnabled,
                $indexTagColorsEnabled,
                $visibleProductIds !== [] && GenreGroup::query()->hiddenOnIndex()->exists(),
                $visibleTagBuckets,
            )
            : collect();

        $productContributors = $productIndexResults->loadContributors(
            $visibleProductIds,
            $settings->visibleIndexFields,
        );
        $productDisplayValues = $productIndexResults->displayValues($visibleProducts, $settings->visibleIndexFields);

        $currentQuery = $this->queryWithCurrentPage($filterQuery, $isUnlimited);
        $tagLinkQuery = $filters->toQueryWithout('genre');
        $productRows = app(ProductIndexRowBuilder::class)->build(
            $visibleProducts,
            $productContributors,
            $settings->dlsiteAgeAppropriateLinksEnabled,
            $currentQuery,
        );

        return view('livewire.product-index', [
            'products' => $products,
            'productRows' => $productRows,
            'productGenres' => $productGenres,
            'productDisplayValues' => $productDisplayValues,
            'filterOptions' => ProductIndexFilters::optionSets($settings->indexSortFieldOptions),
            'indexColumns' => $settings->indexColumns,
            'filterFields' => $settings->filterFields,
            'filterActive' => $filterQuery !== [],
            'hasCurrentTagFilter' => $filters->genre !== '',
            'progressHeading' => $filters->progressHeading(),
            'activeProgress' => $filters->progress?->value,
            'allProgressQuery' => $filters->toQueryWithout(['progress', 'genre']),
            'isUnlimited' => $isUnlimited,
            'totalProducts' => $products instanceof LengthAwarePaginator ? $products->total() : $products->count(),
            'tagHrefPrefix' => route('index', $tagLinkQuery, false)
                . ($tagLinkQuery === [] ? '?' : '&'),
            'quickAddUrl' => route('products.create', [
                'return_query' => $currentQuery,
            ], false),
            'productFormModalEnabled' => $settings->productFormModalEnabled,
            'productFormModalCompletionAction' => $settings->productFormModalCompletionAction,
            'sortIcons' => $this->sortIcons($filters),
            'tableWidthCss' => $settings->tableWidthCss,
        ]);
    }

    public function applyFilters(): void
    {
        $draft = array_merge($this->draft, [
            'search' => $this->search,
            'genre' => $this->genre,
        ]);

        $this->syncStateFromFilters(ProductIndexFilters::fromQuery($draft));
        $this->searchInput = $this->search;
        $this->syncDraftFromCurrent();
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->syncStateFromFilters(new ProductIndexFilters);
        $this->searchInput = '';
        $this->syncDraftFromCurrent();
        $this->resetPage();
    }

    public function applySearch(): void
    {
        $this->search = trim($this->searchInput);
        $this->syncDraftFromCurrent();
        $this->resetPage();
    }

    public function sortByHeader(string $field): void
    {
        if (ProductIndexSortField::tryFrom($field) === null) {
            return;
        }

        if ($this->sort_first_field === $field) {
            $this->sort_first_direction = $this->sort_first_direction === ProductIndexSortDirection::Asc->value
                ? ProductIndexSortDirection::Desc->value
                : ProductIndexSortDirection::Asc->value;
        } else {
            $this->sort_first_field = $field;
            $this->sort_first_direction = ProductIndexSortDirection::Desc->value;
        }

        $this->sort_second_field = '';
        $this->sort_second_direction = '';
        $this->syncDraftFromCurrent();
        $this->resetPage();
    }

    /**
     * @return array<string, string>
     */
    private function sortIcons(ProductIndexFilters $filters): array
    {
        $icons = [];

        foreach (ProductIndexSortField::cases() as $field) {
            $icons[$field->value] = $filters->primarySort?->field === $field
                ? ($filters->primarySort->direction === ProductIndexSortDirection::Asc ? '↑' : '↓')
                : '⇅';
        }

        return $icons;
    }

    /**
     * @return array<string, string>
     */
    private function currentInput(): array
    {
        return collect(ProductIndexFilters::INPUT_KEYS)
            ->mapWithKeys(fn(string $key): array => [$key => (string) $this->{$key}])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function queryWithCurrentPage(array $query, bool $isUnlimited): array
    {
        if (! $isUnlimited) {
            $page = $this->getPage();

            if ($page > 1) {
                $query['page'] = (string) $page;
            }
        }

        return $query;
    }

    private function syncStateFromFilters(ProductIndexFilters $filters): void
    {
        foreach ($filters->toInput() as $key => $value) {
            $this->{$key} = $value;
        }
    }

    private function syncDraftFromCurrent(): void
    {
        $draft = $this->currentFilters()->toInput();

        $draft['tag_match'] = $draft['tag_match'] !== ''
            ? $draft['tag_match']
            : ProductIndexTagMatch::All->value;
        $draft['sort_first_direction'] = $draft['sort_first_direction'] !== ''
            ? $draft['sort_first_direction']
            : ProductIndexSortDirection::Desc->value;
        $draft['sort_second_direction'] = $draft['sort_second_direction'] !== ''
            ? $draft['sort_second_direction']
            : ProductIndexSortDirection::Desc->value;

        $this->draft = $draft;
    }

    private function currentFilters(): ProductIndexFilters
    {
        return ProductIndexFilters::fromQuery($this->currentInput());
    }
}
