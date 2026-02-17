<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Requests\StoreProductRequest;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Define allowed values
        $allowedAgeCategory = ['ALL_AGES', 'R15', 'R18'];
        $allowedProgress = ['Listening', 'Completed', 'Plan to Listen'];

        // Start a query builder instead of immediately fetching all products
        $query = Product::query();
        // --- Filter by age (if given) ---
        if ($request->has('age_category') && in_array($request->age_category, $allowedAgeCategory)) {
            $query->where('age_category', $request->age_category);
        }

        // --- Filter by progress ---
        if ($request->has('progress') && in_array($request->progress, $allowedProgress)) {
            $query->where('progress', $request->progress);

            $progress = $request->progress;
        } else {
            // Better text for the page
            $progress = 'All ASMR';
        }

        // --- Filter by genre (search in both english + custom) ---
        if ($request->has('genre') && $request->genre !== null) {
            $genre = $request->genre;

            $query->where(function ($q) use ($genre) {
                $q->whereJsonContains('genre_english', $genre)
                    ->orWhereJsonContains('genre_custom', $genre);
            });
        }

        // --- Filter by series ---
        if ($request->has('series') && $request->series !== null) {
            $query->where('series', $request->series);
        }

        // --- Search by title / series / tags / RJ ---
        if ($request->filled('search')) {
            $search = mb_strtolower($request->search);

            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(id) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(work_name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(work_name_english) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(series) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(genre) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(genre_english) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(genre_custom) LIKE ?', ["%{$search}%"]);
            });
        }

        // Get products
        $products = $query->get();
        //Sort by id (Biggest number first)
        $products = $products->sortByDesc(function ($item) {
            // Remove "RJ" prefix and cast to integer
            return (int) substr($item->id, 2);
        })->values();

        return view('Index', ['products' => $products, 'progress' => $progress]);
    }

    public function create()
    {
        $monthLabels = collect(range(1, 12))
            ->mapWithKeys(fn($month) => [
                $month => Carbon::create(2000, $month, 1)->translatedFormat('M'),
            ])
            ->all();
        $years = range(now()->year, 1995);
        $days = range(1, 31);

        return view('Create', [
            'monthLabels' => $monthLabels,
            'days' => $days,
            'years' => $years,
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

        $data = array(
            'id' => $dlsite_product_id,
            'maker_id' => $maker_id,
            'work_name' => $work_name,
            'work_name_english' => $work_name_english,
            'age_category' => $age_category,
            'circle' => $circle,
            'work_image' => $work_image,
            'genre' => json_encode($genre),
            'genre_english' => json_encode($genre_english),
            'genre_custom' => $genre_custom,
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
        );

        Product::create($data);

        // Build redirect target
        $redirectUrl = $request->input('redirect', '/');

        return redirect($redirectUrl . '#' . $dlsite_product_id);
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

        $monthLabels = collect(range(1, 12))
            ->mapWithKeys(fn($month) => [
                $month => Carbon::create(2000, $month, 1)->translatedFormat('M'),
            ])
            ->all();
        $years = range(now()->year, 1995);
        $days = range(1, 31);

        return view('Edit', [
            'product' => $product,
            'genreCustomInput' => $this->formatGenreCustomForInput($product->genre_custom),
            'redirect' => $request->input('redirect', '/'),
            'monthLabels' => $monthLabels,
            'days' => $days,
            'years' => $years,
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
            'genre_custom' => $data['genre_custom'] ?? [],
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

        $redirect = $request->input('redirect', '/');

        // only replace ?progress= if value changed
        if ($oldProgress !== $request->progress) {
            $redirect = preg_replace('/([?&])progress=[^&]*/', '', $redirect);
            $redirect .= (str_contains($redirect, '?') ? '&' : '?') . 'progress=' . urlencode($request->progress);
        }

        return redirect($redirect . "#{$id}");
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $redirect = $request->input('redirect', '/');
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
        if (empty($tags)) {
            return '';
        }

        return collect($tags)
            ->map(fn($tag) => $this->quoteCsvTag((string) $tag))
            ->implode(', ');
    }

    private function quoteCsvTag(string $tag): string
    {
        $needsQuotes = str_contains($tag, ',')
            || str_contains($tag, '"')
            || str_contains($tag, "\n")
            || str_contains($tag, "\r");

        if (!$needsQuotes) {
            return $tag;
        }

        return '"' . str_replace('"', '""', $tag) . '"';
    }
}
