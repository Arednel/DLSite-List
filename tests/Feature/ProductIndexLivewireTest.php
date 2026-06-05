<?php

namespace Tests\Feature;

use App\Enums\ProductContributorRole;
use App\Enums\ProductField;
use App\Enums\ProductIndexSortField;
use App\Livewire\ProductIndex;
use App\Models\Genre;
use App\Models\Option;
use App\Models\Product;
use App\Support\ProductContributorSync;
use App\Support\ProductGenreSync;
use App\Support\ProductIndexFilters;
use App\Support\ProductIndexResults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

    public function test_index_results_hydrate_only_base_columns_and_sort_without_hydrating_sort_fields(): void
    {
        $this->createProduct(1, [
            'work_name' => 'NARROW_PRIORITY_LOW',
            'work_name_english' => 'NARROW_PRIORITY_LOW_EN',
            'notes' => 'NARROW_VISIBLE_NOTES',
            'work_image' => 'NARROW_HIDDEN_IMAGE',
            'score' => 9,
            'series' => 'NARROW_HIDDEN_SERIES',
            'age_category' => 'R18',
            'circle' => 'NARROW_HIDDEN_CIRCLE',
            'maker_id' => 'RG000000001',
            'description' => 'NARROW_COLUMNS_HIDDEN_DESCRIPTION',
            'description_english' => 'NARROW_COLUMNS_HIDDEN_DESCRIPTION_EN',
            'progress' => 'Completed',
            'priority' => 1,
            'num_re_listen_times' => 4,
            're_listen_value' => 2,
            'start_date' => ['year' => 2024, 'month' => null, 'day' => null],
            'end_date' => ['year' => 2024, 'month' => '12', 'day' => null],
            'created_at' => Carbon::parse('2026-01-01 00:00:00'),
        ]);
        $this->createProduct(2, [
            'work_name' => 'NARROW_PRIORITY_HIGH',
            'priority' => 2,
            'created_at' => Carbon::parse('2026-02-01 00:00:00'),
        ]);

        $products = app(ProductIndexResults::class)->getProducts(
            ProductIndexFilters::fromQuery([
                'sort_first_field' => 'priority',
                'sort_first_direction' => 'asc',
            ]),
            Option::INDEX_PER_PAGE_UNLIMITED,
        );

        $product = $products->first();

        $this->assertNotNull($product);
        $this->assertSame(['NARROW_PRIORITY_LOW', 'NARROW_PRIORITY_HIGH'], $products->pluck('work_name')->all());

        $attributes = $product->getAttributes();

        $this->assertArrayHasKey('id', $attributes);
        $this->assertArrayHasKey('work_name', $attributes);
        $this->assertArrayHasKey('work_name_english', $attributes);
        $this->assertArrayHasKey('notes', $attributes);
        $this->assertArrayHasKey('progress', $attributes);
        $this->assertArrayNotHasKey('work_image', $attributes);
        $this->assertArrayNotHasKey('score', $attributes);
        $this->assertArrayNotHasKey('series', $attributes);
        $this->assertArrayNotHasKey('age_category', $attributes);
        $this->assertArrayNotHasKey('circle', $attributes);
        $this->assertArrayNotHasKey('maker_id', $attributes);
        $this->assertArrayNotHasKey('description', $attributes);
        $this->assertArrayNotHasKey('description_english', $attributes);
        $this->assertArrayNotHasKey('priority', $attributes);
        $this->assertArrayNotHasKey('num_re_listen_times', $attributes);
        $this->assertArrayNotHasKey('re_listen_value', $attributes);
        $this->assertArrayNotHasKey('start_date', $attributes);
        $this->assertArrayNotHasKey('end_date', $attributes);
        $this->assertArrayNotHasKey('created_at', $attributes);
        $this->assertArrayNotHasKey('rj_number', $attributes);
        $this->assertArrayNotHasKey('start_date_sort', $attributes);
        $this->assertArrayNotHasKey('end_date_sort', $attributes);

        Livewire::test(ProductIndex::class)
            ->assertSee('NARROW_PRIORITY_LOW')
            ->assertDontSee('NARROW_COLUMNS_HIDDEN_DESCRIPTION');
    }

    public function test_index_results_hydrate_columns_for_visible_index_fields(): void
    {
        $this->createProduct(1, [
            'work_name' => 'VISIBLE_COLUMNS_WORK',
            'work_name_english' => 'VISIBLE_COLUMNS_WORK_EN',
            'notes' => 'VISIBLE_COLUMNS_NOTES',
            'work_image' => 'VISIBLE_COLUMNS_IMAGE',
            'score' => 8,
            'series' => 'VISIBLE_COLUMNS_SERIES',
            'age_category' => 'R18',
            'circle' => 'VISIBLE_COLUMNS_CIRCLE',
            'maker_id' => 'RG000000001',
            'description' => 'VISIBLE_COLUMNS_DESCRIPTION',
            'description_english' => 'VISIBLE_COLUMNS_DESCRIPTION_EN',
            'progress' => 'Listening',
            'priority' => 2,
            'num_re_listen_times' => 3,
            're_listen_value' => 5,
            'start_date' => ['year' => 2025, 'month' => '03', 'day' => '01'],
            'end_date' => ['year' => 2025, 'month' => '03', 'day' => '02'],
            'created_at' => Carbon::parse('2026-03-01 00:00:00'),
        ]);

        $products = app(ProductIndexResults::class)->getProducts(
            new ProductIndexFilters,
            Option::INDEX_PER_PAGE_UNLIMITED,
            [
                ProductField::Image->value,
                ProductField::Title->value,
                ProductField::Score->value,
                ProductField::Series->value,
                ProductField::AgeCategory->value,
                ProductField::Progress->value,
                ProductField::Circle->value,
                ProductField::Scenario->value,
                ProductField::Illustration->value,
                ProductField::VoiceActor->value,
                ProductField::Author->value,
                ProductField::Description->value,
                ProductField::Tags->value,
            ],
        );

        $product = $products->first();

        $this->assertNotNull($product);

        $attributes = $product->getAttributes();

        $this->assertArrayHasKey('id', $attributes);
        $this->assertArrayHasKey('work_name', $attributes);
        $this->assertArrayHasKey('work_name_english', $attributes);
        $this->assertArrayHasKey('notes', $attributes);
        $this->assertArrayHasKey('progress', $attributes);
        $this->assertArrayHasKey('work_image', $attributes);
        $this->assertArrayHasKey('score', $attributes);
        $this->assertArrayHasKey('series', $attributes);
        $this->assertArrayHasKey('age_category', $attributes);
        $this->assertArrayHasKey('circle', $attributes);
        $this->assertArrayHasKey('maker_id', $attributes);
        $this->assertArrayHasKey('description', $attributes);
        $this->assertArrayHasKey('description_english', $attributes);
        $this->assertArrayNotHasKey('priority', $attributes);
        $this->assertArrayNotHasKey('num_re_listen_times', $attributes);
        $this->assertArrayNotHasKey('re_listen_value', $attributes);
        $this->assertArrayNotHasKey('start_date', $attributes);
        $this->assertArrayNotHasKey('end_date', $attributes);
        $this->assertArrayNotHasKey('created_at', $attributes);
        $this->assertArrayNotHasKey('rj_number', $attributes);
        $this->assertArrayNotHasKey('start_date_sort', $attributes);
        $this->assertArrayNotHasKey('end_date_sort', $attributes);
    }

    public function test_index_field_layout_can_show_hidden_description_and_reorder_columns(): void
    {
        $this->createProduct(1, [
            'work_name' => 'FIELD_LAYOUT_VISIBLE_WORK',
            'description' => 'FIELD_LAYOUT_VISIBLE_DESCRIPTION',
        ]);

        Option::setIndexFieldLayout([
            ['field' => 'description', 'visible' => true],
            ['field' => 'score', 'visible' => false],
            ['field' => 'tags', 'visible' => true],
        ]);

        $products = app(ProductIndexResults::class)->getProducts(
            new ProductIndexFilters,
            Option::INDEX_PER_PAGE_UNLIMITED,
            ['description'],
        );

        $this->assertArrayHasKey('description', $products->first()->getAttributes());

        Livewire::test(ProductIndex::class)
            ->assertSee('FIELD_LAYOUT_VISIBLE_DESCRIPTION')
            ->assertSeeInOrder(['Description', 'Tags', 'Series']);
    }

    public function test_index_layout_can_hide_image_while_title_stays_locked_visible(): void
    {
        $this->createProduct(1, [
            'work_name' => 'LOCKED_TITLE_VISIBLE',
            'work_image' => 'HIDDEN_IMAGE_PATH',
        ]);

        Option::setIndexFieldLayout([
            ['field' => ProductField::Title->value, 'visible' => false],
            ['field' => ProductField::Image->value, 'visible' => false],
        ]);

        Livewire::test(ProductIndex::class)
            ->assertSee('LOCKED_TITLE_VISIBLE')
            ->assertSee('data-column="Title"', false)
            ->assertDontSee('data-column="Image"', false)
            ->assertDontSee('HIDDEN_IMAGE_PATH');
    }

    public function test_index_layout_can_reorder_image_before_locked_title(): void
    {
        $this->createProduct(1, ['work_name' => 'REORDERED_IMAGE_TITLE']);

        Option::setIndexFieldLayout([
            ['field' => ProductField::Image->value, 'visible' => true],
            ['field' => ProductField::Title->value, 'visible' => true],
        ]);

        Livewire::test(ProductIndex::class)
            ->assertSeeInOrder(['data-column="Image"', 'data-column="Title"'], false)
            ->assertSee('REORDERED_IMAGE_TITLE');
    }

    public function test_optional_index_columns_render_and_sort_from_visible_headers(): void
    {
        Option::setIndexPerPage(Option::INDEX_PER_PAGE_UNLIMITED);
        Option::setIndexFieldLayout([
            ['field' => ProductField::Notes->value, 'visible' => true],
            ['field' => ProductField::StartDate->value, 'visible' => true],
            ['field' => ProductField::FinishDate->value, 'visible' => true],
            ['field' => ProductField::TotalTimesReListened->value, 'visible' => true],
            ['field' => ProductField::ReListenValue->value, 'visible' => true],
            ['field' => ProductField::Priority->value, 'visible' => true],
        ]);

        $this->createProduct(1, [
            'work_name' => 'OPTIONAL_COLUMNS_HIGH',
            'notes' => 'OPTIONAL_VISIBLE_NOTES',
            'start_date' => ['year' => 2026, 'month' => '01', 'day' => '02'],
            'end_date' => ['year' => 2026, 'month' => '02', 'day' => '03'],
            'num_re_listen_times' => 4,
            're_listen_value' => 5,
            'priority' => 2,
        ]);
        $this->createProduct(2, [
            'work_name' => 'OPTIONAL_COLUMNS_LOW',
            'priority' => 0,
        ]);

        Livewire::test(ProductIndex::class)
            ->assertSee('data-column="Notes"', false)
            ->assertSee('data-column="Start Date"', false)
            ->assertSee('data-column="Finish Date"', false)
            ->assertSee('data-column="Total Times Re-listened"', false)
            ->assertSee('data-column="Re-listen Value"', false)
            ->assertSee('data-column="Priority"', false)
            ->assertSee('OPTIONAL_VISIBLE_NOTES')
            ->assertSee('Year: 2026, Month: 01, Day: 02')
            ->assertSee('Very High')
            ->assertSee('High')
            ->assertSee('wire:click="sortByHeader(\'priority\')"', false)
            ->assertSeeInOrder(['OPTIONAL_COLUMNS_LOW', 'OPTIONAL_COLUMNS_HIGH'])
            ->call('sortByHeader', ProductIndexSortField::Priority->value)
            ->assertSeeInOrder(['OPTIONAL_COLUMNS_HIGH', 'OPTIONAL_COLUMNS_LOW']);
    }

    public function test_contributor_and_circle_index_headers_are_sortable_when_visible(): void
    {
        Option::setIndexPerPage(Option::INDEX_PER_PAGE_UNLIMITED);
        Option::setIndexFieldLayout([
            ['field' => ProductField::Circle->value, 'visible' => true],
            ['field' => ProductField::Scenario->value, 'visible' => true],
        ]);

        $zeta = $this->createProduct(1, [
            'work_name' => 'CONTRIBUTOR_SORT_ZETA',
            'circle' => 'Fallback Zeta',
        ]);
        $alpha = $this->createProduct(2, [
            'work_name' => 'CONTRIBUTOR_SORT_ALPHA',
            'circle' => 'Fallback Alpha',
        ]);

        app(ProductContributorSync::class)->sync($zeta, [
            ProductContributorRole::Scenario->value => ['Zeta Scenario'],
        ]);
        app(ProductContributorSync::class)->sync($alpha, [
            ProductContributorRole::Scenario->value => ['Alpha Scenario'],
        ]);

        Livewire::test(ProductIndex::class)
            ->assertSee('wire:click="sortByHeader(\'circle\')"', false)
            ->assertSee('wire:click="sortByHeader(\'scenario\')"', false)
            ->assertSeeInOrder(['CONTRIBUTOR_SORT_ALPHA', 'CONTRIBUTOR_SORT_ZETA'])
            ->call('sortByHeader', ProductIndexSortField::Scenario->value)
            ->assertSeeInOrder(['CONTRIBUTOR_SORT_ZETA', 'CONTRIBUTOR_SORT_ALPHA']);

        Livewire::test(ProductIndex::class)
            ->call('sortByHeader', ProductIndexSortField::Circle->value)
            ->assertSeeInOrder(['CONTRIBUTOR_SORT_ZETA', 'CONTRIBUTOR_SORT_ALPHA']);
    }

    public function test_index_row_data_renders_contributors_and_tags(): void
    {
        $product = $this->createProduct(1, [
            'work_name' => 'ROW_DATA_WORK',
        ]);
        $genre = Genre::query()->create([
            'group_id' => null,
            'title' => 'ROW_DATA_TAG',
            'description' => null,
            'order' => null,
        ]);

        app(ProductGenreSync::class)->syncCustom($product, [$genre->getKey()]);
        app(ProductContributorSync::class)->sync($product, [
            ProductContributorRole::VoiceActor->value => ['ROW_DATA_VOICE'],
        ]);

        Option::setIndexFieldLayout([
            ['field' => 'voice_actor', 'visible' => true],
            ['field' => 'tags', 'visible' => true],
        ]);

        Livewire::test(ProductIndex::class)
            ->assertSee('ROW_DATA_WORK')
            ->assertSee('ROW_DATA_VOICE')
            ->assertSee('ROW_DATA_TAG');
    }

    public function test_index_tag_links_use_prepared_base_url_and_replace_current_genre_filter(): void
    {
        Option::setIndexFieldLayout([
            ['field' => ProductField::Tags->value, 'visible' => true],
        ]);

        $emptyQueryProduct = $this->createProduct(1, [
            'work_name' => 'TAG_LINK_EMPTY_QUERY_WORK',
        ]);
        $emptyQueryGenre = Genre::query()->create([
            'group_id' => null,
            'title' => 'TAG_LINK_EMPTY_QUERY_GENRE',
            'description' => null,
            'order' => null,
        ]);

        app(ProductGenreSync::class)->syncCustom($emptyQueryProduct, [$emptyQueryGenre->getKey()]);

        Livewire::test(ProductIndex::class)
            ->assertSee('href="/?genre=' . $emptyQueryGenre->getKey() . '"', false);

        $currentGenre = Genre::query()->create([
            'group_id' => null,
            'title' => 'TAG_LINK_CURRENT_GENRE',
            'description' => null,
            'order' => null,
        ]);
        $linkedGenre = Genre::query()->create([
            'group_id' => null,
            'title' => 'TAG_LINK_LINKED_GENRE',
            'description' => null,
            'order' => null,
        ]);
        $filteredProduct = $this->createProduct(2, [
            'work_name' => 'rain TAG_LINK_FILTERED_WORK',
            'progress' => 'Listening',
            'series' => 'SERIES_ALPHA',
        ]);

        app(ProductGenreSync::class)->syncCustom($filteredProduct, [
            $currentGenre->getKey(),
            $linkedGenre->getKey(),
        ]);

        Livewire::withQueryParams([
            'genre' => (string) $currentGenre->getKey(),
            'search' => 'rain',
            'series' => 'SERIES_ALPHA',
            'progress' => 'Listening',
            'sort_first_field' => 'score',
            'sort_first_direction' => 'asc',
        ])
            ->test(ProductIndex::class)
            ->assertSee(
                'href="/?search=rain&amp;series=SERIES_ALPHA&amp;progress=Listening&amp;sort_first_field=score&amp;sort_first_direction=asc&amp;genre='
                    . $linkedGenre->getKey()
                    . '"',
                false,
            )
            ->assertDontSee('genre=' . $currentGenre->getKey() . '&amp;genre=', false);
    }

    public function test_index_table_width_option_is_applied_to_table_container(): void
    {
        $this->createProduct(1, ['work_name' => 'WIDTH_VISIBLE_WORK']);
        Option::setIndexTableWidth([
            'mode' => Option::INDEX_TABLE_WIDTH_WIDE,
            'custom' => '',
        ]);

        Livewire::test(ProductIndex::class)
            ->assertSee('--index-table-width: 1400px', false)
            ->assertSee('WIDTH_VISIBLE_WORK');
    }

    public function test_index_render_batches_option_settings_lookup(): void
    {
        $this->createProduct(1, [
            'work_name' => 'BATCHED_OPTION_WORK',
            'description' => 'BATCHED_OPTION_DESCRIPTION',
        ]);
        Option::setIndexPerPage(25);
        Option::setIndexFieldLayout([
            ['field' => ProductField::Description->value, 'visible' => true],
        ]);
        Option::setFilterFieldLayout([
            ['field' => ProductField::Priority->value, 'visible' => true],
        ]);
        Option::setIndexTableWidth([
            'mode' => Option::INDEX_TABLE_WIDTH_WIDE,
            'custom' => '',
        ]);

        $optionQueries = [];
        DB::listen(function ($query) use (&$optionQueries): void {
            if (str_contains(strtolower($query->sql), 'options')) {
                $optionQueries[] = $query->sql;
            }
        });

        Livewire::test(ProductIndex::class)
            ->assertSee('BATCHED_OPTION_DESCRIPTION')
            ->assertSee('id="filter_priority"', false)
            ->assertSee('--index-table-width: 1400px', false);

        $this->assertCount(1, $optionQueries, implode("\n", $optionQueries));
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
            ->assertSee('Desc')
            ->assertSeeInOrder([
                'id="filter_title"',
                'id="filter_score"',
                'id="filter_series"',
                'id="filter_age_category"',
                'id="filter_progress"',
                'id="filter_notes"',
                'id="filter_priority"',
                'id="filter_num_re_listen_times"',
                'id="filter_re_listen_value"',
                'id="filter_tags"',
            ], false)
            ->assertDontSee('id="filter_circle"', false)
            ->assertDontSee('id="filter_scenario"', false)
            ->assertDontSee('id="filter_illustration"', false)
            ->assertDontSee('id="filter_voice_actor"', false)
            ->assertDontSee('id="filter_author"', false)
            ->assertDontSee('id="filter_start_date_from"', false)
            ->assertDontSee('id="filter_end_date_from"', false)
            ->assertDontSee('id="filter_created_at_from"', false)
            ->assertDontSee('id="filter_updated_at_from"', false)
            ->assertDontSee('id="filter_description"', false);
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

    public function test_filter_layout_can_hide_fixed_filter_widgets_without_disabling_url_filters(): void
    {
        $this->createProduct(1, ['work_name' => 'FILTER_LAYOUT_HIDDEN']);
        $this->createProduct(2, ['work_name' => 'FILTER_LAYOUT_VISIBLE']);

        Option::setFilterFieldLayout([
            ['field' => ProductField::Title->value, 'visible' => false],
            ['field' => ProductField::Notes->value, 'visible' => false],
            ['field' => ProductField::Priority->value, 'visible' => false],
            ['field' => ProductField::TotalTimesReListened->value, 'visible' => false],
            ['field' => ProductField::ReListenValue->value, 'visible' => false],
        ]);

        Livewire::withQueryParams(['title' => 'FILTER_LAYOUT_VISIBLE'])
            ->test(ProductIndex::class)
            ->assertSee('FILTER_LAYOUT_VISIBLE')
            ->assertDontSee('FILTER_LAYOUT_HIDDEN')
            ->assertDontSee('id="filter_title"', false)
            ->assertDontSee('id="filter_notes"', false)
            ->assertDontSee('id="filter_priority"', false)
            ->assertDontSee('id="filter_num_re_listen_times"', false)
            ->assertDontSee('id="filter_re_listen_value"', false);
    }

    public function test_filter_layout_can_reorder_fixed_filter_widgets(): void
    {
        Option::setFilterFieldLayout([
            ['field' => ProductField::Priority->value, 'visible' => true],
            ['field' => ProductField::Title->value, 'visible' => true],
            ['field' => ProductField::Notes->value, 'visible' => true],
            ['field' => ProductField::TotalTimesReListened->value, 'visible' => true],
            ['field' => ProductField::ReListenValue->value, 'visible' => true],
        ]);

        Livewire::test(ProductIndex::class)
            ->assertSeeInOrder([
                'id="filter_priority"',
                'id="filter_title"',
                'id="filter_notes"',
                'id="filter_num_re_listen_times"',
                'id="filter_re_listen_value"',
            ], false);
    }

    public function test_filter_layout_can_show_date_range_widgets_and_apply_ranges(): void
    {
        Option::setFilterFieldLayout([
            ['field' => ProductField::StartDate->value, 'visible' => true],
            ['field' => ProductField::FinishDate->value, 'visible' => true],
            ['field' => ProductField::CreatedAt->value, 'visible' => true],
            ['field' => ProductField::UpdatedAt->value, 'visible' => true],
        ]);

        $this->createProduct(1, [
            'work_name' => 'DATE_RANGE_VISIBLE',
            'start_date' => ['year' => 2026, 'month' => '02', 'day' => '15'],
            'end_date' => ['year' => 2026, 'month' => '03', 'day' => '15'],
            'created_at' => Carbon::parse('2026-04-15 12:00:00'),
            'updated_at' => Carbon::parse('2026-05-15 12:00:00'),
        ]);
        $this->createProduct(2, [
            'work_name' => 'DATE_RANGE_HIDDEN',
            'start_date' => ['year' => 2026, 'month' => '01', 'day' => '15'],
            'end_date' => ['year' => 2026, 'month' => '06', 'day' => '15'],
            'created_at' => Carbon::parse('2026-01-15 12:00:00'),
            'updated_at' => Carbon::parse('2026-07-15 12:00:00'),
        ]);

        Livewire::test(ProductIndex::class)
            ->assertSee('id="filter_start_date_from"', false)
            ->assertSee('class="filter-field-stack filter-date-range"', false)
            ->assertSee('class="filter-date-control"', false)
            ->assertSee('>From</span>', false)
            ->assertSee('>To</span>', false)
            ->assertSee('id="filter_end_date_from"', false)
            ->assertSee('id="filter_created_at_from"', false)
            ->assertSee('id="filter_updated_at_from"', false)
            ->set('draft.start_date_from', '2026-02-01')
            ->set('draft.start_date_to', '2026-02-28')
            ->set('draft.end_date_from', '2026-03-01')
            ->set('draft.end_date_to', '2026-03-31')
            ->set('draft.created_at_from', '2026-04-01')
            ->set('draft.created_at_to', '2026-04-30')
            ->set('draft.updated_at_from', '2026-05-01')
            ->set('draft.updated_at_to', '2026-05-31')
            ->call('applyFilters')
            ->assertSet('start_date_from', '2026-02-01')
            ->assertSet('updated_at_to', '2026-05-31')
            ->assertSee('DATE_RANGE_VISIBLE')
            ->assertDontSee('DATE_RANGE_HIDDEN');
    }

    public function test_sort_field_layout_controls_filter_dropdown_without_disabling_sorting(): void
    {
        Option::setIndexPerPage(Option::INDEX_PER_PAGE_UNLIMITED);
        Option::setIndexSortFieldLayout([
            ['field' => ProductIndexSortField::Series->value, 'visible' => true],
            ['field' => ProductIndexSortField::Score->value, 'visible' => false],
        ]);

        $this->createProduct(1, ['work_name' => 'SORT_LOW', 'score' => 1]);
        $this->createProduct(2, ['work_name' => 'SORT_HIGH', 'score' => 9]);

        Livewire::withQueryParams([
            'sort_first_field' => ProductIndexSortField::Score->value,
            'sort_first_direction' => 'asc',
        ])
            ->test(ProductIndex::class)
            ->assertSet('sort_first_field', ProductIndexSortField::Score->value)
            ->assertSet('draft.sort_first_field', ProductIndexSortField::Score->value)
            ->assertSeeInOrder(['SORT_LOW', 'SORT_HIGH'])
            ->assertSee('value="' . ProductIndexSortField::Series->value . '"', false)
            ->assertSee('value="' . ProductIndexSortField::RJ->value . '"', false)
            ->assertDontSee('value="' . ProductIndexSortField::Score->value . '"', false)
            ->assertDontSee('value="' . ProductIndexSortField::UpdatedAt->value . '"', false)
            ->assertDontSee('value="' . ProductIndexSortField::Circle->value . '"', false);

        Livewire::test(ProductIndex::class)
            ->call('sortByHeader', ProductIndexSortField::Score->value)
            ->assertSet('sort_first_field', ProductIndexSortField::Score->value)
            ->assertSet('sort_first_direction', 'desc')
            ->assertSeeInOrder(['SORT_HIGH', 'SORT_LOW'])
            ->assertDontSee('value="' . ProductIndexSortField::Score->value . '"', false);
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
