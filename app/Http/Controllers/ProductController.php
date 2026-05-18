<?php

namespace App\Http\Controllers;

use App\Enums\ProductAgeCategory;
use App\Http\Requests\StoreCustomProductRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Genre;
use App\Models\Product;
use App\Support\ReturnTarget;
use App\Support\TagInput;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
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
        $genres = Genre::query()
            ->withCount('products')
            ->whereIn('type', [
                Genre::TYPE_AUTO_GENERATED_ENGLISH,
                Genre::TYPE_CUSTOM,
            ])
            ->orderBy('title')
            ->get(['id', 'title', 'type']);

        return view('TagLibrary', [
            'genres' => $genres,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request)
    {
        $validated = $request->validated();

        // Get RJ Code
        $workID = $validated['id'];

        //Get work info from DLSite
        $this->Scrape($workID);

        //Get JSON info
        $extractedWorkDataPath = storage_path('app/Works/' . $workID);
        $json = File::get("$extractedWorkDataPath.json");
        $workData = json_decode($json, true);

        $dlsite_product_id = $workData['japanese']['product_id'];
        $maker_id = $workData['japanese']['maker_id'];
        $work_name = $workData['japanese']['work_name'];

        //If user isn't specified english title
        if ($request->work_name_english == null) {
            $work_name_english = $workData['english']['work_name'];
            if ($work_name == $work_name_english) {
                $work_name_english = null;
            }
        } else {
            $work_name_english = $request->work_name_english;
        }

        //If user passed work name - store it instead
        if ($request->work_name != null) {
            $work_name = $request->work_name;
        }

        $age_category = $workData['japanese']['age_category']['_name_'];
        $circle = $workData['japanese']['circle'];

        $work_image = "storage/Works/{$dlsite_product_id}/cover.jpg";
        if (!empty($workData['japanese']['sample_images'])) {
            foreach ($workData['japanese']['sample_images'] as $index => $img) {
                $sample_images[] = "storage/Works/{$dlsite_product_id}/sample_" . ($index + 1) . ".jpg";
            }
        }

        $genre = $workData['japanese']['genre'];
        $genre_english = $workData['english']['genre'];
        $genre_custom = $validated['genre_custom'] ?? [];

        $description = $workData['japanese']['description'];
        $description_english = $workData['english']['description'];
        $notes = $validated['notes'] ?? null;
        $series = $validated['series'] ?? null;
        $sample_images = $workData['japanese']['sample_images'];

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
            'sample_images' => json_encode($sample_images),
            'score' => $validated['score'] ?? null,
            'progress' => $validated['progress'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'num_re_listen_times' => $validated['num_re_listen_times'] ?? null,
            're_listen_value' => $validated['re_listen_value'] ?? null,
            'priority' => $validated['priority'] ?? null,
        ];

        $product = Product::create($data);
        $this->syncProductGenres($product, $genre, $genre_english, $genre_custom);

        $returnTarget = ReturnTarget::fromRequest($request)
            ->forProduct($product);

        return redirect($returnTarget->toUrl());
    }

    public function store_custom(StoreCustomProductRequest $request)
    {
        $validated = $request->validated();
        $workID = $validated['id'];

        $work_image = $this->storeCustomCoverImage($request->file('work_image'), $workID);
        $sampleImages = $this->storeCustomSampleImages($request, $workID);

        $product = Product::create([
            'id' => $workID,
            'maker_id' => null,
            'work_name' => $validated['work_name'],
            'work_name_english' => $validated['work_name_english'] ?? null,
            'age_category' => $validated['age_category'],
            'circle' => null,
            'work_image' => $work_image,
            'description' => null,
            'description_english' => null,
            'notes' => $validated['notes'] ?? null,
            'series' => $validated['series'] ?? null,
            'sample_images' => json_encode($sampleImages),
            'score' => $validated['score'] ?? null,
            'progress' => $validated['progress'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'num_re_listen_times' => $validated['num_re_listen_times'] ?? null,
            're_listen_value' => $validated['re_listen_value'] ?? null,
            'priority' => $validated['priority'] ?? null,
        ]);

        $this->syncProductCustomGenres($product, $validated['genre_custom'] ?? []);

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
        $returnTarget = ReturnTarget::fromRequest($request, $product->getKey());

        return view('Edit', [
            'product' => $product,
            'englishGenres' => $editGenres->get(Genre::TYPE_AUTO_GENERATED_ENGLISH, collect()),
            'genreCustomInput' => $this->formatGenreCustomForInput(
                $editGenres->get(Genre::TYPE_CUSTOM, collect())->pluck('title')->all()
            ),
            'returnQuery' => $returnTarget->query,
            'returnFragment' => $returnTarget->fragment,
            'returnUrl' => $returnTarget->toUrl(),
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

        $product->fill([
            'progress' => $data['progress'] ?? null,
            'score' => $data['score'] ?? null,
            'series' => $data['series'] ?? null,
            'work_name' => $data['work_name'],
            'work_name_english' => $data['work_name_english'] ?? null,
            'notes' => $data['notes'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'num_re_listen_times' => $data['num_re_listen_times'] ?? null,
            're_listen_value' => $data['re_listen_value'] ?? null,
            'priority' => $data['priority'] ?? null,
        ]);
        $product->save();
        $this->syncProductCustomGenres($product, $data['genre_custom'] ?? []);

        $returnTarget = ReturnTarget::fromRequest($request, $product->getKey());
        $newProgress = $data['progress'] ?? null;

        if ($oldProgress !== $newProgress) {
            $returnTarget = $returnTarget->withIndexProgress($newProgress);
        }

        return redirect($returnTarget->forProduct($product)->toUrl());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $returnTarget = ReturnTarget::fromRequest($request);
        $product = Product::find($id);

        // Missing records should be a safe no-op.
        if (!$product) {
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

    private function Scrape(string $workID)
    {
        $storageDir = storage_path();

        // Run DLSiteScraper download
        $path = base_path('python/DLSiteScraper.py');

        // Run the venv's Python (Windows vs Linux/macOS)
        $pythonExe = base_path(
            PHP_OS_FAMILY === 'Windows'
                ? 'python/venv/Scripts/python.exe'
                : 'python/venv/bin/python'
        );

        $process = new Process([$pythonExe, $path, $storageDir, $workID]);

        // Set infinite timeout
        $process->setTimeout(0);
        $process->run();

        // Show any errors
        if (!$process->isSuccessful()) {
            $stderr = trim($process->getErrorOutput());

            // Show error on the previos page
            if ($process->getExitCode() === 2 && $stderr !== '') {
                throw ValidationException::withMessages([
                    'id' => $stderr,
                ]);
            }

            // Show error in Laravel
            throw new ProcessFailedException($process);
        }
    }

    private function formatGenreCustomForInput(?array $tags): string
    {
        return TagInput::format($tags ?? []);
    }

    private function createView(Request $request, bool $isCustomCreate)
    {
        $returnTarget = ReturnTarget::fromRequest($request);
        $returnUrl = $request->input('return_url');
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
            ->where(function ($query): void {
                $query->where('genre_product.source', Genre::PIVOT_SOURCE_CUSTOM)
                    ->orWhere('genres.type', Genre::TYPE_AUTO_GENERATED_ENGLISH);
            })
            ->orderBy('genres.title')
            ->get([
                'genres.title',
                'genres.type',
                'genre_product.source',
            ])
            ->groupBy(fn(object $genre): string => $genre->source === Genre::PIVOT_SOURCE_CUSTOM
                ? Genre::TYPE_CUSTOM
                : $genre->type);
    }

    private function syncProductGenres(Product $product, array $japaneseTitles, array $englishTitles, array $customTitles): void
    {
        $fetchedGenreIds = array_merge(
            Genre::resolveIdsFromTitles($japaneseTitles, Genre::TYPE_AUTO_GENERATED_JAPANESE, Genre::LANGUAGE_JAPANESE),
            Genre::resolveIdsFromTitles($englishTitles, Genre::TYPE_AUTO_GENERATED_ENGLISH, Genre::LANGUAGE_ENGLISH),
        );
        $customGenreIds = Genre::resolveIdsFromTitles($customTitles, Genre::TYPE_CUSTOM, Genre::LANGUAGE_ENGLISH);

        $product->genres()->sync($this->genreSyncPayload($fetchedGenreIds, $customGenreIds));
    }

    private function syncProductCustomGenres(Product $product, array $customTitles): void
    {
        $fetchedGenreIds = DB::table('genre_product')
            ->where('product_id', $product->getKey())
            ->where('source', Genre::PIVOT_SOURCE_FETCHED)
            ->pluck('genre_id')
            ->all();

        $customGenreIds = Genre::resolveIdsFromTitles(
            $customTitles,
            Genre::TYPE_CUSTOM,
            Genre::LANGUAGE_ENGLISH
        );

        $product->genres()->sync($this->genreSyncPayload($fetchedGenreIds, $customGenreIds));
    }

    private function genreSyncPayload(array $fetchedGenreIds, array $customGenreIds): array
    {
        $payload = [];

        foreach (array_unique($fetchedGenreIds) as $genreId) {
            $payload[$genreId] = ['source' => Genre::PIVOT_SOURCE_FETCHED];
        }

        foreach (array_unique($customGenreIds) as $genreId) {
            $payload[$genreId] ??= ['source' => Genre::PIVOT_SOURCE_CUSTOM];
        }

        return $payload;
    }
}
