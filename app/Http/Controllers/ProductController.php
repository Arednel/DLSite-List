<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductIndexRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Genre;
use App\Models\Product;
use App\Support\ProductIndexFilters;
use App\Support\ProductIndexResults;
use App\Support\ReturnTarget;
use App\Support\TagInput;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(ProductIndexRequest $request, ProductIndexResults $productIndexResults)
    {
        $filters = $request->filters();
        $products = $productIndexResults->getProducts($filters);

        return view('Index', [
            'products' => $products,
            'productGenres' => $productIndexResults->loadVisibleGenres($products->modelKeys()),
            'filters' => $filters,
            'filterOptions' => ProductIndexFilters::optionSets(),
            'progress' => $filters->progressHeading(),
            'filterQuery' => $filters->toQuery(),
            'allProgressQuery' => $filters->toQueryWithout(['progress', 'genre']),
            'searchFormQuery' => $filters->toQueryWithout('search'),
        ]);
    }

    public function create(Request $request)
    {
        $returnTarget = ReturnTarget::fromRequest($request);

        return view('Create', [
            'returnRoute' => $returnTarget->route,
            'returnQuery' => $returnTarget->query,
            'returnUrl' => $returnTarget->toUrl(),
            ...$this->buildDateFieldOptions(),
        ]);
    }

    public function tagLibrary()
    {
        $genres = Genre::query()
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
            ->withFragment($dlsite_product_id);

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
            'returnRoute' => $returnTarget->route,
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

        return redirect($returnTarget->toUrl());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $redirect = ReturnTarget::fromRequest($request)->toUrl();
        $product = Product::find($id);

        // Missing records should be a safe no-op.
        if (!$product) {
            return redirect($redirect);
        }

        // Delete the product from DB
        $product->delete();

        // Remove JSON file
        $jsonPath = "app/Works/{$id}.json";
        $storageJsonPath = storage_path($jsonPath);
        if (file_exists($storageJsonPath)) {
            unlink($storageJsonPath);
        }

        // Remove images directory in storage/app/public/Works/{id}
        Storage::disk('public')->deleteDirectory("Works/{$id}");

        // Return to same page but no anchor (work is gone)
        return redirect($redirect);
    }

    private function Scrape($workID)
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
        // Edit only renders fetched EN genres and the custom-tag input.
        // Load just those rows instead of hydrating every genre relationship.
        return DB::table('genre_product')
            ->join('genres', 'genres.id', '=', 'genre_product.genre_id')
            ->where('genre_product.product_id', $productId)
            ->whereIn('genres.type', [
                Genre::TYPE_AUTO_GENERATED_ENGLISH,
                Genre::TYPE_CUSTOM,
            ])
            ->orderBy('genres.title')
            ->get([
                'genres.title',
                'genres.type',
            ])
            ->groupBy('type');
    }

    private function syncProductGenres(Product $product, array $japaneseTitles, array $englishTitles, array $customTitles): void
    {
        $genreIds = array_merge(
            Genre::resolveIdsFromTitles($japaneseTitles, Genre::TYPE_AUTO_GENERATED_JAPANESE, Genre::LANGUAGE_JAPANESE),
            Genre::resolveIdsFromTitles($englishTitles, Genre::TYPE_AUTO_GENERATED_ENGLISH, Genre::LANGUAGE_ENGLISH),
            Genre::resolveIdsFromTitles($customTitles, Genre::TYPE_CUSTOM, Genre::LANGUAGE_ENGLISH),
        );

        $product->genres()->sync(array_values(array_unique($genreIds)));
    }

    private function syncProductCustomGenres(Product $product, array $customTitles): void
    {
        $nonCustomGenreIds = $product->genres()
            ->where('genres.type', '!=', Genre::TYPE_CUSTOM)
            ->pluck('genres.id')
            ->all();

        // User-added titles can reuse existing fetched genres by title.
        // Only brand new titles stay as custom genres.
        $customGenreIds = Genre::resolveIdsFromTitles(
            $customTitles,
            Genre::TYPE_CUSTOM,
            Genre::LANGUAGE_ENGLISH
        );

        $product->genres()->sync(array_values(array_unique(array_merge(
            $nonCustomGenreIds,
            $customGenreIds,
        ))));
    }
}
