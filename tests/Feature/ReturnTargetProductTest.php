<?php

namespace Tests\Feature;

use App\Models\Option;
use App\Models\Product;
use App\Support\ReturnTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ReturnTargetProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_product_omits_page_for_unlimited_pagination(): void
    {
        Product::factory()->create(['id' => 'RJ000000001', 'score' => 1]);
        Product::factory()->create(['id' => 'RJ000000002', 'score' => 3]);
        $product = Product::factory()->create(['id' => 'RJ000000003', 'score' => 5]);

        $target = ReturnTarget::fromRequest(Request::create('/store/custom', 'POST', [
            'return_query' => [
                'sort_first_field' => 'score',
                'sort_first_direction' => 'asc',
                'page' => '9',
            ],
        ]))->forProduct($product, perPage: Option::INDEX_PER_PAGE_UNLIMITED);

        $this->assertSame('/?sort_first_field=score&sort_first_direction=asc#RJ000000003', $target->toUrl());
    }

    public function test_for_product_omits_first_page_from_index_url(): void
    {
        $product = Product::factory()->create(['id' => 'RJ000000001', 'score' => 1]);
        Product::factory()->create(['id' => 'RJ000000002', 'score' => 3]);

        $target = ReturnTarget::fromRequest(Request::create('/store/custom', 'POST', [
            'return_query' => [
                'sort_first_field' => 'score',
                'sort_first_direction' => 'asc',
                'page' => '9',
            ],
        ]))->forProduct($product, perPage: 2);

        $this->assertSame('/?sort_first_field=score&sort_first_direction=asc#RJ000000001', $target->toUrl());
    }

    public function test_for_product_drops_multiple_hiding_filter_groups(): void
    {
        $product = Product::factory()->create([
            'id' => 'RJ000000001',
            'work_name' => 'VISIBLE_RETURN_TARGET',
            'progress' => 'Listening',
            'series' => 'VISIBLE_SERIES',
            'score' => 8,
        ]);

        $target = ReturnTarget::fromRequest(Request::create('/update/RJ000000001', 'POST', [
            'return_query' => [
                'search' => 'HIDDEN_SEARCH',
                'series' => 'HIDDEN_SERIES',
                'progress' => 'Listening',
                'score' => '1',
                'sort_first_field' => 'score',
                'sort_first_direction' => 'asc',
            ],
        ]))->forProduct($product, perPage: 2);

        $this->assertSame('/?progress=Listening&sort_first_field=score&sort_first_direction=asc#RJ000000001', $target->toUrl());
    }
}
