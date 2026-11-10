<?php

namespace Tests\Feature;

use App\Livewire\OptionsWorkSearch;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OptionsWorkSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_livewire_work_search_filters_by_id_and_titles(): void
    {
        $visible = Product::factory()->withTitles('VISIBLE_WORK_TOKEN', 'VISIBLE_ENGLISH_TOKEN')->create([
            'id' => 'RJ111111111',
        ]);
        $visible->localizations()->updateOrCreate(
            ['locale' => 'zh_TW'],
            ['title' => 'FUTURE_LOCALE_SEARCH_TOKEN'],
        );
        Product::factory()->withTitles('HIDDEN_WORK_TOKEN', 'HIDDEN_ENGLISH_TOKEN')->create([
            'id' => 'RJ222222222',
        ]);

        Livewire::test(OptionsWorkSearch::class)
            ->assertSee('VISIBLE_WORK_TOKEN')
            ->assertSee('HIDDEN_WORK_TOKEN')
            ->set('search', 'VISIBLE_ENGLISH')
            ->assertSee('VISIBLE_WORK_TOKEN')
            ->assertDontSee('HIDDEN_WORK_TOKEN')
            ->set('search', 'RJ222')
            ->assertSee('HIDDEN_WORK_TOKEN')
            ->assertDontSee('VISIBLE_WORK_TOKEN');
    }

    public function test_livewire_work_search_sorts_visible_results_by_numeric_product_code_descending(): void
    {
        Product::factory()->create([
            'id' => 'RJ000000002',
            'work_name' => 'ORDER_MATCH_TWO',
        ]);
        Product::factory()->create([
            'id' => 'RJ000000010',
            'work_name' => 'ORDER_MATCH_TEN',
        ]);
        Product::factory()->create([
            'id' => 'RJ000000001',
            'work_name' => 'ORDER_OTHER_ONE',
        ]);

        Livewire::test(OptionsWorkSearch::class)
            ->assertSeeInOrder(['RJ000000010', 'RJ000000002', 'RJ000000001'])
            ->set('search', 'ORDER_MATCH')
            ->assertSeeInOrder(['RJ000000010', 'RJ000000002'])
            ->assertDontSee('RJ000000001');
    }

    public function test_livewire_work_search_preserves_selected_ids_when_filtered_out(): void
    {
        $selected = Product::factory()->create([
            'id' => 'RJ333333333',
            'work_name' => 'SELECTED_WORK_TOKEN',
        ]);
        Product::factory()->create([
            'id' => 'RJ444444444',
            'work_name' => 'VISIBLE_FILTERED_WORK_TOKEN',
        ]);

        Livewire::test(OptionsWorkSearch::class)
            ->set('selectedProductIds', [$selected->id])
            ->set('search', 'VISIBLE_FILTERED')
            ->assertSee('VISIBLE_FILTERED_WORK_TOKEN')
            ->assertDontSee('SELECTED_WORK_TOKEN')
            ->assertSee('<input type="hidden" name="product_ids[]" value="' . $selected->id . '">', false);
    }


    public function test_livewire_work_search_uses_lightweight_title_projections(): void
    {
        Product::factory()->withTitles('Projected Japanese title', 'Projected English title')->create([
            'id' => 'RJ555555555',
        ]);

        Livewire::test(OptionsWorkSearch::class)
            ->set('search', 'Projected Japanese')
            ->assertViewHas('products', function ($products): bool {
                $row = $products->sole();

                return ! $row instanceof Product
                    && $row->id === 'RJ555555555'
                    && $row->japanese_title === 'Projected Japanese title'
                    && $row->english_title === 'Projected English title';
            });
    }

    public function test_livewire_work_search_projection_returns_null_for_missing_or_blank_english_titles(): void
    {
        $nullEnglish = Product::factory()->withEnglishTitle(null)->create(['id' => 'RJ555555556']);
        $emptyEnglish = Product::factory()->withEnglishTitle('')->create(['id' => 'RJ555555557']);

        Livewire::test(OptionsWorkSearch::class)
            ->assertViewHas('products', function ($products) use ($nullEnglish, $emptyEnglish): bool {
                $rows = $products->keyBy('id');

                return $rows->get($nullEnglish->id)->english_title === null
                    && $rows->get($emptyEnglish->id)->english_title === null;
            });
    }

    public function test_image_choice_is_preserved_while_search_rerenders(): void
    {
        Product::factory()->create(['work_name' => 'IMAGE_SEARCH_TOKEN']);

        Livewire::test(OptionsWorkSearch::class)
            ->set('checkImages', true)
            ->set('search', 'IMAGE_SEARCH')
            ->assertSet('checkImages', true);
    }
}
