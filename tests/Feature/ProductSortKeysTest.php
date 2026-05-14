<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSortKeysTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_save_derives_rj_and_partial_date_sort_keys(): void
    {
        $product = Product::factory()->create([
            'id' => 'RJ000000010',
            'start_date' => ['year' => 2025, 'month' => null, 'day' => null],
            'end_date' => ['year' => 2026, 'month' => '03', 'day' => '04'],
        ]);

        $product->refresh();

        $this->assertSame(10, $product->rj_number);
        $this->assertSame(20250000, $product->start_date_sort);
        $this->assertSame(20260304, $product->end_date_sort);

        $this->assertDatabaseHas('products', [
            'id' => 'RJ000000010',
            'rj_number' => 10,
            'start_date_sort' => 20250000,
            'end_date_sort' => 20260304,
        ]);
    }

    public function test_product_save_refreshes_and_clears_date_sort_keys(): void
    {
        $product = Product::factory()->create([
            'id' => 'RJ000000020',
            'start_date' => ['year' => 2025, 'month' => '03', 'day' => '04'],
            'end_date' => ['year' => 2025, 'month' => '03', 'day' => null],
        ]);

        $product->forceFill([
            'start_date' => null,
            'end_date' => null,
        ])->save();

        $product->refresh();

        $this->assertNull($product->start_date_sort);
        $this->assertNull($product->end_date_sort);
    }

    public function test_numeric_rj_scope_uses_stored_sort_key(): void
    {
        Product::factory()->create(['id' => 'RJ000000002']);
        Product::factory()->create(['id' => 'RJ000000010']);
        Product::factory()->create(['id' => 'RJ000000001']);

        $this->assertSame(
            ['RJ000000010', 'RJ000000002', 'RJ000000001'],
            Product::query()->orderByNumericRj()->pluck('id')->all(),
        );

        $this->assertSame(
            ['RJ000000001', 'RJ000000002', 'RJ000000010'],
            Product::query()->orderByNumericRj('asc')->pluck('id')->all(),
        );
    }

    public function test_series_filter_scope_uses_exact_series_match(): void
    {
        Product::factory()->create([
            'id' => 'RJ000000030',
            'series' => 'Shared Series',
        ]);
        Product::factory()->create([
            'id' => 'RJ000000031',
            'series' => 'Shared Series Extended',
        ]);

        $this->assertSame(
            ['RJ000000030'],
            Product::query()->filterSeries('Shared Series')->pluck('id')->all(),
        );
    }
}
