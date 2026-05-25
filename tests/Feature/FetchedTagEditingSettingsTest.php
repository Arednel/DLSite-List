<?php

namespace Tests\Feature;

use App\Livewire\FetchedTagEditingSettings;
use App\Models\Option;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class FetchedTagEditingSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_component_defaults_to_disabled_without_saved_option(): void
    {
        Livewire::test(FetchedTagEditingSettings::class)
            ->assertSet('enabled', false)
            ->assertSee('Enable editing fetched English tags')
            ->assertSee('wire:model.change.live="enabled"', false);

        $this->assertFalse(Option::canEditFetchedTags());
    }

    public function test_settings_component_saves_enabled_and_disabled_values(): void
    {
        Livewire::test(FetchedTagEditingSettings::class)
            ->set('enabled', true)
            ->call('save')
            ->assertSet('enabled', true)
            ->assertSee('Fetched tag editing setting saved.');

        $this->assertTrue(Option::canEditFetchedTags());
        $this->assertSame('1', DB::table('options')->where('key', Option::EDIT_FETCHED_TAGS)->value('value'));

        Livewire::test(FetchedTagEditingSettings::class)
            ->set('enabled', false)
            ->call('save')
            ->assertSet('enabled', false);

        $this->assertFalse(Option::canEditFetchedTags());
        $this->assertSame('0', DB::table('options')->where('key', Option::EDIT_FETCHED_TAGS)->value('value'));
    }

    public function test_settings_component_clears_saved_notice_when_toggle_changes(): void
    {
        Livewire::test(FetchedTagEditingSettings::class)
            ->set('enabled', true)
            ->call('save')
            ->assertSet('saved', true)
            ->assertSee('Fetched tag editing setting saved.')
            ->set('enabled', false)
            ->assertSet('saved', false)
            ->assertDontSee('Fetched tag editing setting saved.');
    }
}
