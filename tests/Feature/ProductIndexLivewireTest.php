<?php

namespace Tests\Feature;

use App\Livewire\ProductIndex;
use App\Models\Option;
use App\Models\Product;
use App\Support\ProductIndexFilters;
use App\Support\ProductIndexResults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class ProductIndexLivewireTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_uses_default_page_size_when_no_option_exists(): void
    {
        foreach (range(1, 101) as $number) {
            $this->createProduct($number);
        }

        Livewire::test(ProductIndex::class)
            ->assertSee('WORK_101')
            ->assertSee('WORK_002')
            ->assertDontSee('WORK_001')
            ->assertSee('Showing 1-100 of 101');
    }

    public function test_index_uses_fixed_custom_and_unlimited_page_size_options(): void
    {
        foreach (range(1, 5) as $number) {
            $this->createProduct($number);
        }

        Option::setIndexPerPage(2);

        Livewire::test(ProductIndex::class)
            ->assertSee('WORK_005')
            ->assertSee('WORK_004')
            ->assertDontSee('WORK_003')
            ->call('nextPage')
            ->assertSee('WORK_003')
            ->assertSee('WORK_002')
            ->assertDontSee('WORK_005');

        Option::setIndexPerPage(3);

        Livewire::test(ProductIndex::class)
            ->assertSee('WORK_005')
            ->assertSee('WORK_003')
            ->assertDontSee('WORK_002')
            ->assertSee('Showing 1-3 of 5');

        Option::setIndexPerPage(Option::INDEX_PER_PAGE_UNLIMITED);

        Livewire::test(ProductIndex::class)
            ->assertSee('WORK_005')
            ->assertSee('WORK_001')
            ->assertSee('Showing all 5 works')
            ->assertDontSee('Next');
    }

    public function test_header_rj_sort_toggles_across_the_full_filtered_result_set(): void
    {
        Option::setIndexPerPage(2);

        foreach (range(1, 3) as $number) {
            $this->createProduct($number);
        }

        Livewire::test(ProductIndex::class)
            ->assertSeeInOrder(['WORK_003', 'WORK_002'])
            ->call('sortByHeader', 'rj')
            ->assertSeeInOrder(['WORK_003', 'WORK_002'])
            ->call('sortByHeader', 'rj')
            ->assertSeeInOrder(['WORK_001', 'WORK_002'])
            ->assertDontSee('WORK_003');
    }

    public function test_advanced_sort_applies_primary_and_secondary_sorting(): void
    {
        Option::setIndexPerPage(Option::INDEX_PER_PAGE_UNLIMITED);

        $this->createProduct(1, [
            'work_name' => 'SORT_ALPHA',
            'score' => 7,
            'priority' => 1,
        ]);
        $this->createProduct(2, [
            'work_name' => 'SORT_BETA',
            'score' => 9,
            'priority' => 2,
        ]);
        $this->createProduct(3, [
            'work_name' => 'SORT_GAMMA',
            'score' => 9,
            'priority' => 0,
        ]);

        $component = Livewire::test(ProductIndex::class)
            ->set('draft.sort_first_field', 'score')
            ->set('draft.sort_first_direction', 'desc')
            ->set('draft.sort_second_field', 'priority')
            ->set('draft.sort_second_direction', 'asc')
            ->call('applyFilters')
            ->assertSet('sort_first_field', 'score')
            ->assertSet('sort_first_direction', 'desc')
            ->assertSet('sort_second_field', 'priority')
            ->assertSet('sort_second_direction', 'asc')
            ->assertSeeInOrder(['SORT_GAMMA', 'SORT_BETA', 'SORT_ALPHA']);

        $component
            ->assertSee('wire:model="draft.sort_first_field"', false)
            ->assertSee('wire:model="draft.sort_first_direction"', false)
            ->assertSee('wire:model="draft.sort_second_field"', false)
            ->assertSee('wire:model="draft.sort_second_direction"', false);
    }

    public function test_query_string_sort_is_selected_in_filter_modal(): void
    {
        $component = Livewire::withQueryParams([
            'sort_first_field' => 'score',
            'sort_first_direction' => 'asc',
        ])
            ->test(ProductIndex::class)
            ->assertSet('draft.sort_first_field', 'score')
            ->assertSet('draft.sort_first_direction', 'asc');

        $component
            ->assertSee('wire:model="draft.sort_first_field"', false)
            ->assertSee('wire:model="draft.sort_first_direction"', false);
    }

    public function test_header_sort_is_selected_in_filter_modal(): void
    {
        $component = Livewire::test(ProductIndex::class)
            ->call('sortByHeader', 'score')
            ->assertSet('draft.sort_first_field', 'score')
            ->assertSet('draft.sort_first_direction', 'desc');

        $component
            ->assertSee('wire:model="draft.sort_first_field"', false)
            ->assertSee('wire:model="draft.sort_first_direction"', false);

        $component
            ->call('sortByHeader', 'score')
            ->assertSet('draft.sort_first_field', 'score')
            ->assertSet('draft.sort_first_direction', 'asc');

        $component
            ->assertSee('wire:model="draft.sort_first_field"', false)
            ->assertSee('wire:model="draft.sort_first_direction"', false);
    }

    public function test_scalar_sort_uses_paginated_result_order_across_pages(): void
    {
        Option::setIndexPerPage(2);

        $this->createProduct(1, ['work_name' => 'SQL_SCORE_FOUR', 'score' => 4]);
        $this->createProduct(2, ['work_name' => 'SQL_SCORE_NINE', 'score' => 9]);
        $this->createProduct(3, ['work_name' => 'SQL_SCORE_SEVEN', 'score' => 7]);
        $this->createProduct(4, ['work_name' => 'SQL_SCORE_ONE', 'score' => 1]);

        Livewire::test(ProductIndex::class)
            ->set('draft.sort_first_field', 'score')
            ->set('draft.sort_first_direction', 'desc')
            ->call('applyFilters')
            ->assertSeeInOrder(['SQL_SCORE_NINE', 'SQL_SCORE_SEVEN'])
            ->assertDontSee('SQL_SCORE_FOUR')
            ->call('gotoPage', 2)
            ->assertSeeInOrder(['SQL_SCORE_FOUR', 'SQL_SCORE_ONE'])
            ->assertDontSee('SQL_SCORE_NINE');
    }

    public function test_nullable_scalar_sorts_keep_null_values_last(): void
    {
        $this->createProduct(1, [
            'work_name' => 'NULL_SORT_VALUE',
            'score' => null,
            'priority' => null,
            'num_re_listen_times' => null,
            're_listen_value' => null,
        ]);
        $this->createProduct(2, [
            'work_name' => 'LOW_SORT_VALUE',
            'score' => 1,
            'priority' => 0,
            'num_re_listen_times' => 1,
            're_listen_value' => 1,
        ]);
        $this->createProduct(3, [
            'work_name' => 'HIGH_SORT_VALUE',
            'score' => 9,
            'priority' => 2,
            'num_re_listen_times' => 3,
            're_listen_value' => 5,
        ]);

        $results = app(ProductIndexResults::class);
        $cases = [
            ['score', 'asc', ['LOW_SORT_VALUE', 'HIGH_SORT_VALUE', 'NULL_SORT_VALUE']],
            ['score', 'desc', ['HIGH_SORT_VALUE', 'LOW_SORT_VALUE', 'NULL_SORT_VALUE']],
            ['priority', 'asc', ['LOW_SORT_VALUE', 'HIGH_SORT_VALUE', 'NULL_SORT_VALUE']],
            ['num_re_listen_times', 'asc', ['LOW_SORT_VALUE', 'HIGH_SORT_VALUE', 'NULL_SORT_VALUE']],
            ['re_listen_value', 'asc', ['LOW_SORT_VALUE', 'HIGH_SORT_VALUE', 'NULL_SORT_VALUE']],
        ];

        foreach ($cases as [$field, $direction, $expectedNames]) {
            $products = $results->getProducts(
                ProductIndexFilters::fromQuery([
                    'sort_first_field' => $field,
                    'sort_first_direction' => $direction,
                ]),
                Option::INDEX_PER_PAGE_UNLIMITED,
            );

            $this->assertSame(
                $expectedNames,
                $products->pluck('work_name')->all(),
                "Failed asserting nullable {$field} {$direction} sort order.",
            );
        }
    }

    public function test_date_sort_uses_sql_sort_keys_across_paginated_results(): void
    {
        Option::setIndexPerPage(3);

        $this->createProduct(1, [
            'work_name' => 'DATE_SORT_YEAR_ONLY',
            'start_date' => ['year' => 2025, 'month' => null, 'day' => null],
        ]);
        $this->createProduct(2, [
            'work_name' => 'DATE_SORT_MONTH_ONLY',
            'start_date' => ['year' => 2025, 'month' => '03', 'day' => null],
        ]);
        $this->createProduct(3, [
            'work_name' => 'DATE_SORT_FULL',
            'start_date' => ['year' => 2025, 'month' => '03', 'day' => '04'],
        ]);
        $this->createProduct(4, [
            'work_name' => 'DATE_SORT_NULL',
            'start_date' => null,
        ]);

        Livewire::test(ProductIndex::class)
            ->set('draft.sort_first_field', 'start_date')
            ->set('draft.sort_first_direction', 'asc')
            ->call('applyFilters')
            ->assertSeeInOrder(['DATE_SORT_YEAR_ONLY', 'DATE_SORT_MONTH_ONLY', 'DATE_SORT_FULL'])
            ->assertDontSee('DATE_SORT_NULL')
            ->call('gotoPage', 2)
            ->assertSee('DATE_SORT_NULL')
            ->assertDontSee('DATE_SORT_YEAR_ONLY');
    }

    public function test_added_date_sort_uses_created_at_across_paginated_results(): void
    {
        Option::setIndexPerPage(2);

        $this->createProduct(1, [
            'work_name' => 'ADDED_DATE_OLD',
            'created_at' => Carbon::parse('2026-01-01 00:00:00'),
        ]);
        $this->createProduct(2, [
            'work_name' => 'ADDED_DATE_NEW',
            'created_at' => Carbon::parse('2026-03-01 00:00:00'),
        ]);
        $this->createProduct(3, [
            'work_name' => 'ADDED_DATE_MID',
            'created_at' => Carbon::parse('2026-02-01 00:00:00'),
        ]);

        Livewire::test(ProductIndex::class)
            ->set('draft.sort_first_field', 'created_at')
            ->set('draft.sort_first_direction', 'desc')
            ->call('applyFilters')
            ->assertSee('Added to the site Date')
            ->assertSeeInOrder(['ADDED_DATE_NEW', 'ADDED_DATE_MID'])
            ->assertDontSee('ADDED_DATE_OLD')
            ->call('gotoPage', 2)
            ->assertSee('ADDED_DATE_OLD')
            ->assertDontSee('ADDED_DATE_NEW');
    }

    public function test_index_results_hydrate_only_columns_needed_by_index(): void
    {
        $this->createProduct(1, [
            'work_name' => 'NARROW_COLUMNS_VISIBLE',
            'description' => 'NARROW_COLUMNS_HIDDEN_DESCRIPTION',
            'start_date' => ['year' => 2024, 'month' => null, 'day' => null],
        ]);

        $products = app(ProductIndexResults::class)->getProducts(
            new ProductIndexFilters,
            Option::INDEX_PER_PAGE_UNLIMITED,
        );

        $product = $products->first();

        $this->assertNotNull($product);

        $attributes = $product->getAttributes();

        $this->assertArrayHasKey('id', $attributes);
        $this->assertArrayHasKey('work_name', $attributes);
        $this->assertArrayHasKey('start_date', $attributes);
        $this->assertArrayHasKey('created_at', $attributes);
        $this->assertArrayNotHasKey('description', $attributes);

        Livewire::test(ProductIndex::class)
            ->assertSee('NARROW_COLUMNS_VISIBLE')
            ->assertDontSee('NARROW_COLUMNS_HIDDEN_DESCRIPTION');
    }

    public function test_search_results_paginate_across_pages(): void
    {
        Option::setIndexPerPage(2);

        $this->createProduct(1, ['work_name' => 'SEARCH_PAGE_MATCH_ONE']);
        $this->createProduct(2, ['work_name' => 'SEARCH_PAGE_MATCH_TWO']);
        $this->createProduct(3, ['work_name' => 'SEARCH_PAGE_MATCH_THREE']);
        $this->createProduct(4, ['work_name' => 'SEARCH_PAGE_OTHER']);

        Livewire::test(ProductIndex::class)
            ->set('searchInput', 'SEARCH_PAGE_MATCH')
            ->call('applySearch')
            ->assertSee('SEARCH_PAGE_MATCH_THREE')
            ->assertSee('SEARCH_PAGE_MATCH_TWO')
            ->assertDontSee('SEARCH_PAGE_MATCH_ONE')
            ->assertDontSee('SEARCH_PAGE_OTHER')
            ->call('nextPage')
            ->assertSee('SEARCH_PAGE_MATCH_ONE')
            ->assertDontSee('SEARCH_PAGE_MATCH_THREE')
            ->assertDontSee('SEARCH_PAGE_OTHER');
    }

    public function test_filter_changes_reset_pagination_to_first_page(): void
    {
        Option::setIndexPerPage(1);

        $this->createProduct(1, ['work_name' => 'RESET_TARGET']);
        $this->createProduct(2, ['work_name' => 'RESET_OTHER']);

        Livewire::test(ProductIndex::class)
            ->call('nextPage')
            ->assertSee('RESET_TARGET')
            ->set('searchInput', 'RESET_OTHER')
            ->call('applySearch')
            ->assertSee('RESET_OTHER')
            ->assertDontSee('RESET_TARGET');
    }

    public function test_built_in_pagination_methods_change_visible_page(): void
    {
        Option::setIndexPerPage(2);

        foreach (range(1, 5) as $number) {
            $this->createProduct($number);
        }

        Livewire::test(ProductIndex::class)
            ->assertSee('WORK_005')
            ->assertDontSee('WORK_003')
            ->call('nextPage')
            ->assertSee('WORK_003')
            ->assertDontSee('WORK_005')
            ->call('previousPage')
            ->assertSee('WORK_005')
            ->assertDontSee('WORK_003')
            ->call('gotoPage', 2)
            ->assertSee('WORK_002')
            ->assertDontSee('WORK_005');
    }

    public function test_pagination_markup_uses_built_in_links_and_scrolls_to_progress_menu(): void
    {
        Option::setIndexPerPage(1);
        $this->createProduct(1);
        $this->createProduct(2);

        Livewire::test(ProductIndex::class)
            ->assertSee('id="progress-menu"', false)
            ->assertSee('#progress-menu', false)
            ->assertSee('x-on:click=', false)
            ->assertSee("wire:click=\"nextPage('page')\"", false)
            ->assertSee('wire:click="gotoPage(', false);
    }

    public function test_advanced_filter_defaults_are_selected_without_query_state(): void
    {
        Livewire::test(ProductIndex::class)
            ->assertSet('draft.tag_match', 'all')
            ->assertSet('draft.sort_first_direction', 'desc')
            ->assertSet('draft.sort_second_direction', 'desc')
            ->assertSet('tag_match', '')
            ->assertSet('sort_first_direction', '')
            ->assertSet('sort_second_direction', '')
            ->assertSee('All tags')
            ->assertSee('Desc');
    }

    public function test_query_string_values_initialize_livewire_index_state(): void
    {
        $this->createProduct(1, ['work_name' => 'QUERY_HIDDEN']);
        $this->createProduct(2, ['work_name' => 'QUERY_VISIBLE']);

        Livewire::withQueryParams(['search' => 'QUERY_VISIBLE'])
            ->test(ProductIndex::class)
            ->assertSet('search', 'QUERY_VISIBLE')
            ->assertSee('QUERY_VISIBLE')
            ->assertDontSee('QUERY_HIDDEN');
    }

    private function createProduct(int $number, array $attributes = []): Product
    {
        $id = 'RJ' . str_pad((string) $number, 9, '0', STR_PAD_LEFT);

        return Product::factory()->create(array_merge([
            'id' => $id,
            'maker_id' => 'RG' . substr($id, 2),
            'work_name' => 'WORK_' . str_pad((string) $number, 3, '0', STR_PAD_LEFT),
        ], $attributes));
    }
}
