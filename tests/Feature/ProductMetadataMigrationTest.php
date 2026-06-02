<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductMetadataMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_skips_missing_json(): void
    {
        Storage::fake('local');

        $product = Product::factory()->create([
            'id' => 'RJ999999',
            'circle' => 'Existing Circle',
        ]);

        (require database_path('migrations/2026_05_30_000002_backfill_product_metadata_from_storage.php'))->up();

        $this->assertSame('Existing Circle', $product->refresh()->circle);
    }

    public function test_backfill_skips_invalid_json(): void
    {
        Storage::fake('local');

        $product = Product::factory()->create([
            'id' => 'RJ888888',
            'description' => 'Existing Description',
        ]);
        Storage::disk('local')->put('Works/RJ888888.json', '{not json');

        (require database_path('migrations/2026_05_30_000002_backfill_product_metadata_from_storage.php'))->up();

        $this->assertSame('Existing Description', $product->refresh()->description);
    }
}
