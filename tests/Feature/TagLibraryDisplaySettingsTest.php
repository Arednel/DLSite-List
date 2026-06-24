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

    public function test_tag_library_settings_render_inline_helper_icons(): void
    {
        $html = Livewire::test(TagLibraryDisplaySettings::class)
            ->assertSee('Open Tag Library with all tags shown')
            ->assertSee('Enable group ordering on Index')
            ->assertSee('When enabled, Tag Library opens with the All Tags list expanded instead of collapsed.')
            ->assertSee('When enabled, Index tag chips use saved group and membership order instead of plain tag order and title.')
            ->assertDontSee('option-setting-description', false)
            ->html();

        $this->assertSame(2, substr_count($html, 'fa-solid fa-circle-question'));
    }

    public function test_tag_library_display_setting_hydrates_from_option_and_saves_changes(): void
    {
        Option::setTagLibraryTagsExpandedByDefault(true);
        Option::setTagLibraryIndexGroupOrderingEnabled(false);

        Livewire::test(TagLibraryDisplaySettings::class)
            ->assertSet('expandedByDefault', true)
            ->assertSet('indexGroupOrderingEnabled', false)
            ->assertSet('saved', false)
            ->assertSet('notice', '')
            ->set('expandedByDefault', false)
            ->set('indexGroupOrderingEnabled', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('expandedByDefault', false)
            ->assertSet('indexGroupOrderingEnabled', true)
            ->assertSet('saved', true)
            ->assertSet('notice', 'Tag Library settings saved.');

        $this->assertFalse(Option::tagLibraryTagsExpandedByDefault());
        $this->assertTrue(Option::tagLibraryIndexGroupOrderingEnabled());
    }

    public function test_tag_library_display_setting_resets_to_default_after_confirmation(): void
    {
        Option::setTagLibraryTagsExpandedByDefault(true);
        Option::setTagLibraryIndexGroupOrderingEnabled(true);

        Livewire::test(TagLibraryDisplaySettings::class)
            ->assertSet('expandedByDefault', true)
            ->assertSet('indexGroupOrderingEnabled', true)
            ->call('askResetToDefault')
            ->assertSet('confirmingResetToDefault', true)
            ->call('resetToDefault')
            ->assertHasNoErrors()
            ->assertSet('confirmingResetToDefault', false)
            ->assertSet('expandedByDefault', false)
            ->assertSet('indexGroupOrderingEnabled', false)
            ->assertSet('saved', true)
            ->assertSet('notice', 'Tag Library settings reset to default.');

        $this->assertFalse(Option::tagLibraryTagsExpandedByDefault());
        $this->assertFalse(Option::tagLibraryIndexGroupOrderingEnabled());
    }

    public function test_tag_library_display_setting_refreshes_group_ordering_after_global_reset(): void
    {
        Option::setTagLibraryTagsExpandedByDefault(true);
        Option::setTagLibraryIndexGroupOrderingEnabled(true);

        $component = Livewire::test(TagLibraryDisplaySettings::class)
            ->assertSet('expandedByDefault', true)
            ->assertSet('indexGroupOrderingEnabled', true);

        Option::resetVisibleSettingsToDefault();

        $component
            ->call('refreshFromSettings')
            ->assertSet('expandedByDefault', false)
            ->assertSet('indexGroupOrderingEnabled', false)
            ->assertSet('saved', false)
            ->assertSet('notice', '');
    }
}
