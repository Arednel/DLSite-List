<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_cleans_every_existing_rj_folder_without_touching_unrelated_files(): void
    {
        Storage::fake('public');
        $disk = Storage::disk('public');
        $first = Product::factory()->create([
            'id' => 'RJ000000001',
            'work_image' => 'storage/Works/RJ000000001/cover.png',
            'sample_images' => ['storage/Works/RJ000000001/sample_1.jpg'],
        ]);
        $second = Product::factory()->create([
            'id' => 'RJ000000002',
            'work_image' => 'storage/Works/RJ000000002/cover.jpg',
            'sample_images' => [],
        ]);

        $disk->put("Works/{$first->id}/cover.png", 'saved-cover');
        $disk->put("Works/{$first->id}/sample_1.jpg", 'saved-sample');
        $disk->put("Works/{$first->id}/cover.jpg", 'old-cover');
        $disk->put("Works/{$first->id}/sample_2.jpg", 'old-sample');
        $disk->put("Works/{$first->id}/reference.jpg", 'unrelated-image');
        $disk->put("Works/{$first->id}/notes.txt", 'unrelated-file');
        $disk->put("Works/{$first->id}/archive/sample_3.jpg", 'nested-image');
        $disk->put("Works/{$second->id}/cover.jpg", 'saved-cover');
        $disk->put("Works/{$second->id}/sample_4.webp", 'old-sample');
        $disk->put('Works/RJ999999999/sample_1.jpg', 'orphan-image');
        $disk->put('Works/Other/sample_1.jpg', 'non-rj-image');

        $this->artisan('works:cleanup-images')
            ->expectsOutput(
                'Cleanup complete: processed 2 RJ work folder(s); '
                    . 'removed 3 unreferenced image(s).'
            )
            ->expectsOutput(
                'Skipped 1 RJ work folder(s) without a matching product.'
            )
            ->assertExitCode(0);

        $this->assertFileExists($disk->path("Works/{$first->id}/cover.png"));
        $this->assertFileExists($disk->path("Works/{$first->id}/sample_1.jpg"));
        $this->assertFileDoesNotExist($disk->path("Works/{$first->id}/cover.jpg"));
        $this->assertFileDoesNotExist($disk->path("Works/{$first->id}/sample_2.jpg"));
        $this->assertFileExists($disk->path("Works/{$first->id}/reference.jpg"));
        $this->assertFileExists($disk->path("Works/{$first->id}/notes.txt"));
        $this->assertFileExists($disk->path("Works/{$first->id}/archive/sample_3.jpg"));
        $this->assertFileExists($disk->path("Works/{$second->id}/cover.jpg"));
        $this->assertFileDoesNotExist($disk->path("Works/{$second->id}/sample_4.webp"));
        $this->assertFileExists($disk->path('Works/RJ999999999/sample_1.jpg'));
        $this->assertFileExists($disk->path('Works/Other/sample_1.jpg'));
    }
}
