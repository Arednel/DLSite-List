<?php

namespace Tests\Feature;

use App\Enums\ProductProgress;
use App\Enums\UiLanguage;
use App\Livewire\ProductIndex;
use App\Models\Option;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;
use Tests\TestCase;

class LocalizedPhpUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_month_options_follow_the_saved_locale_without_changing_values(): void
    {
        $this->get(route('products.create'))
            ->assertOk()
            ->assertSee('<option value="1"', false)
            ->assertSee('Jan');

        Option::setUiLanguage(UiLanguage::Japanese);

        $this->get(route('products.create'))
            ->assertOk()
            ->assertSee('<option value="1"', false)
            ->assertSee('1月')
            ->assertDontSee('Jan');
    }

    public function test_localized_progress_heading_keeps_selection_bound_to_the_stable_value(): void
    {
        Option::setUiLanguage(UiLanguage::Japanese);
        App::setLocale(UiLanguage::Japanese->value);

        Livewire::withQueryParams(['progress' => ProductProgress::Listening->value])
            ->test(ProductIndex::class)
            ->assertSee('聴取中')
            ->assertSee('progress-listening on', false)
            ->assertSet('progress', ProductProgress::Listening->value);
    }

    public function test_stored_age_and_progress_values_render_through_localized_enum_labels(): void
    {
        Option::setUiLanguage(UiLanguage::Japanese);
        App::setLocale(UiLanguage::Japanese->value);
        $product = Product::factory()->create([
            'age_category' => 'ALL_AGES',
            'progress' => ProductProgress::Listening->value,
        ]);

        Livewire::test(ProductIndex::class)
            ->assertSee('全年齢')
            ->assertSee('聴取中');

        $this->assertSame('ALL_AGES', $product->refresh()->age_category);
        $this->assertSame(ProductProgress::Listening->value, $product->progress);
    }
}
