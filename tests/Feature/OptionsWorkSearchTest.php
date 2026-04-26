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
        Product::factory()->create([
            'id' => 'RJ111111111',
            'work_name' => 'VISIBLE_WORK_TOKEN',
            'work_name_english' => 'VISIBLE_ENGLISH_TOKEN',
        ]);
        Product::factory()->create([
            'id' => 'RJ222222222',
            'work_name' => 'HIDDEN_WORK_TOKEN',
            'work_name_english' => 'HIDDEN_ENGLISH_TOKEN',
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
            ->assertSee('<input type="hidden" name="product_ids[]" value="'.$selected->id.'">', false);
    }
}
