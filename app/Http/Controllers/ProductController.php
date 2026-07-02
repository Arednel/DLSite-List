<?php

namespace App\Http\Controllers;

use App\Enums\ProductAgeCategory;
use App\Enums\ProductContributorRole;
use App\Enums\ProductField;
use App\Enums\ProductPriority;
use App\Enums\ProductReListenValue;
use App\Http\Requests\BaseProductRequest;
use App\Http\Requests\StoreCustomProductRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Genre;
use App\Models\Option;
use App\Models\Product;
use App\Support\DLSite\DLSitePythonRunner;
use App\Support\DLSite\DLSiteWorkData;
use App\Support\PartialDateFormatter;
use App\Support\ProductContributorSync;
use App\Support\ProductFieldLayout;
use App\Support\ProductGenreSync;
use App\Support\ReturnTarget;
use App\Support\TagColor;
use App\Support\TagInput;
use App\Support\VisibleGenreAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductGenreSync $genreSync,
        private readonly ProductContributorSync $contributorSync,
    ) {}

    private const VISIBILITY_AFFECTING_PRODUCT_FIELDS = [
        'work_name',
        'work_name_english',
        'description',
        'description_english',
        'notes',
        'series',
        'age_category',
        'circle',
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
        return view('TagLibrary');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request, DLSitePythonRunner $pythonRunner)
    {
        $validated = $request->validated();

        // Get RJ Code
        $workID = $validated['id'];

        // Get work info from DLSite
        $this->scrape($workID, $pythonRunner);

        // Get JSON info
        $json = Storage::disk('local')->get("Works/{$workID}.json");
        $workData = DLSiteWorkData::fromArray(json_decode($json, true), $workID);
        $visibleCreateFields = ProductFieldLayout::visibleFields(Option::quickAddFieldLayout());

        $dlsite_product_id = $workData->productId;
        [$work_name, $work_name_english] = $this->dlsiteCreateTitleValues($request, $validated, $visibleCreateFields, $workData);
        [$circle, $maker_id] = $this->dlsiteCreateCircleValues($request, $validated, $visibleCreateFields, $workData);

        $age_category = $this->dlsiteCreateTextOverride(
            $request,
            $validated,
            $visibleCreateFields,
            ProductField::AgeCategory,
            'age_category',
            $workData->ageCategory,
        );
        $work_image = "storage/Works/{$dlsite_product_id}/cover.jpg";
        $genre = $workData->japaneseGenres;
        $genre_english = $workData->englishGenres;
        $genre_custom = $this->createFieldVisible($visibleCreateFields, ProductField::Tags)
            ? ($validated['genre_custom'] ?? [])
            : [];

        [$description, $description_english] = $this->dlsiteCreateDescriptionValues(
            $request,
            $validated,
            $visibleCreateFields,
            $workData,
        );
        $notes = $this->createFieldSubmitted($request, $visibleCreateFields, ProductField::Notes, 'notes')
            ? ($validated['notes'] ?? null)
            : null;
        $series = $this->dlsiteCreateSeriesValue($request, $validated, $visibleCreateFields, $workData);
        $sample_images = $workData->sampleImages;
        $contributorsByRole = $this->dlsiteCreateContributorsByRole(
            $validated,
            $visibleCreateFields,
            $workData,
            $circle,
        );

        $data = [
            'id' => $dlsite_product_id,
            'maker_id' => $maker_id,
            'work_name' => $work_name,
            'work_name_english' => $work_name_english,
            'age_category' => $age_category,
            'circle' => $circle,
            'work_image' => $work_image,
            'description' => $description,
            'description_english' => $description_english,
            'notes' => $notes,
            'series' => $series,
            'sample_images' => $sample_images,
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
        ];

        $product = Product::create($data);
        $this->syncProductGenres($product, $genre, $genre_english, $genre_custom);
        $this->contributorSync->sync($product, $contributorsByRole, $maker_id);

        $returnTarget = ReturnTarget::fromRequest($request)
            ->forProduct($product);

        return redirect($returnTarget->toUrl());
    }

    public function store_custom(StoreCustomProductRequest $request)
    {
        $validated = $request->validated();
        $workID = $validated['id'];
        $visibleCreateFields = ProductFieldLayout::visibleFields(Option::customQuickAddFieldLayout());

        $work_image = $this->storeCustomCoverImage($request->file('work_image'), $workID);
        $sampleImages = $this->createFieldVisible($visibleCreateFields, ProductField::SampleImages)
            ? $this->storeCustomSampleImages($request, $workID)
            : [];
        [$description, $descriptionEnglish] = $this->customCreateDescriptionValues(
            $request,
            $validated,
            $visibleCreateFields,
        );
        [$circle, $makerId] = $this->customCreateCircleValues($request, $validated, $visibleCreateFields);

        $product = Product::create([
            'id' => $workID,
            'maker_id' => $makerId,
            'work_name' => $validated['work_name'],
            'work_name_english' => $validated['work_name_english'] ?? null,
            'age_category' => $validated['age_category'],
            'circle' => $circle,
            'work_image' => $work_image,
            'description' => $description,
            'description_english' => $descriptionEnglish,
            'notes' => $this->createFieldSubmitted($request, $visibleCreateFields, ProductField::Notes, 'notes')
                ? ($validated['notes'] ?? null)
                : null,
            'series' => $this->createFieldSubmitted($request, $visibleCreateFields, ProductField::Series, 'series')
                ? ($validated['series'] ?? null)
                : null,
            'sample_images' => $sampleImages,
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

        $this->syncProductCustomGenres(
            $product,
            $this->createFieldVisible($visibleCreateFields, ProductField::Tags)
                ? ($validated['genre_custom'] ?? [])
                : [],
        );
        $this->contributorSync->sync(
            $product,
            $this->customCreateContributorsByRole($validated, $visibleCreateFields, $circle),
            $makerId,
        );

        $returnTarget = ReturnTarget::fromRequest($request)
            ->forProduct($product);

        return redirect($returnTarget->toUrl());
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        dd('show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, string $id)
    {
        $product = Product::findOrFail($id);
        $editGenres = $this->loadEditGenresForProduct($product->getKey());
        $showReadonlyGenreColors = Option::tagColorSurfaceEnabled(Option::TAG_COLOR_SURFACE_EDIT_READONLY);
        $genreColorPairs = $showReadonlyGenreColors
            ? TagColor::effectiveColorPairsForGenreIds($editGenres->flatMap(fn(Collection $genres): Collection => $genres)->pluck('id'))
            : collect();
        $englishGenres = $this->editGenreDisplayRows(
            $editGenres->get(Genre::LANGUAGE_ENGLISH, collect()),
            $genreColorPairs,
        );
        $customGenres = $this->editGenreDisplayRows(
            $editGenres->get(Genre::PIVOT_SOURCE_CUSTOM, collect()),
            $genreColorPairs,
        );
        $returnTarget = ReturnTarget::fromRequest($request, $product->getKey());

        return view('Edit', [
            'product' => $product,
            'englishGenres' => $englishGenres,
            'customGenres' => $customGenres,
            'genreFetchedEnglishInput' => $this->formatGenreInput($englishGenres->pluck('title')->all()),
            'genreCustomInput' => $this->formatGenreInput($customGenres->pluck('title')->all()),
            'showReadonlyGenreColors' => $showReadonlyGenreColors,
            'editFields' => ProductFieldLayout::editFields(Option::editFieldLayout()),
            'contributorInputs' => $this->formatContributorInputs($product),
            'readonlyFieldValues' => $this->readonlyFieldValues($product),
            'returnQuery' => $returnTarget->query,
            'returnFragment' => $returnTarget->fragment,
            'returnUrl' => $returnTarget->toUrl(),
            'ageCategoryOptions' => ProductAgeCategory::options(),
            ...$this->buildDateFieldOptions(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, string $id)
    {
        $product = Product::findOrFail($id);

        $oldProgress = $product->progress;

        $data = $request->validated();

        $editFieldLayout = Option::editFieldLayout();
        $editableFields = ProductFieldLayout::editableFields($editFieldLayout);
        $product->fill($this->updatePayload($request, $data, $editableFields));
        $productFieldsChanged = $product->isDirty(self::VISIBILITY_AFFECTING_PRODUCT_FIELDS);
        $product->save();
        $contributorsChanged = $this->syncProductEditContributors($request, $product, $data, $editableFields);
        $genresChanged = ProductFieldLayout::visible($editFieldLayout, ProductField::Tags)
            ? $this->syncProductEditGenres($request, $product, $data, $editFieldLayout)
            : false;

        $returnTarget = ReturnTarget::fromRequest($request, $product->getKey());
        $newProgress = in_array(ProductField::Progress->value, $editableFields, true) && $request->wasSubmitted('progress')
            ? ($data['progress'] ?? null)
            : $oldProgress;

        if ($oldProgress !== $newProgress) {
            $returnTarget = $returnTarget->withIndexProgress($newProgress);
        }

        return redirect($returnTarget->forProduct(
            $product,
            visibilityMayHaveChanged: $productFieldsChanged || $genresChanged || $contributorsChanged,
        )->toUrl());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $returnTarget = ReturnTarget::fromRequest($request);
        $product = Product::find($id);

        // Missing records should be a safe no-op.
        if (! $product) {
            return redirect($returnTarget->toUrl());
        }

        $jsonPath = "Works/{$id}.json";
        $imageDirectory = "Works/{$id}";

        if (! Storage::disk('local')->delete($jsonPath)) {
            Log::warning('Unable to delete product scraper JSON.', [
                'product_id' => $id,
                'path' => $jsonPath,
            ]);
        }

        if (! Storage::disk('public')->deleteDirectory($imageDirectory)) {
            Log::warning('Unable to delete product image directory.', [
                'product_id' => $id,
                'path' => $imageDirectory,
            ]);
        }

        $product->delete();

        // Return to same page but no anchor (work is gone)
        return redirect($returnTarget->afterDeleting()->toUrl());
    }

    private function scrape(string $workID, DLSitePythonRunner $pythonRunner): void
    {
        $result = $pythonRunner->runScraper($workID);

        // Show any errors
        if ($result->failed()) {
            $stderr = trim($result->errorOutput());

            // Show error on the previos page
            if ($result->exitCode() === 2 && $stderr !== '') {
                throw ValidationException::withMessages([
                    'id' => $stderr,
                ]);
            }

            // Show error in Laravel
            $result->throw();
        }
    }

    private function formatGenreInput(?array $tags): string
    {
        return TagInput::format($tags ?? []);
    }

    private function formatContributorInputs(Product $product): array
    {
        return collect($this->contributorSync->namesByRole($product))
            ->map(fn(array $names): string => TagInput::format($names))
            ->all();
    }

    private function readonlyDescription(Product $product): ?string
    {
        return collect([$product->description, $product->description_english])
            ->filter(fn(mixed $value): bool => filled($value))
            ->join("\n\n") ?: null;
    }

    private function readonlyFieldValues(Product $product): array
    {
        return [
            ProductField::Description->value => $this->readonlyDescription($product),
            ProductField::Notes->value => $product->notes,
            ProductField::StartDate->value => $this->readonlyDate($product->start_date),
            ProductField::FinishDate->value => $this->readonlyDate($product->end_date),
            ProductField::TotalTimesReListened->value => $product->num_re_listen_times,
            ProductField::ReListenValue->value => ProductReListenValue::tryFrom((string) $product->re_listen_value)?->label(),
            ProductField::Priority->value => ProductPriority::tryFrom((string) $product->priority)?->label(),
        ];
    }

    private function readonlyDate(?array $date): ?string
    {
        return PartialDateFormatter::format($date);
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function dlsiteCreateTitleValues(
        StoreProductRequest $request,
        array $data,
        array $visibleFields,
        DLSiteWorkData $workData,
    ): array {
        if (! $this->createFieldVisible($visibleFields, ProductField::Title)) {
            return [$workData->workName, $workData->englishWorkName];
        }

        $workName = $request->wasSubmitted('work_name') && filled($data['work_name'] ?? null)
            ? $data['work_name']
            : $workData->workName;
        $englishWorkName = $request->wasSubmitted('work_name_english') && filled($data['work_name_english'] ?? null)
            ? $data['work_name_english']
            : $workData->englishWorkName;

        return [$workName, $englishWorkName === $workName ? null : $englishWorkName];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function dlsiteCreateCircleValues(
        StoreProductRequest $request,
        array $data,
        array $visibleFields,
        DLSiteWorkData $workData,
    ): array {
        $circle = $this->dlsiteCreateTextOverride(
            $request,
            $data,
            $visibleFields,
            ProductField::Circle,
            'circle',
            $workData->circle,
        );
        $makerId = $this->dlsiteCreateTextOverride(
            $request,
            $data,
            $visibleFields,
            ProductField::Circle,
            'maker_id',
            $workData->makerId,
        );

        return [$circle, $makerId];
    }

    private function dlsiteCreateTextOverride(
        StoreProductRequest $request,
        array $data,
        array $visibleFields,
        ProductField $field,
        string $key,
        ?string $default,
    ): ?string {
        if (! $this->createFieldSubmitted($request, $visibleFields, $field, $key)) {
            return $default;
        }

        return filled($data[$key] ?? null) ? $data[$key] : $default;
    }

    private function dlsiteCreateSeriesValue(
        StoreProductRequest $request,
        array $data,
        array $visibleFields,
        DLSiteWorkData $workData,
    ): ?string {
        if (
            $this->createFieldSubmitted($request, $visibleFields, ProductField::Series, 'series')
            && filled($data['series'] ?? null)
        ) {
            return $data['series'];
        }

        return Option::autoSeriesFromTitleName() ? $workData->autoSeries() : null;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function dlsiteCreateDescriptionValues(
        StoreProductRequest $request,
        array $data,
        array $visibleFields,
        DLSiteWorkData $workData,
    ): array {
        $description = $this->dlsiteCreateTextOverride(
            $request,
            $data,
            $visibleFields,
            ProductField::Description,
            'description',
            $workData->description,
        );
        $englishDescription = $this->dlsiteCreateTextOverride(
            $request,
            $data,
            $visibleFields,
            ProductField::Description,
            'description_english',
            $workData->englishDescription,
        );

        return [
            $description,
            $englishDescription === $description ? null : $englishDescription,
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function dlsiteCreateContributorsByRole(
        array $data,
        array $visibleFields,
        DLSiteWorkData $workData,
        ?string $circle,
    ): array {
        $contributors = $workData->contributorsByRole;
        $contributors[ProductContributorRole::Circle->value] = filled($circle) ? [$circle] : [];

        foreach (ProductContributorRole::cases() as $role) {
            if ($role === ProductContributorRole::Circle) {
                continue;
            }

            $field = $role->productField();

            if ($this->createFieldVisible($visibleFields, $field) && ($data[$role->value] ?? []) !== []) {
                $contributors[$role->value] = array_values($data[$role->value]);
            }
        }

        return $contributors;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
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
        if (! $this->createFieldVisible($visibleFields, ProductField::Description)) {
            return [null, null];
        }

        $description = $request->wasSubmitted('description') ? ($data['description'] ?? null) : null;
        $englishDescription = $request->wasSubmitted('description_english')
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
            && $request->wasAnySubmitted($submittedKeys);
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
        $returnUrl = $request->has('return_url')
            ? $request->input('return_url')
            : $request->old('return_url');
        $returnUrl = is_scalar($returnUrl) ? trim((string) $returnUrl) : '';

        // Keep the original back target when switching between DLSite and Custom create.
        if ($returnUrl === '') {
            $returnUrl = URL::previous(route('index'));
        }

        $returnParameters = ['return_url' => $returnUrl];

        if ($returnTarget->query !== []) {
            $returnParameters['return_query'] = $returnTarget->query;
        }

        return view('Create', [
            'isCustomCreate' => $isCustomCreate,
            'quickAddFields' => $isCustomCreate
                ? ProductFieldLayout::customQuickAddFields(Option::customQuickAddFieldLayout())
                : ProductFieldLayout::quickAddFields(Option::quickAddFieldLayout()),
            'returnQuery' => $returnTarget->query,
            'returnUrl' => $returnUrl,
            'returnParameters' => $returnParameters,
            'ageCategoryOptions' => ProductAgeCategory::options(),
            ...$this->buildDateFieldOptions(),
        ]);
    }

    private function storeCustomCoverImage(UploadedFile $file, string $workID): string
    {
        $path = "Works/{$workID}/cover.{$file->extension()}";

        Storage::disk('public')->putFileAs("Works/{$workID}", $file, basename($path));

        return "storage/{$path}";
    }

    private function storeCustomSampleImages(Request $request, string $workID): array
    {
        return collect($request->file('sample_images', []))
            ->values()
            ->map(function (UploadedFile $file, int $index) use ($workID): string {
                $path = "Works/{$workID}/sample_" . ($index + 1) . '.' . $file->extension();

                Storage::disk('public')->putFileAs("Works/{$workID}", $file, basename($path));

                return "storage/{$path}";
            })
            ->all();
    }

    private function updatePayload(UpdateProductRequest $request, array $data, array $editableFields): array
    {
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

        foreach ($this->updatePayloadFieldMap() as $field => $submittedColumns) {
            if (
                ! in_array($field, $editableFields, true)
                || ! $request->wasAnySubmitted(array_keys($submittedColumns))
            ) {
                continue;
            }

            $payload = [
                ...$payload,
                ...$this->updatePayloadForField($field, $data, $submittedColumns),
            ];
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
            ProductField::Circle->value => [
                'circle' => 'circle',
                'maker_id' => 'maker_id',
            ],
            ProductField::Description->value => ['description' => 'description', 'description_english' => 'description_english'],
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
    private function updatePayloadForField(string $field, array $data, array $submittedColumns): array
    {
        if ($field === ProductField::Description->value) {
            return $this->descriptionUpdatePayload($data);
        }

        return collect($submittedColumns)
            ->mapWithKeys(fn(string $column): array => [$column => $data[$column] ?? null])
            ->all();
    }

    private function descriptionUpdatePayload(array $data): array
    {
        return [
            'description' => $data['description'] ?? null,
            'description_english' => ($data['description_english'] ?? null) === ($data['description'] ?? null)
                ? null
                : ($data['description_english'] ?? null),
        ];
    }

    private function syncProductEditContributors(
        UpdateProductRequest $request,
        Product $product,
        array $data,
        array $editableFields,
    ): bool {
        $changed = false;
        $currentNamesByRole = $this->contributorSync->namesByRole($product);

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
            $currentNames = $currentNamesByRole[$role->value] ?? [];

            if ($this->normalizedNameList($newNames) !== $this->normalizedNameList($currentNames)) {
                $changed = true;
            }

            $this->contributorSync->syncRole(
                $product,
                $role,
                $newNames,
                $role === ProductContributorRole::Circle ? $product->maker_id : null,
            );
        }

        return $changed;
    }

    private function normalizedNameList(array $names): array
    {
        return collect($names)
            ->map(fn(mixed $name): string => mb_convert_case(trim((string) $name), MB_CASE_FOLD, 'UTF-8'))
            ->filter()
            ->sort()
            ->values()
            ->all();
    }

    private function buildDateFieldOptions(): array
    {
        return [
            'monthLabels' => collect(range(1, 12))
                ->mapWithKeys(fn($month) => [
                    $month => Carbon::create(2000, $month, 1)->translatedFormat('M'),
                ])
                ->all(),
            'days' => range(1, 31),
            'years' => range(now()->year, 1995),
        ];
    }

    private function loadEditGenresForProduct(string $productId): Collection
    {
        // Edit separates readonly fetched genres from user-editable custom entries by pivot source.
        return DB::table('genre_product')
            ->join('genres', 'genres.id', '=', 'genre_product.genre_id')
            ->where('genre_product.product_id', $productId)
            ->where(VisibleGenreAttachment::query())
            ->orderBy('genres.title')
            ->get([
                'genres.id',
                'genres.title',
                'genre_product.source',
            ])
            ->groupBy(fn(object $genre): string => $genre->source === Genre::PIVOT_SOURCE_CUSTOM
                ? Genre::PIVOT_SOURCE_CUSTOM
                : Genre::LANGUAGE_ENGLISH);
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

    private function syncProductCustomGenres(Product $product, array $customTitles): bool
    {
        return $this->genreSync->syncCustom($product, Genre::resolveIdsFromTitles($customTitles));
    }

    private function syncProductEditGenres(
        UpdateProductRequest $request,
        Product $product,
        array $data,
        array $editFieldLayout,
    ): bool {
        $customGenreIds = null;
        $englishFetchedGenreIds = null;

        if (
            ProductFieldLayout::editable($editFieldLayout, ProductField::Tags)
            && $request->wasSubmitted('genre_custom')
        ) {
            $customGenreIds = Genre::resolveIdsFromTitles($data['genre_custom'] ?? []);
        }

        if (
            ProductFieldLayout::fetchedTagsEditable($editFieldLayout)
            && $request->wasSubmitted('genre_fetched_english')
        ) {
            $englishFetchedGenreIds = Genre::resolveIdsFromTitles($data['genre_fetched_english'] ?? []);
        }

        if ($customGenreIds === null && $englishFetchedGenreIds === null) {
            return false;
        }

        return $this->genreSync->syncEditableTagBuckets(
            $product,
            $englishFetchedGenreIds,
            $customGenreIds,
        );
    }
}
