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
            ->assertSee('Tag color surfaces')
            ->assertSee('When enabled, Tag Library opens with the All Tags list expanded instead of collapsed.')
            ->assertSee('When enabled, Index tag chips use saved group and membership order instead of plain tag order and title.')
            ->assertDontSee('option-setting-description', false)
            ->html();

        $this->assertSame(3, substr_count($html, 'fa-solid fa-circle-question'));
    }

    public function test_tag_library_display_setting_hydrates_from_option_and_saves_changes(): void
    {
        Option::setTagLibraryTagsExpandedByDefault(true);
        Option::setTagLibraryIndexGroupOrderingEnabled(false);
        Option::setTagColorSurfaces([
            'index' => true,
            'tag_library' => false,
            'autocomplete' => false,
            'edit_readonly' => false,
            'refetch' => false,
        ]);

        Livewire::test(TagLibraryDisplaySettings::class)
            ->assertSet('expandedByDefault', true)
            ->assertSet('indexGroupOrderingEnabled', false)
            ->assertSet('colorSurfaces.index', true)
            ->assertSet('colorSurfaces.tag_library', false)
            ->assertSet('colorSurfaces.autocomplete', false)
            ->assertSet('saved', false)
            ->assertSet('notice', '')
            ->set('expandedByDefault', false)
            ->set('indexGroupOrderingEnabled', true)
            ->set('colorSurfaces.index', false)
            ->set('colorSurfaces.tag_library', true)
            ->set('colorSurfaces.autocomplete', true)
            ->set('colorSurfaces.edit_readonly', true)
            ->set('colorSurfaces.refetch', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('expandedByDefault', false)
            ->assertSet('indexGroupOrderingEnabled', true)
            ->assertSet('colorSurfaces.index', false)
            ->assertSet('colorSurfaces.tag_library', true)
            ->assertSet('colorSurfaces.autocomplete', true)
            ->assertSet('colorSurfaces.edit_readonly', true)
            ->assertSet('colorSurfaces.refetch', true)
            ->assertSet('saved', true)
            ->assertSet('notice', 'Tag Library settings saved.');

        $this->assertFalse(Option::tagLibraryTagsExpandedByDefault());
        $this->assertTrue(Option::tagLibraryIndexGroupOrderingEnabled());
        $this->assertSame([
            'index' => false,
            'tag_library' => true,
            'autocomplete' => true,
            'edit_readonly' => true,
            'refetch' => true,
        ], Option::tagColorSurfaces());
    }

    public function test_tag_library_display_setting_resets_to_default_after_confirmation(): void
    {
        Option::setTagLibraryTagsExpandedByDefault(true);
        Option::setTagLibraryIndexGroupOrderingEnabled(true);
        Option::setTagColorSurfaces([
            'index' => false,
            'tag_library' => false,
            'autocomplete' => true,
            'edit_readonly' => true,
            'refetch' => true,
        ]);

        Livewire::test(TagLibraryDisplaySettings::class)
            ->assertSet('expandedByDefault', true)
            ->assertSet('indexGroupOrderingEnabled', true)
            ->assertSet('colorSurfaces.index', false)
            ->assertSet('colorSurfaces.autocomplete', true)
            ->call('askResetToDefault')
            ->assertSet('confirmingResetToDefault', true)
            ->call('resetToDefault')
            ->assertHasNoErrors()
            ->assertSet('confirmingResetToDefault', false)
            ->assertSet('expandedByDefault', false)
            ->assertSet('indexGroupOrderingEnabled', false)
            ->assertSet('colorSurfaces.index', true)
            ->assertSet('colorSurfaces.tag_library', true)
            ->assertSet('colorSurfaces.autocomplete', false)
            ->assertSet('colorSurfaces.edit_readonly', false)
            ->assertSet('colorSurfaces.refetch', false)
            ->assertSet('saved', true)
            ->assertSet('notice', 'Tag Library settings reset to default.');

        $this->assertFalse(Option::tagLibraryTagsExpandedByDefault());
        $this->assertFalse(Option::tagLibraryIndexGroupOrderingEnabled());
        $this->assertSame([
            'index' => true,
            'tag_library' => true,
            'autocomplete' => false,
            'edit_readonly' => false,
            'refetch' => false,
        ], Option::tagColorSurfaces());
    }

    public function test_tag_library_display_setting_refreshes_group_ordering_after_global_reset(): void
    {
        Option::setTagLibraryTagsExpandedByDefault(true);
        Option::setTagLibraryIndexGroupOrderingEnabled(true);
        Option::setTagColorSurfaces([
            'index' => false,
            'tag_library' => false,
            'autocomplete' => true,
            'edit_readonly' => true,
            'refetch' => true,
        ]);

        $component = Livewire::test(TagLibraryDisplaySettings::class)
            ->assertSet('expandedByDefault', true)
            ->assertSet('indexGroupOrderingEnabled', true)
            ->assertSet('colorSurfaces.index', false)
            ->assertSet('colorSurfaces.autocomplete', true);

        Option::resetVisibleSettingsToDefault();

        $component
            ->call('refreshFromSettings')
            ->assertSet('expandedByDefault', false)
            ->assertSet('indexGroupOrderingEnabled', false)
            ->assertSet('colorSurfaces.index', true)
            ->assertSet('colorSurfaces.tag_library', true)
            ->assertSet('colorSurfaces.autocomplete', false)
            ->assertSet('colorSurfaces.edit_readonly', false)
            ->assertSet('colorSurfaces.refetch', false)
            ->assertSet('saved', false)
            ->assertSet('notice', '');
    }
}
