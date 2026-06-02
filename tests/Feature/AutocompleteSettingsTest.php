<?php

namespace Tests\Feature;

use App\Enums\AutocompleteOrder;
use App\Livewire\AutocompleteSettings;
use App\Models\Option;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class AutocompleteSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_component_defaults_to_usage_order_without_saved_options(): void
    {
        Livewire::test(AutocompleteSettings::class)
            ->assertSet('tagOrder', AutocompleteOrder::Usage->value)
            ->assertSet('seriesOrder', AutocompleteOrder::Usage->value)
            ->assertSee('Tag suggestions')
            ->assertSee('Series suggestions')
            ->assertSee('Most used first orders all matching tags by attached work count.', false)
            ->assertSee('Most used first orders all matching series by work count.', false);

        $this->assertSame(AutocompleteOrder::Usage, Option::tagAutocompleteOrder());
        $this->assertSame(AutocompleteOrder::Usage, Option::seriesAutocompleteOrder());
    }

    public function test_settings_component_saves_tag_and_series_order_separately(): void
    {
        Livewire::test(AutocompleteSettings::class)
            ->set('tagOrder', AutocompleteOrder::FirstWord->value)
            ->set('seriesOrder', AutocompleteOrder::Usage->value)
            ->call('save')
            ->assertSet('tagOrder', AutocompleteOrder::FirstWord->value)
            ->assertSet('seriesOrder', AutocompleteOrder::Usage->value)
            ->assertSee('Autocomplete settings saved.');

        $this->assertSame(AutocompleteOrder::FirstWord, Option::tagAutocompleteOrder());
        $this->assertSame(AutocompleteOrder::Usage, Option::seriesAutocompleteOrder());
        $this->assertSame(
            AutocompleteOrder::FirstWord->value,
            DB::table('options')->where('key', Option::TAG_AUTOCOMPLETE_ORDER)->value('value')
        );
        $this->assertSame(
            AutocompleteOrder::Usage->value,
            DB::table('options')->where('key', Option::SERIES_AUTOCOMPLETE_ORDER)->value('value')
        );

        Livewire::test(AutocompleteSettings::class)
            ->set('tagOrder', AutocompleteOrder::Usage->value)
            ->set('seriesOrder', AutocompleteOrder::FirstWord->value)
            ->call('save');

        $this->assertSame(AutocompleteOrder::Usage, Option::tagAutocompleteOrder());
        $this->assertSame(AutocompleteOrder::FirstWord, Option::seriesAutocompleteOrder());
    }

    public function test_settings_component_rejects_invalid_order_values(): void
    {
        Livewire::test(AutocompleteSettings::class)
            ->set('tagOrder', 'unknown')
            ->call('save')
            ->assertHasErrors(['tagOrder']);

        $this->assertSame(AutocompleteOrder::Usage, Option::tagAutocompleteOrder());
        $this->assertSame(AutocompleteOrder::Usage, Option::seriesAutocompleteOrder());
    }

    public function test_settings_component_resets_to_default_with_confirmation(): void
    {
        Livewire::test(AutocompleteSettings::class)
            ->set('tagOrder', AutocompleteOrder::FirstWord->value)
            ->set('seriesOrder', AutocompleteOrder::FirstWord->value)
            ->call('save')
            ->call('askResetToDefault')
            ->assertSet('confirmingResetToDefault', true)
            ->call('resetToDefault')
            ->assertSet('confirmingResetToDefault', false)
            ->assertSet('tagOrder', AutocompleteOrder::Usage->value)
            ->assertSet('seriesOrder', AutocompleteOrder::Usage->value)
            ->assertSee('Autocomplete settings reset to default.');

        $this->assertSame(AutocompleteOrder::Usage, Option::tagAutocompleteOrder());
        $this->assertSame(AutocompleteOrder::Usage, Option::seriesAutocompleteOrder());
    }

    public function test_settings_component_clears_saved_notice_when_inputs_change(): void
    {
        Livewire::test(AutocompleteSettings::class)
            ->set('tagOrder', AutocompleteOrder::FirstWord->value)
            ->call('save')
            ->assertSet('saved', true)
            ->assertSee('Autocomplete settings saved.')
            ->set('seriesOrder', AutocompleteOrder::FirstWord->value)
            ->assertSet('saved', false)
            ->assertDontSee('Autocomplete settings saved.');
    }
}
