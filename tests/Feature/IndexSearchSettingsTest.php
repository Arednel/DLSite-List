<?php

namespace Tests\Feature;

use App\Livewire\IndexSearchSettings;
use App\Models\Option;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IndexSearchSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_hydrates_from_option_and_saves_changes(): void
    {
        Option::setIndexSearchHiddenDescriptionsEnabled(true);

        Livewire::test(IndexSearchSettings::class)
            ->assertSet('searchHiddenDescriptions', true)
            ->assertSee('Search hidden descriptions')
            ->assertSee('When enabled, general Index search can match description text even when the Description column is hidden.')
            ->set('searchHiddenDescriptions', false)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('searchHiddenDescriptions', false)
            ->assertSet('saved', true)
            ->assertSet('notice', 'Index search setting saved.');

        $this->assertFalse(Option::indexSearchHiddenDescriptionsEnabled());
    }

    public function test_it_resets_to_default_after_confirmation(): void
    {
        Option::setIndexSearchHiddenDescriptionsEnabled(true);

        Livewire::test(IndexSearchSettings::class)
            ->assertSet('searchHiddenDescriptions', true)
            ->call('askResetToDefault')
            ->assertSet('confirmingResetToDefault', true)
            ->call('resetToDefault')
            ->assertHasNoErrors()
            ->assertSet('confirmingResetToDefault', false)
            ->assertSet('searchHiddenDescriptions', false)
            ->assertSet('saved', true)
            ->assertSet('notice', 'Index search setting reset to default.');

        $this->assertFalse(Option::indexSearchHiddenDescriptionsEnabled());
    }

    public function test_it_refreshes_after_global_reset(): void
    {
        Option::setIndexSearchHiddenDescriptionsEnabled(true);

        $component = Livewire::test(IndexSearchSettings::class)
            ->assertSet('searchHiddenDescriptions', true);

        Option::resetVisibleSettingsToDefault();

        $component
            ->call('refreshFromSettings')
            ->assertSet('searchHiddenDescriptions', false)
            ->assertSet('saved', false)
            ->assertSet('notice', '');
    }
}
