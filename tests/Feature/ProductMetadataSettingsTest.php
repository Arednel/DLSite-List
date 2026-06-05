<?php

namespace Tests\Feature;

use App\Enums\AutocompleteOrder;
use App\Enums\ProductField;
use App\Enums\ProductIndexSortField;
use App\Livewire\AutoSeriesSettings;
use App\Livewire\IndexTableWidthSettings;
use App\Livewire\OptionsResetDefaults;
use App\Livewire\ProductFieldLayoutSettings;
use App\Models\Option;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ProductMetadataSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_series_setting_component_persists_toggle(): void
    {
        Livewire::test(AutoSeriesSettings::class)
            ->assertSet('enabled', true)
            ->assertSee('wire:click="askResetToDefault"', false)
            ->assertDontSee('wire:confirm', false)
            ->assertSee('option-reset-button', false)
            ->assertSeeInOrder(['Save auto-series setting', 'Reset to default'])
            ->set('enabled', false)
            ->call('save')
            ->assertSet('saved', true);

        $this->assertFalse(Option::autoSeriesFromTitleName());
    }

    public function test_auto_series_setting_component_resets_to_default(): void
    {
        Livewire::test(AutoSeriesSettings::class)
            ->set('enabled', false)
            ->call('save')
            ->call('askResetToDefault')
            ->assertSet('confirmingResetToDefault', true)
            ->call('resetToDefault')
            ->assertSet('confirmingResetToDefault', false)
            ->assertSet('enabled', true)
            ->assertSee('Auto-series setting reset to default.');

        $this->assertTrue(Option::autoSeriesFromTitleName());
    }

    public function test_table_width_component_saves_fixed_and_custom_widths(): void
    {
        Livewire::test(IndexTableWidthSettings::class)
            ->set('mode', Option::INDEX_TABLE_WIDTH_WIDE)
            ->call('save')
            ->assertSet('saved', true);

        $this->assertSame('1400px', Option::indexTableWidthCss());

        Livewire::test(IndexTableWidthSettings::class)
            ->set('mode', Option::INDEX_TABLE_WIDTH_CUSTOM)
            ->set('custom', '72vw')
            ->call('save')
            ->assertSet('mode', Option::INDEX_TABLE_WIDTH_CUSTOM)
            ->assertSet('custom', '72vw');

        $this->assertSame('72vw', Option::indexTableWidthCss());
    }

    public function test_table_width_component_resets_to_default(): void
    {
        Livewire::test(IndexTableWidthSettings::class)
            ->set('mode', Option::INDEX_TABLE_WIDTH_CUSTOM)
            ->set('custom', '72vw')
            ->call('save')
            ->call('askResetToDefault')
            ->assertSet('confirmingResetToDefault', true)
            ->call('resetToDefault')
            ->assertSet('confirmingResetToDefault', false)
            ->assertSet('mode', Option::INDEX_TABLE_WIDTH_DEFAULT)
            ->assertSet('custom', '')
            ->assertSee('Index table width reset to default.');

        $this->assertSame('1024px', Option::indexTableWidthCss());
    }

    public function test_table_width_component_rejects_invalid_custom_width(): void
    {
        Livewire::test(IndexTableWidthSettings::class)
            ->set('mode', Option::INDEX_TABLE_WIDTH_CUSTOM)
            ->set('custom', 'calc(100%)')
            ->call('save')
            ->assertHasErrors(['custom']);

        $this->assertSame('1024px', Option::indexTableWidthCss());
    }

    public function test_settings_reset_helpers_clear_validation_and_close_confirmation(): void
    {
        Livewire::test(IndexTableWidthSettings::class)
            ->set('mode', Option::INDEX_TABLE_WIDTH_CUSTOM)
            ->set('custom', 'calc(100%)')
            ->call('save')
            ->assertHasErrors(['custom'])
            ->call('askResetToDefault')
            ->assertSet('confirmingResetToDefault', true)
            ->assertHasNoErrors(['custom'])
            ->call('resetToDefault')
            ->assertSet('confirmingResetToDefault', false)
            ->assertSet('saved', true)
            ->assertHasNoErrors()
            ->assertSee('Index table width reset to default.');
    }

    public function test_shared_reset_confirmation_modal_markup_supports_immediate_and_countdown_resets(): void
    {
        Livewire::test(IndexTableWidthSettings::class)
            ->assertSee('wire:click="askResetToDefault"', false)
            ->assertDontSee('wire:confirm', false)
            ->assertSee('option-reset-button', false)
            ->assertSeeInOrder(['Save table width', 'Reset to default'])
            ->call('askResetToDefault')
            ->assertSet('confirmingResetToDefault', true)
            ->assertSee('x-teleport="body"', false)
            ->assertSee('wire:click.self="cancelResetToDefault"', false)
            ->assertSee('wire:keydown.escape.window="cancelResetToDefault"', false)
            ->assertDontSee('x-on:keydown.escape.window', false)
            ->assertSee('options-modal-confirm--danger', false)
            ->assertSee('data-options-reset-delay="0"', false)
            ->assertDontSee('x-bind:disabled="!ready"', false)
            ->assertSee('wire:click="resetToDefault"', false)
            ->assertSee('Are you sure?')
            ->assertSee('Reset this setting to its default?');

        Livewire::test(OptionsResetDefaults::class)
            ->assertSee('Reset All Options')
            ->assertSee('wire:click="askResetToDefault"', false)
            ->assertDontSee('wire:confirm', false)
            ->assertSee('option-reset-button', false)
            ->call('askResetToDefault')
            ->assertSet('confirmingResetToDefault', true)
            ->assertSee('x-teleport="body"', false)
            ->assertSee('wire:click.self="cancelResetToDefault"', false)
            ->assertSee('wire:keydown.escape.window="cancelResetToDefault"', false)
            ->assertSee('options-modal-confirm--danger', false)
            ->assertSee('data-options-reset-delay="3"', false)
            ->assertSee('disabled', false)
            ->assertSee('x-bind:disabled="!ready"', false)
            ->assertSee('wire:click="resetAll"', false)
            ->assertSee('x-text="remaining">3</span>', false)
            ->assertSee('Are you sure?')
            ->assertSee('Reset all Options settings to their defaults?');
    }

    public function test_field_layout_component_saves_visibility_editability_and_order(): void
    {
        Livewire::test(ProductFieldLayoutSettings::class)
            ->assertSee('Custom Tags')
            ->assertSee('Fetched EN Tags')
            ->assertSee('Quick Add')
            ->assertSee('Custom Quick Add')
            ->assertSee('Index Sort Fields')
            ->assertSee('Required')
            ->assertSee('Notes are already shown inside Title; enable this for a separate column.')
            ->assertSee('field-layout-edit-stack', false)
            ->call('move', 'indexOrder', 11, -1)
            ->call('move', 'quickAddOrder', 11, -1)
            ->call('move', 'sortOrder', 2, -1)
            ->set('indexFields.score.visible', false)
            ->set('editFields.voice_actor.visible', true)
            ->set('editFields.voice_actor.editable', true)
            ->set('editFields.notes.visible', false)
            ->set('editFields.tags.fetched_editable', true)
            ->set('quickAddFields.notes.visible', false)
            ->set('customQuickAddFields.sample_images.visible', false)
            ->set('sortFields.score.visible', false)
            ->call('save')
            ->assertSet('saved', true);

        $this->assertFalse(collect(Option::indexFieldLayout())->firstWhere('field', ProductField::Score->value)['visible']);
        $this->assertTrue(collect(Option::editFieldLayout())->firstWhere('field', ProductField::VoiceActor->value)['editable']);
        $this->assertFalse(collect(Option::editFieldLayout())->firstWhere('field', ProductField::Notes->value)['visible']);
        $this->assertTrue(collect(Option::editFieldLayout())->firstWhere('field', ProductField::Tags->value)['fetched_editable']);
        $this->assertFalse(collect(Option::quickAddFieldLayout())->firstWhere('field', ProductField::Notes->value)['visible']);
        $this->assertSame(ProductField::Priority->value, Option::quickAddFieldLayout()[10]['field']);
        $this->assertFalse(collect(Option::customQuickAddFieldLayout())->firstWhere('field', ProductField::SampleImages->value)['visible']);
        $this->assertFalse(collect(Option::indexSortFieldLayout())->firstWhere('field', ProductIndexSortField::Score->value)['visible']);
        $this->assertSame(ProductIndexSortField::Series->value, Option::indexSortFieldLayout()[1]['field']);
    }

    public function test_field_layout_component_supports_drag_reordering(): void
    {
        Livewire::test(ProductFieldLayoutSettings::class)
            ->assertSee('wire:sort="reorderLayout"', false)
            ->assertSee('wire:sort:item="indexOrder|score"', false)
            ->assertSee('wire:sort:item="indexOrder|title"', false)
            ->assertSee('wire:model.live="indexFields.title.visible"', false)
            ->assertSee('wire:model.live="quickAddFields.rj_code.visible"', false)
            ->assertSee('wire:model.live="customQuickAddFields.image.visible"', false)
            ->assertSee('wire:sort:item="sortOrder|score"', false)
            ->assertSee('wire:model.live="sortFields.score.visible"', false)
            ->assertSee('disabled', false)
            ->assertSee('wire:sort:handle', false)
            ->assertSee('wire:sort:ignore', false)
            ->assertSee('wire:model.live="indexFields.score.visible"', false)
            ->assertSee('wire:model.live="editFields.tags.fetched_editable"', false)
            ->assertDontSee('wire:model.live="indexLayout.0.visible"', false)
            ->assertSee('wire:click.stop="move(', false)
            ->assertSee('field-layout-drag-handle', false)
            ->assertSee('fa-solid fa-arrows-up-down', false)
            ->call('reorderLayout', 'indexOrder|' . ProductField::Description->value, 0)
            ->assertSet('indexOrder.0', ProductField::Description->value)
            ->call('reorderLayout', 'editOrder|' . ProductField::Tags->value, 0)
            ->assertSet('editOrder.0', ProductField::Tags->value)
            ->call('reorderLayout', 'filterOrder|' . ProductField::VoiceActor->value, 0)
            ->assertSet('filterOrder.0', ProductField::VoiceActor->value)
            ->call('reorderLayout', 'quickAddOrder|' . ProductField::Priority->value, 1)
            ->assertSet('quickAddOrder.1', ProductField::Priority->value)
            ->call('reorderLayout', 'customQuickAddOrder|' . ProductField::SampleImages->value, 1)
            ->assertSet('customQuickAddOrder.1', ProductField::SampleImages->value)
            ->call('reorderLayout', 'sortOrder|' . ProductIndexSortField::AddedToTheSiteDate->value, 1)
            ->assertSet('sortOrder.1', ProductIndexSortField::AddedToTheSiteDate->value)
            ->call('save')
            ->assertSet('saved', true);

        $this->assertSame(ProductField::Description->value, Option::indexFieldLayout()[0]['field']);
        $this->assertSame(ProductField::Tags->value, Option::editFieldLayout()[0]['field']);
        $this->assertSame(ProductField::VoiceActor->value, Option::filterFieldLayout()[0]['field']);
        $this->assertSame(ProductField::Priority->value, Option::quickAddFieldLayout()[1]['field']);
        $this->assertSame(ProductField::SampleImages->value, Option::customQuickAddFieldLayout()[1]['field']);
        $this->assertSame(ProductIndexSortField::AddedToTheSiteDate->value, Option::indexSortFieldLayout()[1]['field']);
    }

    public function test_edit_title_layout_row_is_locked_visible_without_editability_toggle(): void
    {
        Livewire::test(ProductFieldLayoutSettings::class)
            ->assertSet('editFields.title.visible', true)
            ->assertSet('editFields.title.visibility_locked', true)
            ->assertSet('editFields.title.editable', true)
            ->assertSee('wire:model.live="editFields.title.visible"', false)
            ->assertDontSee('wire:model.live="editFields.title.editable"', false);
    }

    public function test_quick_add_required_layout_rows_are_locked_visible(): void
    {
        Livewire::test(ProductFieldLayoutSettings::class)
            ->assertSet('quickAddFields.rj_code.visible', true)
            ->assertSet('quickAddFields.rj_code.visibility_locked', true)
            ->assertSet('customQuickAddFields.rj_code.visibility_locked', true)
            ->assertSet('customQuickAddFields.title.visibility_locked', true)
            ->assertSet('customQuickAddFields.age_category.visibility_locked', true)
            ->assertSet('customQuickAddFields.image.visibility_locked', true)
            ->set('quickAddFields.rj_code.visible', false)
            ->set('customQuickAddFields.title.visible', false)
            ->call('save')
            ->assertSet('quickAddFields.rj_code.visible', true)
            ->assertSet('customQuickAddFields.title.visible', true);

        $this->assertTrue(collect(Option::quickAddFieldLayout())->firstWhere('field', ProductField::RjCode->value)['visible']);
        $this->assertTrue(collect(Option::customQuickAddFieldLayout())->firstWhere('field', ProductField::Title->value)['visible']);
    }

    public function test_field_layout_drag_reordering_ignores_invalid_fields_and_clamps_positions(): void
    {
        $component = Livewire::test(ProductFieldLayoutSettings::class);
        $originalOrder = $component->get('indexOrder');
        $originalFields = $component->get('indexFields');

        $component
            ->call('reorderLayout', 'notARealOrder|' . ProductField::VoiceActor->value, 0)
            ->assertSet('indexOrder', $originalOrder)
            ->assertSet('indexFields', $originalFields)
            ->call('reorderLayout', 'indexOrder|not_a_field', 0)
            ->assertSet('indexOrder', $originalOrder)
            ->assertSet('indexFields', $originalFields)
            ->call('reorderLayout', ProductField::Tags->value, 999)
            ->assertSet('indexOrder', $originalOrder)
            ->assertSet('indexFields', $originalFields);

        $this->assertSame($originalOrder, $component->get('indexOrder'));
        $this->assertSame($originalFields, $component->get('indexFields'));

        $component
            ->call('reorderLayout', 'indexOrder|' . ProductField::Tags->value, 999)
            ->assertSet('indexOrder.' . (count($originalOrder) - 1), ProductField::Tags->value)
            ->call('reorderLayout', 'indexOrder|' . ProductField::Tags->value, -5)
            ->assertSet('indexOrder.0', ProductField::Tags->value);
    }

    public function test_field_layout_reordering_preserves_field_keyed_checkbox_state(): void
    {
        Livewire::test(ProductFieldLayoutSettings::class)
            ->set('indexFields.description.visible', true)
            ->call('move', 'indexOrder', 11, -1)
            ->assertSet('indexFields.description.visible', true)
            ->assertSet('indexOrder.10', ProductField::Description->value)
            ->call('reorderLayout', 'indexOrder|' . ProductField::Description->value, 0)
            ->assertSet('indexFields.description.visible', true)
            ->assertSet('indexOrder.0', ProductField::Description->value)
            ->call('save')
            ->assertSet('saved', true);

        $descriptionRow = collect(Option::indexFieldLayout())->firstWhere('field', ProductField::Description->value);

        $this->assertTrue($descriptionRow['visible']);
        $this->assertSame(ProductField::Description->value, Option::indexFieldLayout()[0]['field']);
    }

    public function test_global_reset_all_options_resets_visible_settings_and_keeps_unrelated_options(): void
    {
        Option::setIndexPerPage(250);
        Option::setIndexTableWidth([
            'mode' => Option::INDEX_TABLE_WIDTH_CUSTOM,
            'custom' => '72vw',
        ]);
        Option::setIndexFieldLayout([
            ['field' => ProductField::Score->value, 'visible' => false],
        ]);
        Option::setEditFieldLayout([
            ['field' => ProductField::Tags->value, 'visible' => true, 'editable' => true, 'fetched_editable' => true],
        ]);
        Option::setQuickAddFieldLayout([
            ['field' => ProductField::Notes->value, 'visible' => false],
        ]);
        Option::setCustomQuickAddFieldLayout([
            ['field' => ProductField::SampleImages->value, 'visible' => false],
        ]);
        Option::setIndexSortFieldLayout([
            ['field' => ProductIndexSortField::Series->value, 'visible' => false],
        ]);
        Option::setAutoSeriesFromTitleName(false);
        Option::setTagAutocompleteOrder(AutocompleteOrder::FirstWord);
        Option::setSeriesAutocompleteOrder(AutocompleteOrder::FirstWord);
        Option::query()->create([
            'key' => 'unrelated_option',
            'value' => 'keep-me',
        ]);

        Livewire::test(OptionsResetDefaults::class)
            ->call('askResetToDefault')
            ->assertSet('confirmingResetToDefault', true)
            ->call('cancelResetToDefault')
            ->assertSet('confirmingResetToDefault', false);

        $this->assertSame(250, Option::indexPerPage());

        Livewire::test(OptionsResetDefaults::class)
            ->call('askResetToDefault')
            ->call('resetAll')
            ->assertDispatched('options-defaults-reset')
            ->assertSet('confirmingResetToDefault', false)
            ->assertSet('saved', true)
            ->assertSee('All Options settings reset to defaults.');

        $this->assertSame(Option::DEFAULT_INDEX_PER_PAGE, Option::indexPerPage());
        $this->assertSame('1024px', Option::indexTableWidthCss());
        $this->assertTrue(Option::autoSeriesFromTitleName());
        $this->assertSame(AutocompleteOrder::Usage, Option::tagAutocompleteOrder());
        $this->assertSame(AutocompleteOrder::Usage, Option::seriesAutocompleteOrder());
        $this->assertSame(ProductField::Image->value, Option::indexFieldLayout()[0]['field']);
        $this->assertTrue(Option::indexFieldLayout()[0]['visible']);
        $this->assertSame(ProductField::Title->value, Option::indexFieldLayout()[1]['field']);
        $this->assertTrue(Option::indexFieldLayout()[1]['visible']);
        $this->assertTrue(Option::indexFieldLayout()[1]['visibility_locked']);
        $this->assertFalse(collect(Option::editFieldLayout())->firstWhere('field', ProductField::Tags->value)['fetched_editable']);
        $this->assertSame(ProductField::RjCode->value, Option::quickAddFieldLayout()[0]['field']);
        $this->assertTrue(Option::quickAddFieldLayout()[0]['visibility_locked']);
        $this->assertTrue(collect(Option::quickAddFieldLayout())->firstWhere('field', ProductField::Notes->value)['visible']);
        $this->assertTrue(collect(Option::customQuickAddFieldLayout())->firstWhere('field', ProductField::SampleImages->value)['visible']);
        $this->assertSame(ProductIndexSortField::RJ->value, Option::indexSortFieldLayout()[0]['field']);
        $this->assertTrue(collect(Option::indexSortFieldLayout())->firstWhere('field', ProductIndexSortField::Series->value)['visible']);
        $this->assertSame('keep-me', DB::table('options')->where('key', 'unrelated_option')->value('value'));
    }

    public function test_settings_component_refreshes_from_settings_after_global_reset_event(): void
    {
        Option::setAutoSeriesFromTitleName(false);

        $component = Livewire::test(AutoSeriesSettings::class)
            ->assertSet('enabled', false);

        Option::resetAutoSeriesFromTitleNameToDefault();

        $component
            ->call('refreshFromSettings')
            ->assertSet('enabled', true)
            ->assertSet('saved', false)
            ->assertDontSee('Auto-series setting saved.');
    }

    public function test_options_page_uses_field_layout_for_tag_editing(): void
    {
        $this->get('/options')
            ->assertOk()
            ->assertSee('Field Layouts')
            ->assertSee('Fetched EN Tags')
            ->assertDontSee('Enable editing fetched English tags')
            ->assertDontSee('Choose whether fetched English tags can be changed from the Edit Work page.');
    }
}
