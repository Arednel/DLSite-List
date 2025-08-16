<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Define allowed values
        $allowedProgress = ['Listening', 'Completed', 'Plan to Listen'];

        // Start a query builder instead of immediately fetching all products
        $query = Product::query();

        // --- Filter by progress ---
        $progress = $request->query('progress');
        if (in_array($progress, $allowedProgress)) {
            $query->where('progress', $progress);
        } else {
            $progress = 'All ASMR';
        }

        // --- Filter by age (if given) ---
        if ($request->has('age_category') && $request->age_category !== '') {
            $query->where('age_category', $request->age_category);
        }

        // --- Filter by genre (search in both english + custom) ---
        if ($request->has('genre') && $request->genre !== '') {
            $genre = $request->genre;

            $query->where(function ($q) use ($genre) {
                $q->whereJsonContains('genre_english', $genre)
                    ->orWhereJsonContains('genre_custom', $genre);
            });
        }

        // --- Filter by custom genre (if given) ---
        if ($request->has('genre_custom') && $request->genre_custom !== '') {
            $query->whereJsonContains('genre_custom', $request->genre_custom);
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
        return view('Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //  Return back if null
        if ($request->id == null) {
            return redirect('/');
        }

        // Get RJ Code
        if (preg_match('/RJ\d+/', $request->id, $matches)) {
            $workID = $matches[0];
        }

        // Check if already exists
        if (Product::where('id', $workID)->exists()) {
            return redirect('/');
        }

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

        if ($request->genre_custom == null) {
            $genre_custom = '[]';
        } else {
            $genre_custom_input = $request->input('genre_custom'); // e.g. "tag1, tag2, , tag3"
            $genre_custom_array = array_filter(array_map('trim', explode(',', $genre_custom_input)));
            $genre_custom = json_encode($genre_custom_array);
        }

        $description = $workData['japanese']['description'];
        $description_english = $workData['english']['description'];
        $notes = $request->notes;
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
            'sample_images' => json_encode($sample_images),
            'score' => $request->score,
            'progress' => $request->progress,
            'created_at' => now(),
        );

        Product::insert($data);

        return redirect('/');
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
    public function edit(string $id)
    {
        $product = Product::where('id', $id)->first();

        return view('Edit', ['product' => $product]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {

        $genre_custom_input = $request->input('genre_custom'); // e.g. "tag1, tag2, , tag3"
        $genre_custom_array = array_filter(array_map('trim', explode(',', $genre_custom_input)));

        Product::where('id', $id)->update([
            'progress' => $request->progress,
            'score' => $request->score,
            'genre_custom' => json_encode($genre_custom_array),
            'work_name_english' => $request->work_name_english,
            'notes' => $request->notes,
        ]);

        return redirect('/');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        // Delete the product from DB
        Product::where('id', $id)->delete();

        // Remove JSON file
        $jsonPath = "app/Works/{$id}.json";
        $storageJsonPath = storage_path($jsonPath);
        if (file_exists($storageJsonPath)) {
            unlink($storageJsonPath);
        }

        // Remove images directory in storage/app/public/Works/{id}
        Storage::disk('public')->deleteDirectory("Works/{$id}");

        return redirect('/');
    }

    private function Scrape($workID)
    {
        $storageDir = storage_path();

        // Run DLSiteScraper download
        $path = base_path('python/DLSiteScraper.py');
        $modulesPath = base_path('python\modules');

        $process = new Process(['py', $path, $modulesPath, $storageDir, $workID]);

        // Set infinite timeout
        $process->setTimeout(0);
        $process->run();

        // Show any errors
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }
}
