<?php

namespace Tests\Feature;

use App\Models\Option;
use App\Models\Product;
use App\Support\ProductIndexFilters;
use App\Support\ProductIndexResults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSortKeysTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_save_derives_code_and_partial_date_sort_keys(): void
    {
        $product = Product::factory()->create([
            'id' => 'RJ000000010',
            'start_date' => ['year' => 2025, 'month' => null, 'day' => null],
            'end_date' => ['year' => 2026, 'month' => '03', 'day' => '04'],
        ]);

        $product->refresh();

        $this->assertSame(10, $product->code_number);
        $this->assertSame(20250000, $product->start_date_sort);
        $this->assertSame(20260304, $product->end_date_sort);

        $this->assertDatabaseHas('products', [
            'id' => 'RJ000000010',
            'code_number' => 10,
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

    public function test_numeric_code_extraction_supports_only_the_planned_prefixes(): void
    {
        foreach (['RJ000000010', 'BJ000000010', 'VJ000000010', 'bj000000010'] as $id) {
            $this->assertSame(10, Product::codeNumberFromId($id));
        }

        foreach ([null, 'RJ', 'RJ123abc', 'XX000000010'] as $id) {
            $this->assertNull(Product::codeNumberFromId($id));
        }
    }

    public function test_numeric_code_scope_uses_stored_sort_key_and_id_ties(): void
    {
        foreach (['RJ000000002', 'BJ000000002', 'VJ000000002', 'RJ000000010', 'RJ000000001'] as $id) {
            Product::factory()->create(['id' => $id]);
        }

        $this->assertSame(
            ['RJ000000010', 'BJ000000002', 'RJ000000002', 'VJ000000002', 'RJ000000001'],
            Product::query()->orderByNumericCode()->pluck('id')->all(),
        );

        $this->assertSame(
            ['RJ000000001', 'BJ000000002', 'RJ000000002', 'VJ000000002', 'RJ000000010'],
            Product::query()->orderByNumericCode('asc')->pluck('id')->all(),
        );

        foreach (['RJ000000002', 'BJ000000002', 'VJ000000002'] as $id) {
            $this->assertDatabaseHas('products', ['id' => $id, 'code_number' => 2]);
        }
    }

    public function test_index_code_sort_breaks_equal_numbers_by_full_id(): void
    {
        foreach (['RJ000000010', 'VJ000000002', 'BJ000000002', 'RJ000000002'] as $id) {
            Product::factory()->create(['id' => $id]);
        }

        $results = app(ProductIndexResults::class);
        $default = $results->getProducts(
            ProductIndexFilters::fromQuery([]),
            Option::INDEX_PER_PAGE_UNLIMITED,
            [],
        );

        $this->assertSame(
            ['RJ000000010', 'VJ000000002', 'RJ000000002', 'BJ000000002'],
            $default->pluck('id')->all(),
        );

        $ascending = $results->getProducts(
            ProductIndexFilters::fromQuery([
                'sort_first_field' => 'rj', // Persisted sort value remains unchanged.
                'sort_first_direction' => 'asc',
            ]),
            Option::INDEX_PER_PAGE_UNLIMITED,
            [],
        );

        $this->assertSame(
            ['BJ000000002', 'RJ000000002', 'VJ000000002', 'RJ000000010'],
            $ascending->pluck('id')->all(),
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
