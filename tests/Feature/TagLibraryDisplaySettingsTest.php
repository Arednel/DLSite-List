<?php

namespace Tests\Feature;

use App\Livewire\TagLibraryDisplaySettings;
use App\Models\Option;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TagLibraryDisplaySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tag_library_display_setting_hydrates_from_option_and_saves_changes(): void
    {
        Option::setTagLibraryTagsExpandedByDefault(true);

        Livewire::test(TagLibraryDisplaySettings::class)
            ->assertSet('expandedByDefault', true)
            ->assertSet('saved', false)
            ->assertSet('notice', '')
            ->set('expandedByDefault', false)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('expandedByDefault', false)
            ->assertSet('saved', true)
            ->assertSet('notice', 'Tag Library display setting saved.');

        $this->assertFalse(Option::tagLibraryTagsExpandedByDefault());
    }

    public function test_tag_library_display_setting_resets_to_default_after_confirmation(): void
    {
        Option::setTagLibraryTagsExpandedByDefault(true);

        Livewire::test(TagLibraryDisplaySettings::class)
            ->assertSet('expandedByDefault', true)
            ->call('askResetToDefault')
            ->assertSet('confirmingResetToDefault', true)
            ->call('resetToDefault')
            ->assertHasNoErrors()
            ->assertSet('confirmingResetToDefault', false)
            ->assertSet('expandedByDefault', false)
            ->assertSet('saved', true)
            ->assertSet('notice', 'Tag Library display setting reset to default.');

        $this->assertFalse(Option::tagLibraryTagsExpandedByDefault());
    }
}
