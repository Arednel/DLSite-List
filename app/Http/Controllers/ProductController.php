<?php

namespace App\Http\Controllers;

use App\Enums\ProductAgeCategory;
use App\Enums\ProductContributorRole;
use App\Enums\ProductField;
use App\Enums\ProductFormat;
use App\Enums\ProductPriority;
use App\Enums\ProductProgress;
use App\Enums\ProductReListenValue;
use App\Http\Requests\BaseProductRequest;
use App\Http\Requests\StoreCustomProductRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Genre;
use App\Models\Option;
use App\Models\Product;
use App\Support\DLSite\DLSiteFetchResult;
use App\Support\DLSite\DLSiteProductAlreadyExistsException;
use App\Support\DLSite\DLSiteProductImportException;
use App\Support\DLSite\DLSiteProductImporter;
use App\Support\DLSite\DLSiteProductImportInput;
use App\Support\PartialDateFormatter;
use App\Support\ProductContributorSync;
use App\Support\ProductFieldLayout;
use App\Support\ProductGenreSync;
use App\Support\ProductImageSubmission;
use App\Support\ReturnTarget;
use App\Support\ReturnUrl;
use App\Support\TagColor;
use App\Support\TagInput;
use App\Support\VisibleGenreAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use League\Flysystem\UnableToListContents;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductGenreSync $genreSync,
        private readonly ProductContributorSync $contributorSync,
        private readonly ProductImageSubmission $imageSubmission,
    ) {}

    private const VISIBILITY_AFFECTING_PRODUCT_FIELDS = [
        'work_name',
        'work_name_english',
        'description',
        'description_english',
        'notes',
        'series',
        'age_category',
        'product_format',
        'circle',
        'publisher',
        'brand',
        'maker_id',
        'progress',
        'score',
        'priority',
        'num_re_listen_times',
        're_listen_value',
    ];

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('Index');
    }

    public function create(Request $request)
    {
        return $this->createView($request, false);
    }

    public function create_custom(Request $request)
    {
        return $this->createView($request, true);
    }

    public function tagLibrary()
    {
        return view('TagLibrary', $this->productFormModalSettings());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request, DLSiteProductImporter $importer)
    {
        $validated = $request->validated();

        try {
            $result = $importer->import(
                $validated['id'],
                DLSiteProductImportInput::fromRequest(
                    $request,
                    $validated,
                    Option::quickAddFieldLayout(),
                ),
            );
        } catch (DLSiteProductAlreadyExistsException) {
            throw ValidationException::withMessages([
                'id' => __('errors.Work with this product code is already in your library'),
            ]);
        } catch (DLSiteProductImportException $exception) {
            throw ValidationException::withMessages([
                'id' => $exception->displayMessage(),
            ]);
        }

        $returnTarget = ReturnTarget::fromRequest($request)
            ->forProduct($result->product);

        return $this->productMutationResponse(
            $request,
            $returnTarget->toUrl(),
            DLSiteFetchResult::displayImageFailureMessage($result->warning),
        );
    }

    public function store_custom(StoreCustomProductRequest $request)
    {
        $validated = $request->validated();
        $workID = $validated['id'];
        $visibleCreateFields = ProductFieldLayout::visibleFields(Option::customQuickAddFieldLayout());
        $coverVisible = $this->createFieldVisible($visibleCreateFields, ProductField::Image);
        $samplesVisible = $this->createFieldVisible($visibleCreateFields, ProductField::SampleImages);
        [$description, $descriptionEnglish] = $this->customCreateDescriptionValues(
            $request,
            $validated,
            $visibleCreateFields,
        );
        [$circle, $makerId] = $this->customCreateCircleValues($request, $validated, $visibleCreateFields);

        $product = $this->imageSubmission->create(
            $request,
            $workID,
            $coverVisible,
            $samplesVisible,
            function (array $images) use (
                $request,
                $validated,
                $visibleCreateFields,
                $workID,
                $description,
                $descriptionEnglish,
                $circle,
                $makerId,
            ): Product {
                $product = Product::create([
                    'id' => $workID,
                    'maker_id' => $makerId,
                    'work_name' => $validated['work_name'],
                    'work_name_english' => $validated['work_name_english'] ?? null,
                    'age_category' => $validated['age_category'],
                    'product_format' => $this->createFieldSubmitted($request, $visibleCreateFields, ProductField::ProductFormat, 'product_format')
                        ? (($validated['product_format'] ?? []) ?: null)
                        : null,
                    'circle' => $circle,
                    'publisher' => $this->createFieldSubmitted($request, $visibleCreateFields, ProductField::Publisher, 'publisher')
                        ? ($validated['publisher'] ?? null) : null,
                    'brand' => $this->createFieldSubmitted($request, $visibleCreateFields, ProductField::Brand, 'brand')
                        ? ($validated['brand'] ?? null) : null,
                    'work_image' => $images['work_image'],
                    'description' => $description,
                    'description_english' => $descriptionEnglish,
                    'notes' => $this->createFieldSubmitted($request, $visibleCreateFields, ProductField::Notes, 'notes')
                        ? ($validated['notes'] ?? null)
                        : null,
                    'series' => $this->createFieldSubmitted($request, $visibleCreateFields, ProductField::Series, 'series')
                        ? ($validated['series'] ?? null)
                        : null,
                    'sample_images' => $images['sample_images'],
                    'score' => $this->createFieldSubmitted($request, $visibleCreateFields, ProductField::Score, 'score')
                        ? ($validated['score'] ?? null)
                        : null,
                    'progress' => $this->createFieldSubmitted($request, $visibleCreateFields, ProductField::Progress, 'progress')
                        ? ($validated['progress'] ?? null)
                        : null,
                    'start_date' => $this->createFieldSubmitted($request, $visibleCreateFields, ProductField::StartDate, 'add.start_date')
                        ? ($validated['start_date'] ?? null)
                        : null,
                    'end_date' => $this->createFieldSubmitted($request, $visibleCreateFields, ProductField::FinishDate, 'add.finish_date')
                        ? ($validated['end_date'] ?? null)
                        : null,
                    'num_re_listen_times' => $this->createFieldSubmitted($request, $visibleCreateFields, ProductField::TotalTimesReListened, 'add.num_re_listen_times')
                        ? ($validated['num_re_listen_times'] ?? null)
                        : null,
                    're_listen_value' => $this->createFieldSubmitted($request, $visibleCreateFields, ProductField::ReListenValue, 'add.re_listen_value')
                        ? ($validated['re_listen_value'] ?? null)
                        : null,
                    'priority' => $this->createFieldSubmitted($request, $visibleCreateFields, ProductField::Priority, 'add.priority')
                        ? ($validated['priority'] ?? null)
                        : null,
                ]);

                $this->genreSync->syncCustom(
                    $product,
                    Genre::resolveIdsFromTitles(
                        $this->createFieldVisible($visibleCreateFields, ProductField::Tags)
                            ? ($validated['genre_custom'] ?? [])
                            : [],
                    ),
                );
                $this->contributorSync->sync(
                    $product,
                    $this->customCreateContributorsByRole($validated, $visibleCreateFields, $circle),
                    $makerId,
                );

                return $product;
            },
        );

        $returnTarget = ReturnTarget::fromRequest($request)
            ->forProduct($product);

        return $this->productMutationResponse($request, $returnTarget->toUrl());
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Product $product)
    {
        $fetchedLanguage = Option::effectiveFetchedTagLanguage()->value;
        $editGenres = $this->loadEditGenresForProduct($product->getKey(), $fetchedLanguage);
        $showReadonlyGenreColors = Option::tagColorSurfaceEnabled(Option::TAG_COLOR_SURFACE_EDIT_READONLY);
        $genreColorPairs = $showReadonlyGenreColors
            ? TagColor::effectiveColorPairsForGenreIds($editGenres->flatMap(fn(Collection $genres): Collection => $genres)->pluck('id'))
            : collect();
        $fetchedGenres = $this->editGenreDisplayRows(
            $editGenres->get(Genre::PIVOT_SOURCE_FETCHED, collect()),
            $genreColorPairs,
        );
        $customGenres = $this->editGenreDisplayRows(
            $editGenres->get(Genre::PIVOT_SOURCE_CUSTOM, collect()),
            $genreColorPairs,
        );
        $persistedFetchedInput = TagInput::format($fetchedGenres->pluck('title'));
        $returnTarget = ReturnTarget::fromRequest($request, $product->getKey());

        return view('Edit', [
            'product' => $product,
            'isModal' => $request->boolean('modal'),
            'productFormThemeClass' => $this->productFormThemeClass(),
            'fetchedGenres' => $fetchedGenres,
            'customGenres' => $customGenres,
            'genreFetchedInput' => $persistedFetchedInput !== '' ? $persistedFetchedInput . ', ' : '',
            'genreFetchedLanguage' => $fetchedLanguage,
            'genreCustomInput' => TagInput::format($customGenres->pluck('title')),
            'showReadonlyGenreColors' => $showReadonlyGenreColors,
            'editFields' => ProductFieldLayout::editFields(Option::editFieldLayout()),
            'contributorInputs' => $this->formatContributorInputs($product),
            'readonlyFieldValues' => $this->readonlyFieldValues($product),
            'progressOptions' => ProductProgress::visibleOptions(
                Option::optionalProductStatuses(),
                $product->progress,
            ),
            'returnQuery' => $returnTarget->query,
            'returnFragment' => $returnTarget->fragment,
            'returnUrl' => $returnTarget->toUrl(),
            'ageCategoryOptions' => ProductAgeCategory::options(),
            ...self::buildDateFieldOptions(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Product $product)
    {
        $oldProgress = $product->progress;

        $data = $request->validated();

        $editFieldLayout = Option::editFieldLayout();
        $editableFields = ProductFieldLayout::editableFields($editFieldLayout);
        $updatePayload = $this->updatePayload($request, $data, $editableFields, $product);
        $applyEdit = function (array $images) use (
            $request,
            $product,
            $data,
            $editableFields,
            $editFieldLayout,
            $updatePayload,
        ): array {
            $product->fill([
                ...$updatePayload,
                'work_image' => $images['work_image'],
                'sample_images' => $images['sample_images'],
            ]);
            $productFieldsChanged = $product->isDirty(self::VISIBILITY_AFFECTING_PRODUCT_FIELDS);

            if ($product->isDirty()) {
                $product->save();
            }

            return [
                'product_fields_changed' => $productFieldsChanged,
                'contributors_changed' => $this->syncProductEditContributors($request, $product, $data, $editableFields),
                'genres_changed' => $this->syncProductEditGenres($request, $product, $data, $editFieldLayout),
            ];
        };

        $changes = $this->imageSubmission->update(
            $request,
            $product,
            ProductFieldLayout::editable($editFieldLayout, ProductField::Image),
            ProductFieldLayout::editable($editFieldLayout, ProductField::SampleImages),
            $applyEdit,
        );

        $productFieldsChanged = $changes['product_fields_changed'];
        $contributorsChanged = $changes['contributors_changed'];
        $genresChanged = $changes['genres_changed'];

        $returnTarget = ReturnTarget::fromRequest($request, $product->getKey());
        $newProgress = in_array(ProductField::Progress->value, $editableFields, true) && $request->wasSubmitted('progress')
            ? ($data['progress'] ?? null)
            : $oldProgress;

        if ($oldProgress !== $newProgress) {
            $returnTarget = $returnTarget->withIndexProgress($newProgress);
        }

        $redirectUrl = $returnTarget->forProduct(
            $product,
            visibilityMayHaveChanged: $productFieldsChanged || $genresChanged || $contributorsChanged,
        )->toUrl();

        return $this->productMutationResponse($request, $redirectUrl);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Product $product)
    {
        $returnTarget = ReturnTarget::fromRequest($request);
        $id = (string) $product->getKey();

        $imageDirectory = "Works/{$id}";
        $localDisk = Storage::disk('local');
        $jsonPath = "Works/{$id}.json";
        if (! $localDisk->delete($jsonPath)) {
            Log::warning('Unable to delete product JSON.', [
                'product_id' => $id,
                'path' => $jsonPath,
            ]);
        }

        // Listing original-work files is optional cleanup and must not block product deletion.
        try {
            $paths = $localDisk->files('Works');
        } catch (UnableToListContents $exception) {
            Log::warning('Unable to list original-work JSON files for deletion.', [
                'product_id' => $id,
                'path' => 'Works',
                'reason' => $exception->getMessage(),
            ]);
            $paths = [];
        }
        $jsonPattern = '/^Works\/' . preg_quote($id, '/') . ' - (?:RJ|BJ|VJ)[0-9]+\.json$/D';

        foreach ($paths as $path) {
            if (preg_match($jsonPattern, $path) !== 1) {
                continue;
            }

            if (! $localDisk->delete($path)) {
                Log::warning('Unable to delete product JSON.', [
                    'product_id' => $id,
                    'path' => $path,
                ]);
            }
        }

        if (! Storage::disk('public')->deleteDirectory($imageDirectory)) {
            Log::warning('Unable to delete product image directory.', [
                'product_id' => $id,
                'path' => $imageDirectory,
            ]);
        }

        $product->delete();

        // Return to same page but no anchor (work is gone)
        return $this->productMutationResponse(
            $request,
            $returnTarget->afterDeleting()->toUrl(),
            completionStatus: __('":work" removed', ['work' => $id]),
            completionDescription: __(
                'The work was removed from your library. You can continue if this window does not close automatically.',
            ),
        );
    }

    private function formatContributorInputs(Product $product): array
    {
        return collect($this->contributorSync->namesByRole($product))
            ->map(fn(array $names): string => TagInput::format($names))
            ->all();
    }

    private function readonlyFieldValues(Product $product): array
    {
        return [
            ProductField::ProductFormat->value => ($product->product_format ?? []) === []
                ? null
                : implode(', ', ProductFormat::labelsFor($product->product_format)),
            ProductField::DescriptionJapanese->value => $product->description,
            ProductField::DescriptionEnglish->value => $product->description_english,
            ProductField::Notes->value => $product->notes,
            ProductField::StartDate->value => PartialDateFormatter::format($product->start_date),
            ProductField::FinishDate->value => PartialDateFormatter::format($product->end_date),
            ProductField::TotalTimesReListened->value => $product->num_re_listen_times,
            ProductField::ReListenValue->value => ProductReListenValue::tryFrom((string) $product->re_listen_value)?->label(),
            ProductField::Priority->value => ProductPriority::tryFrom((string) $product->priority)?->label(),
        ];
    }

    private function customCreateCircleValues(
        StoreCustomProductRequest $request,
        array $data,
        array $visibleFields,
    ): array {
        if (! $this->createFieldVisible($visibleFields, ProductField::Circle)) {
            return [null, null];
        }

        return [
            $request->wasSubmitted('circle') ? ($data['circle'] ?? null) : null,
            $request->wasSubmitted('maker_id') ? ($data['maker_id'] ?? null) : null,
        ];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function customCreateDescriptionValues(
        StoreCustomProductRequest $request,
        array $data,
        array $visibleFields,
    ): array {
        $description = $this->createFieldSubmitted($request, $visibleFields, ProductField::DescriptionJapanese, 'description')
            ? ($data['description'] ?? null)
            : null;
        $englishDescription = $this->createFieldSubmitted(
            $request,
            $visibleFields,
            ProductField::DescriptionEnglish,
            'description_english'
        )
            ? ($data['description_english'] ?? null)
            : null;

        return [
            $description,
            $englishDescription === $description ? null : $englishDescription,
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function customCreateContributorsByRole(array $data, array $visibleFields, ?string $circle): array
    {
        $contributors = [
            ProductContributorRole::Circle->value => filled($circle) ? [$circle] : [],
        ];

        foreach (ProductContributorRole::cases() as $role) {
            if ($role === ProductContributorRole::Circle) {
                continue;
            }

            $field = $role->productField();

            if ($this->createFieldVisible($visibleFields, $field)) {
                $contributors[$role->value] = array_values($data[$role->value] ?? []);
            }
        }

        return $contributors;
    }

    private function createFieldSubmitted(
        BaseProductRequest $request,
        array $visibleFields,
        ProductField $field,
        string|array $submittedKeys,
    ): bool {
        return $this->createFieldVisible($visibleFields, $field)
            && $request->hasAny($submittedKeys);
    }

    private function createFieldVisible(array $visibleFields, ProductField $field): bool
    {
        return in_array($field->value, $visibleFields, true);
    }

    private function createView(Request $request, bool $isCustomCreate)
    {
        if (! $request->has('return_query') && $request->old('return_query') !== null) {
            $request->merge(['return_query' => $request->old('return_query')]);
        }

        $returnTarget = ReturnTarget::fromRequest($request);
        // Keep the original back target when switching between DLSite and Custom create.
        $returnUrl = ReturnUrl::fromRequest($request);

        $returnParameters = ['return_url' => $returnUrl];

        if ($request->boolean('modal')) {
            $returnParameters['modal'] = '1';
        }

        if ($returnTarget->query !== []) {
            $returnParameters['return_query'] = $returnTarget->query;
        }

        return view('Create', [
            'isCustomCreate' => $isCustomCreate,
            'isBulkImport' => false,
            'isModal' => $request->boolean('modal'),
            'productFormThemeClass' => $this->productFormThemeClass(),
            'quickAddFields' => $isCustomCreate
                ? ProductFieldLayout::customQuickAddFields(Option::customQuickAddFieldLayout())
                : ProductFieldLayout::quickAddFields(Option::quickAddFieldLayout()),
            'returnQuery' => $returnTarget->query,
            'returnUrl' => $returnUrl,
            'returnParameters' => $returnParameters,
            'ageCategoryOptions' => ProductAgeCategory::options(),
            'progressOptions' => ProductProgress::visibleOptions(Option::optionalProductStatuses()),
            ...self::buildDateFieldOptions(),
        ]);
    }

    private function productFormThemeClass(): string
    {
        return 'product-form-theme-' . Option::productFormTheme();
    }

    /**
     * @return array{productFormModalEnabled: bool, productFormModalCompletionAction: string}
     */
    private function productFormModalSettings(): array
    {
        return [
            'productFormModalEnabled' => Option::productFormModalEnabled(),
            'productFormModalCompletionAction' => Option::productFormModalCompletionAction(),
        ];
    }

    private function productMutationResponse(
        Request $request,
        string $redirectUrl,
        ?string $warning = null,
        ?string $completionStatus = null,
        ?string $completionDescription = null,
    ): RedirectResponse|View {
        if (! $request->boolean('modal')) {
            return redirect($redirectUrl)
                ->with('dlsite_image_warning', $warning);
        }

        return view('WorkFormCompleted', [
            'redirectUrl' => $redirectUrl,
            'warning' => $warning,
            'completionStatus' => $completionStatus,
            'completionDescription' => $completionDescription,
        ]);
    }

    private function updatePayload(
        UpdateProductRequest $request,
        array $data,
        array $editableFields,
        Product $product,
    ): array {
        $payload = [];

        if (
            in_array(ProductField::Title->value, $editableFields, true)
            && $request->wasSubmitted('work_name')
        ) {
            $payload = [
                'work_name' => $data['work_name'],
                'work_name_english' => $data['work_name_english'] ?? null,
            ];
        }

        $japaneseDescriptionForDuplicateCheck = $this->effectiveJapaneseDescriptionForUpdate(
            $request,
            $data,
            $editableFields,
            $product,
        );

        foreach ($this->updatePayloadFieldMap() as $field => $submittedColumns) {
            if (
                ! in_array($field, $editableFields, true)
                || ! $request->hasAny(array_keys($submittedColumns))
            ) {
                continue;
            }

            $payload = [
                ...$payload,
                ...$this->updatePayloadForField($field, $data, $submittedColumns, $japaneseDescriptionForDuplicateCheck),
            ];
        }

        foreach (['start_date', 'end_date'] as $dateField) {
            if (
                array_key_exists($dateField, $payload)
                && Product::dateSortValue($payload[$dateField]) === Product::dateSortValue($product->{$dateField})
            ) {
                unset($payload[$dateField]);
            }
        }

        return $payload;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function updatePayloadFieldMap(): array
    {
        return [
            ProductField::Progress->value => ['progress' => 'progress'],
            ProductField::Score->value => ['score' => 'score'],
            ProductField::Series->value => ['series' => 'series'],
            ProductField::AgeCategory->value => ['age_category' => 'age_category'],
            ProductField::ProductFormat->value => ['product_format' => 'product_format'],
            ProductField::Publisher->value => ['publisher' => 'publisher'],
            ProductField::Brand->value => ['brand' => 'brand'],
            ProductField::Circle->value => [
                'circle' => 'circle',
                'maker_id' => 'maker_id',
            ],
            ProductField::DescriptionJapanese->value => ['description' => 'description'],
            ProductField::DescriptionEnglish->value => ['description_english' => 'description_english'],
            ProductField::Notes->value => ['notes' => 'notes'],
            ProductField::StartDate->value => ['add.start_date' => 'start_date'],
            ProductField::FinishDate->value => ['add.finish_date' => 'end_date'],
            ProductField::TotalTimesReListened->value => ['add.num_re_listen_times' => 'num_re_listen_times'],
            ProductField::ReListenValue->value => ['add.re_listen_value' => 're_listen_value'],
            ProductField::Priority->value => ['add.priority' => 'priority'],
        ];
    }

    /**
     * @param  array<string, string>  $submittedColumns
     */
    private function updatePayloadForField(
        string $field,
        array $data,
        array $submittedColumns,
        ?string $japaneseDescriptionForDuplicateCheck,
    ): array {
        if ($field === ProductField::DescriptionEnglish->value) {
            return $this->descriptionUpdatePayload($data, $japaneseDescriptionForDuplicateCheck);
        }

        if ($field === ProductField::ProductFormat->value) {
            return ['product_format' => ($data['product_format'] ?? []) ?: null];
        }

        return collect($submittedColumns)
            ->mapWithKeys(fn(string $column): array => [$column => $data[$column] ?? null])
            ->all();
    }

    private function effectiveJapaneseDescriptionForUpdate(
        UpdateProductRequest $request,
        array $data,
        array $editableFields,
        Product $product,
    ): ?string {
        if (
            in_array(ProductField::DescriptionJapanese->value, $editableFields, true)
            && $request->wasSubmitted('description')
        ) {
            return $data['description'] ?? null;
        }

        return $product->description;
    }

    private function descriptionUpdatePayload(array $data, ?string $japaneseDescription): array
    {
        $englishDescription = $data['description_english'] ?? null;

        if ($englishDescription === $japaneseDescription) {
            $englishDescription = null;
        }

        return [
            'description_english' => $englishDescription,
        ];
    }

    private function syncProductEditContributors(
        UpdateProductRequest $request,
        Product $product,
        array $data,
        array $editableFields,
    ): bool {
        $changed = false;

        foreach (ProductField::cases() as $field) {
            $role = $field->contributorRole();

            if (! $role || ! in_array($field->value, $editableFields, true)) {
                continue;
            }

            if (
                $role === ProductContributorRole::Circle
                && ! $request->wasSubmitted('circle')
                && ! $request->wasSubmitted('maker_id')
            ) {
                continue;
            }

            if ($role !== ProductContributorRole::Circle && ! $request->wasSubmitted($role->value)) {
                continue;
            }

            $newNames = $role === ProductContributorRole::Circle
                ? array_values(array_filter([$product->circle]))
                : ($data[$role->value] ?? []);

            $changed = $this->contributorSync->syncRole(
                $product,
                $role,
                $newNames,
                $role === ProductContributorRole::Circle ? $product->maker_id : null,
            ) || $changed;
        }

        return $changed;
    }


    /** @return array{monthLabels: array<int, string>, days: list<int>, years: list<int>} */
    public static function buildDateFieldOptions(): array
    {
        return [
            'monthLabels' => collect(range(1, 12))
                ->mapWithKeys(fn(int $month): array => [
                    $month => Carbon::create(2000, $month, 1)->translatedFormat('M'),
                ])
                ->all(),
            'days' => range(1, 31),
            'years' => range(now()->year, 1995),
        ];
    }

    private function loadEditGenresForProduct(string $productId, string $fetchedLanguage): Collection
    {
        // Edit separates readonly fetched genres from user-editable custom entries by pivot source.
        return DB::table('genre_product')
            ->join('genres', 'genres.id', '=', 'genre_product.genre_id')
            ->where('genre_product.product_id', $productId)
            ->where(VisibleGenreAttachment::query($fetchedLanguage))
            ->orderBy('genres.title')
            ->get([
                'genres.id',
                'genres.title',
                'genre_product.source',
            ])
            ->groupBy(fn(object $genre): string => $genre->source === Genre::PIVOT_SOURCE_CUSTOM
                ? Genre::PIVOT_SOURCE_CUSTOM
                : Genre::PIVOT_SOURCE_FETCHED);
    }

    private function editGenreDisplayRows(Collection $genres, Collection $colorPairs): Collection
    {
        return $genres
            ->map(function (object $genre) use ($colorPairs): object {
                $colors = $colorPairs->get((int) $genre->id, TagColor::pair(null, null));

                foreach (TagColor::viewData($colors['color'], $colors['text_color']) as $key => $value) {
                    $genre->{$key} = $value;
                }

                return $genre;
            });
    }

    private function syncProductGenres(Product $product, array $japaneseTitles, array $englishTitles, array $customTitles): void
    {
        $this->genreSync->sync($product, [
            Genre::LANGUAGE_JAPANESE => Genre::resolveIdsFromTitles($japaneseTitles),
            Genre::LANGUAGE_ENGLISH => Genre::resolveIdsFromTitles($englishTitles),
        ], Genre::resolveIdsFromTitles($customTitles));
    }

    private function syncProductEditGenres(
        UpdateProductRequest $request,
        Product $product,
        array $data,
        array $editFieldLayout,
    ): bool {
        $customGenreIds = null;
        $fetchedGenreIds = null;
        $fetchedLanguage = null;

        if (
            ProductFieldLayout::editable($editFieldLayout, ProductField::Tags)
            && $request->wasSubmitted('genre_custom')
        ) {
            $customGenreIds = Genre::resolveIdsFromTitles($data['genre_custom'] ?? []);
        }

        if (
            ProductFieldLayout::fetchedTagsEditable($editFieldLayout)
            && $request->wasSubmitted('genre_fetched')
        ) {
            $fetchedGenreIds = Genre::resolveIdsFromTitles($data['genre_fetched'] ?? []);
            $fetchedLanguage = $data['genre_fetched_language'];
        }

        if ($customGenreIds === null && $fetchedGenreIds === null) {
            return false;
        }

        return $this->genreSync->syncEditableTagBuckets(
            $product,
            $fetchedLanguage,
            $fetchedGenreIds,
            $customGenreIds,
        );
    }
}
