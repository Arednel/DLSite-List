<?php

namespace Tests\Feature;

use App\Enums\AutocompleteOrder;
use App\Enums\ProductField;
use App\Livewire\AutoSeriesSettings;
use App\Livewire\AutocompleteSettings;
use App\Livewire\IndexTableWidthSettings;
use App\Livewire\IndexPaginationSettings;
use App\Livewire\OptionsResetDefaults;
use App\Livewire\ProductFieldLayoutSettings;
use App\Models\Option;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductMetadataSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_series_setting_hydrates_from_option_and_saves_changes(): void
    {
        Option::setAutoSeriesFromTitleName(false);

        Livewire::test(AutoSeriesSettings::class)
            ->assertSet('enabled', false)
            ->assertSet('saved', false)
            ->assertSet('notice', '')
            ->set('enabled', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('enabled', true)
            ->assertSet('saved', true)
            ->assertSet('notice', 'Auto-series setting saved.');

        $this->assertTrue(Option::autoSeriesFromTitleName());
    }

    public function test_auto_series_setting_clears_saved_notice_when_changed_after_save(): void
    {
        Livewire::test(AutoSeriesSettings::class)
            ->set('enabled', false)
            ->call('save')
            ->assertSet('saved', true)
            ->assertSet('notice', 'Auto-series setting saved.')
            ->set('enabled', true)
            ->assertSet('saved', false)
            ->assertSet('notice', '');
    }

    public function test_auto_series_setting_resets_to_default_after_confirmation(): void
    {
        Option::setAutoSeriesFromTitleName(false);

        Livewire::test(AutoSeriesSettings::class)
            ->assertSet('enabled', false)
            ->call('askResetToDefault')
            ->assertSet('confirmingResetToDefault', true)
            ->call('resetToDefault')
            ->assertHasNoErrors()
            ->assertSet('confirmingResetToDefault', false)
            ->assertSet('enabled', true)
            ->assertSet('saved', true)
            ->assertSet('notice', 'Auto-series setting reset to default.');

        $this->assertTrue(Option::autoSeriesFromTitleName());
    }

    public function test_auto_series_setting_refreshes_after_global_defaults_reset(): void
    {
        Option::setAutoSeriesFromTitleName(false);

        $component = Livewire::test(AutoSeriesSettings::class)
            ->assertSet('enabled', false)
            ->call('save')
            ->assertSet('saved', true);

        Option::resetAutoSeriesFromTitleNameToDefault();

        $component
            ->call('refreshFromSettings')
            ->assertSet('enabled', true)
            ->assertSet('saved', false)
            ->assertSet('notice', '');
    }

    #[DataProvider('fixedTableWidthProvider')]
    public function test_table_width_component_saves_fixed_width_modes(string $mode, string $expectedCss): void
    {
        Livewire::test(IndexTableWidthSettings::class)
            ->set('mode', $mode)
            ->set('custom', '72vw')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('mode', $mode)
            ->assertSet('custom', '')
            ->assertSet('saved', true)
            ->assertSet('notice', 'Index table width setting saved.');

        $this->assertSame($mode, Option::indexTableWidth()['mode']);
        $this->assertSame('', Option::indexTableWidth()['custom']);
        $this->assertSame($expectedCss, Option::indexTableWidthCss());
    }

    public function test_table_width_component_saves_custom_width(): void
    {
        Livewire::test(IndexTableWidthSettings::class)
            ->set('mode', Option::INDEX_TABLE_WIDTH_CUSTOM)
            ->set('custom', '72vw')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('mode', Option::INDEX_TABLE_WIDTH_CUSTOM)
            ->assertSet('custom', '72vw')
            ->assertSet('saved', true)
            ->assertSet('notice', 'Index table width setting saved.');

        $this->assertSame([
            'mode' => Option::INDEX_TABLE_WIDTH_CUSTOM,
            'custom' => '72vw',
        ], Option::indexTableWidth());
        $this->assertSame('72vw', Option::indexTableWidthCss());
    }

    #[DataProvider('invalidCustomWidthProvider')]
    public function test_table_width_component_rejects_invalid_custom_widths(string $customWidth): void
    {
        Option::setIndexTableWidth([
            'mode' => Option::INDEX_TABLE_WIDTH_DEFAULT,
            'custom' => '',
        ]);

        Livewire::test(IndexTableWidthSettings::class)
            ->set('mode', Option::INDEX_TABLE_WIDTH_CUSTOM)
            ->set('custom', $customWidth)
            ->call('save')
            ->assertHasErrors(['custom'])
            ->assertSet('saved', false)
            ->assertSet('notice', '');

        $this->assertSame(Option::INDEX_TABLE_WIDTH_DEFAULT, Option::indexTableWidth()['mode']);
        $this->assertSame('1024px', Option::indexTableWidthCss());
    }

    public function test_table_width_component_clears_validation_and_resets_to_default(): void
    {
        Option::setIndexTableWidth([
            'mode' => Option::INDEX_TABLE_WIDTH_CUSTOM,
            'custom' => '72vw',
        ]);

        Livewire::test(IndexTableWidthSettings::class)
            ->set('custom', 'calc(100%)')
            ->call('save')
            ->assertHasErrors(['custom'])
            ->call('askResetToDefault')
            ->assertHasNoErrors(['custom'])
            ->assertSet('confirmingResetToDefault', true)
            ->call('resetToDefault')
            ->assertHasNoErrors()
            ->assertSet('confirmingResetToDefault', false)
            ->assertSet('mode', Option::INDEX_TABLE_WIDTH_DEFAULT)
            ->assertSet('custom', '')
            ->assertSet('saved', true)
            ->assertSet('notice', 'Index table width reset to default.');

        $this->assertSame('1024px', Option::indexTableWidthCss());
    }

    public function test_table_width_component_can_cancel_reset_without_changing_saved_value(): void
    {
        Option::setIndexTableWidth([
            'mode' => Option::INDEX_TABLE_WIDTH_CUSTOM,
            'custom' => '72vw',
        ]);

        Livewire::test(IndexTableWidthSettings::class)
            ->call('askResetToDefault')
            ->assertSet('confirmingResetToDefault', true)
            ->call('cancelResetToDefault')
            ->assertSet('confirmingResetToDefault', false)
            ->assertSet('mode', Option::INDEX_TABLE_WIDTH_CUSTOM)
            ->assertSet('custom', '72vw');

        $this->assertSame('72vw', Option::indexTableWidthCss());
    }

    public function test_table_width_component_refreshes_after_global_defaults_reset(): void
    {
        Option::setIndexTableWidth([
            'mode' => Option::INDEX_TABLE_WIDTH_CUSTOM,
            'custom' => '72vw',
        ]);

        $component = Livewire::test(IndexTableWidthSettings::class)
            ->assertSet('mode', Option::INDEX_TABLE_WIDTH_CUSTOM)
            ->assertSet('custom', '72vw')
            ->call('save')
            ->assertSet('saved', true);

        Option::resetIndexTableWidthToDefault();

        $component
            ->call('refreshFromSettings')
            ->assertSet('mode', Option::INDEX_TABLE_WIDTH_DEFAULT)
            ->assertSet('custom', '')
            ->assertSet('saved', false)
            ->assertSet('notice', '');
    }

    public function test_field_layout_component_hydrates_expected_locked_rows(): void
    {
        Livewire::test(ProductFieldLayoutSettings::class)
            ->assertSet('editFields.title.visible', true)
            ->assertSet('editFields.title.visibility_locked', true)
            ->assertSet('editFields.title.editable', true)
            ->assertSet('quickAddFields.rj_code.visible', true)
            ->assertSet('quickAddFields.rj_code.visibility_locked', true)
            ->assertSet('customQuickAddFields.rj_code.visibility_locked', true)
            ->assertSet('customQuickAddFields.title.visibility_locked', true)
            ->assertSet('customQuickAddFields.age_category.visibility_locked', true)
            ->assertSet('customQuickAddFields.image.visibility_locked', true);
    }

    public function test_field_layout_component_saves_visibility_editability_and_fetched_tag_editability(): void
    {
        Livewire::test(ProductFieldLayoutSettings::class)
            ->set('indexFields.score.visible', false)
            ->set('editFields.voice_actor.visible', true)
            ->set('editFields.voice_actor.editable', true)
            ->set('editFields.notes.visible', false)
            ->set('editFields.tags.visible', true)
            ->set('editFields.tags.fetched_editable', true)
            ->set('quickAddFields.notes.visible', false)
            ->set('customQuickAddFields.sample_images.visible', false)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('saved', true)
            ->assertSet('notice', 'Field layouts saved.');

        $this->assertFalse($this->layoutRow(Option::indexFieldLayout(), ProductField::Score)['visible']);
        $this->assertTrue($this->layoutRow(Option::editFieldLayout(), ProductField::VoiceActor)['visible']);
        $this->assertTrue($this->layoutRow(Option::editFieldLayout(), ProductField::VoiceActor)['editable']);
        $this->assertFalse($this->layoutRow(Option::editFieldLayout(), ProductField::Notes)['visible']);
        $this->assertTrue($this->layoutRow(Option::editFieldLayout(), ProductField::Tags)['fetched_editable']);
        $this->assertFalse($this->layoutRow(Option::quickAddFieldLayout(), ProductField::Notes)['visible']);
        $this->assertFalse($this->layoutRow(Option::customQuickAddFieldLayout(), ProductField::SampleImages)['visible']);
    }

    public function test_field_layout_component_saves_custom_order_for_each_layout_surface(): void
    {
        $component = Livewire::test(ProductFieldLayoutSettings::class);

        $indexOrder = $this->moveFieldToPosition($component->get('indexOrder'), ProductField::Description, 0);
        $editOrder = $this->moveFieldToPosition($component->get('editOrder'), ProductField::Tags, 0);
        $filterOrder = $this->moveFieldToPosition($component->get('filterOrder'), ProductField::VoiceActor, 0);
        $quickAddOrder = $this->moveFieldToPosition($component->get('quickAddOrder'), ProductField::Priority, 1);
        $customQuickAddOrder = $this->moveFieldToPosition(
            $component->get('customQuickAddOrder'),
            ProductField::SampleImages,
            1,
        );

        $component
            ->set('indexOrder', $indexOrder)
            ->set('editOrder', $editOrder)
            ->set('filterOrder', $filterOrder)
            ->set('quickAddOrder', $quickAddOrder)
            ->set('customQuickAddOrder', $customQuickAddOrder)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('saved', true);

        $this->assertSame(ProductField::Description->value, Option::indexFieldLayout()[0]['field']);
        $this->assertSame(ProductField::Tags->value, Option::editFieldLayout()[0]['field']);
        $this->assertSame(ProductField::VoiceActor->value, Option::filterFieldLayout()[0]['field']);
        $this->assertSame(ProductField::Priority->value, Option::quickAddFieldLayout()[1]['field']);
        $this->assertSame(ProductField::SampleImages->value, Option::customQuickAddFieldLayout()[1]['field']);
    }

    public function test_field_layout_component_moves_rows_and_preserves_field_keyed_state(): void
    {
        $component = Livewire::test(ProductFieldLayoutSettings::class)
            ->set('indexFields.description.visible', true);

        $descriptionIndex = array_search(ProductField::Description->value, $component->get('indexOrder'), true);
        $this->assertIsInt($descriptionIndex);

        $direction = $descriptionIndex === 0 ? 1 : -1;
        $expectedIndex = $descriptionIndex + $direction;

        $component
            ->call('move', 'indexOrder', $descriptionIndex, $direction)
            ->assertSet('indexFields.description.visible', true)
            ->assertSet("indexOrder.{$expectedIndex}", ProductField::Description->value)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('saved', true);

        $descriptionRow = $this->layoutRow(Option::indexFieldLayout(), ProductField::Description);

        $this->assertTrue($descriptionRow['visible']);
        $this->assertSame(ProductField::Description->value, Option::indexFieldLayout()[$expectedIndex]['field']);
    }

    public function test_field_layout_component_ignores_invalid_move_requests(): void
    {
        $component = Livewire::test(ProductFieldLayoutSettings::class);
        $originalOrder = $component->get('indexOrder');
        $originalFields = $component->get('indexFields');

        $component
            ->call('move', 'notARealOrderProperty', 0, 1)
            ->assertSet('indexOrder', $originalOrder)
            ->assertSet('indexFields', $originalFields)
            ->call('move', 'indexOrder', -1, 1)
            ->assertSet('indexOrder', $originalOrder)
            ->assertSet('indexFields', $originalFields)
            ->call('move', 'indexOrder', count($originalOrder), -1)
            ->assertSet('indexOrder', $originalOrder)
            ->assertSet('indexFields', $originalFields);
    }

    public function test_field_layout_component_ignores_invalid_layout_names_when_moving_or_reading_rows(): void
    {
        $component = Livewire::test(ProductFieldLayoutSettings::class);
        $originalOrder = $component->get('indexOrder');

        $component
            ->call('move', 'notARealOrderProperty', 0, 1)
            ->assertSet('indexOrder', $originalOrder);

        $this->assertSame([], $component->instance()->layoutRows('notARealOrderProperty', 'indexFields'));
        $this->assertSame([], $component->instance()->layoutRows('indexOrder', 'notARealFieldsProperty'));
    }

    public function test_field_layout_component_enforces_locked_visibility_when_saving(): void
    {
        Livewire::test(ProductFieldLayoutSettings::class)
            ->set('editFields.title.visible', false)
            ->set('quickAddFields.rj_code.visible', false)
            ->set('customQuickAddFields.title.visible', false)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('editFields.title.visible', true)
            ->assertSet('quickAddFields.rj_code.visible', true)
            ->assertSet('customQuickAddFields.title.visible', true);

        $this->assertTrue($this->layoutRow(Option::editFieldLayout(), ProductField::Title)['visible']);
        $this->assertTrue($this->layoutRow(Option::quickAddFieldLayout(), ProductField::RjCode)['visible']);
        $this->assertTrue($this->layoutRow(Option::customQuickAddFieldLayout(), ProductField::Title)['visible']);
    }

    public function test_field_layout_component_resets_to_default_after_confirmation(): void
    {
        Livewire::test(ProductFieldLayoutSettings::class)
            ->set('indexFields.score.visible', false)
            ->call('save')
            ->assertSet('saved', true)
            ->call('askResetToDefault')
            ->assertSet('confirmingResetToDefault', true)
            ->call('resetToDefault')
            ->assertHasNoErrors()
            ->assertSet('confirmingResetToDefault', false)
            ->assertSet('saved', true)
            ->assertSet('notice', 'Field layouts reset to default.');

        $this->assertTrue($this->layoutRow(Option::indexFieldLayout(), ProductField::Score)['visible']);
        $this->assertSame(ProductField::Image->value, Option::indexFieldLayout()[0]['field']);
    }

    public function test_field_layout_component_refreshes_after_global_defaults_reset(): void
    {
        Option::setIndexFieldLayout([
            ['field' => ProductField::Score->value, 'visible' => false],
        ]);

        $component = Livewire::test(ProductFieldLayoutSettings::class)
            ->assertSet('indexFields.score.visible', false)
            ->call('save')
            ->assertSet('saved', true);

        Option::resetFieldLayoutsToDefault();

        $component
            ->call('refreshFromSettings')
            ->assertSet('indexFields.score.visible', true)
            ->assertSet('saved', false)
            ->assertSet('notice', '');
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
            [
                'field' => ProductField::Tags->value,
                'visible' => true,
                'editable' => true,
                'fetched_editable' => true,
            ],
        ]);
        Option::setQuickAddFieldLayout([
            ['field' => ProductField::Notes->value, 'visible' => false],
        ]);
        Option::setCustomQuickAddFieldLayout([
            ['field' => ProductField::SampleImages->value, 'visible' => false],
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
        $this->assertSame('72vw', Option::indexTableWidthCss());

        Livewire::test(OptionsResetDefaults::class)
            ->call('askResetToDefault')
            ->call('resetAll')
            ->assertDispatched('options-defaults-reset')
            ->assertSet('confirmingResetToDefault', false)
            ->assertSet('saved', true)
            ->assertSet('notice', 'All Options settings reset to defaults.');

        $this->assertSame(Option::DEFAULT_INDEX_PER_PAGE, Option::indexPerPage());
        $this->assertSame('1024px', Option::indexTableWidthCss());
        $this->assertTrue(Option::autoSeriesFromTitleName());
        $this->assertSame(AutocompleteOrder::Usage, Option::tagAutocompleteOrder());
        $this->assertSame(AutocompleteOrder::Usage, Option::seriesAutocompleteOrder());
        $this->assertSame(ProductField::Image->value, Option::indexFieldLayout()[0]['field']);
        $this->assertTrue(Option::indexFieldLayout()[0]['visible']);
        $this->assertTrue($this->layoutRow(Option::indexFieldLayout(), ProductField::Title)['visibility_locked']);
        $this->assertFalse($this->layoutRow(Option::editFieldLayout(), ProductField::Tags)['fetched_editable']);
        $this->assertTrue($this->layoutRow(Option::quickAddFieldLayout(), ProductField::RjCode)['visibility_locked']);
        $this->assertTrue($this->layoutRow(Option::quickAddFieldLayout(), ProductField::Notes)['visible']);
        $this->assertTrue($this->layoutRow(Option::customQuickAddFieldLayout(), ProductField::SampleImages)['visible']);
        $this->assertSame('keep-me', DB::table('options')->where('key', 'unrelated_option')->value('value'));
    }

    public function test_general_options_page_mounts_non_layout_settings_components(): void
    {
        $this->get('/options')
            ->assertOk()
            ->assertSeeLivewire(IndexPaginationSettings::class)
            ->assertSeeLivewire(IndexTableWidthSettings::class)
            ->assertDontSeeLivewire(ProductFieldLayoutSettings::class)
            ->assertSeeLivewire(AutoSeriesSettings::class)
            ->assertSeeLivewire(AutocompleteSettings::class)
            ->assertSeeLivewire(OptionsResetDefaults::class);
    }

    public function test_field_layouts_options_page_mounts_layout_settings_components(): void
    {
        $this->get('/options?tab=field-layouts')
            ->assertOk()
            ->assertDontSeeLivewire(IndexPaginationSettings::class)
            ->assertDontSeeLivewire(IndexTableWidthSettings::class)
            ->assertSeeLivewire(ProductFieldLayoutSettings::class)
            ->assertDontSeeLivewire(AutoSeriesSettings::class)
            ->assertDontSeeLivewire(AutocompleteSettings::class)
            ->assertSeeLivewire(OptionsResetDefaults::class);
    }

    public function test_field_layout_component_renders_core_user_controls(): void
    {
        Livewire::test(ProductFieldLayoutSettings::class)
            ->assertSee('Index Table')
            ->assertSee('Edit Form')
            ->assertSee('Filter Modal')
            ->assertSee('Quick Add')
            ->assertSee('Custom Quick Add')
            ->assertSee('Required')
            ->assertSee('Editable Custom Tags')
            ->assertSee('Editable Fetched EN Tags')
            ->assertSee('Save field layouts')
            ->assertSee('Reset to default');
    }

    public function test_field_layout_component_moves_a_row_up_and_down(): void
    {
        $component = Livewire::test(ProductFieldLayoutSettings::class);
        $originalOrder = $component->get('indexOrder');

        $this->assertArrayHasKey(0, $originalOrder);
        $this->assertArrayHasKey(1, $originalOrder);

        $component
            ->call('move', 'indexOrder', 1, -1)
            ->assertSet('indexOrder.0', $originalOrder[1])
            ->assertSet('indexOrder.1', $originalOrder[0])
            ->call('move', 'indexOrder', 0, 1)
            ->assertSet('indexOrder', $originalOrder);
    }

    public function test_field_layouts_tab_exposes_metadata_settings_sections(): void
    {
        $this->get('/options?tab=field-layouts')
            ->assertOk()
            ->assertSee('Field Layouts')
            ->assertSee('Fetched EN Tags');
    }

    public function test_global_reset_modal_mentions_general_and_field_layout_tabs(): void
    {
        Livewire::test(OptionsResetDefaults::class)
            ->call('askResetToDefault')
            ->assertSee('Reset all General and Field Layouts options to their defaults?');
    }

    public function test_reset_all_options_uses_countdown_delay(): void
    {
        $this->assertSame(
            3,
            Livewire::test(OptionsResetDefaults::class)
                ->instance()
                ->resetConfirmDelaySeconds()
        );

        $this->assertSame(
            0,
            Livewire::test(IndexTableWidthSettings::class)
                ->instance()
                ->resetConfirmDelaySeconds()
        );
    }

    public static function fixedTableWidthProvider(): iterable
    {
        yield 'default width' => [Option::INDEX_TABLE_WIDTH_DEFAULT, '1024px'];
        yield 'wide width' => [Option::INDEX_TABLE_WIDTH_WIDE, '1400px'];
        yield 'full width' => [Option::INDEX_TABLE_WIDTH_FULL, '100%'];
    }

    public static function invalidCustomWidthProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'css function' => ['calc(100%)'];
        yield 'arbitrary text' => ['wide'];
        yield 'unsupported unit' => ['10vh'];
        yield 'missing unit' => ['1200'];
    }

    /**
     * @param list<string> $order
     *
     * @return list<string>
     */
    private function moveFieldToPosition(array $order, ProductField $field, int $position): array
    {
        $currentIndex = array_search($field->value, $order, true);

        $this->assertIsInt($currentIndex, "Missing field [{$field->value}] in layout order.");

        array_splice($order, $currentIndex, 1);

        $position = max(0, min($position, count($order)));
        array_splice($order, $position, 0, [$field->value]);

        return array_values($order);
    }

    /**
     * @param list<array<string, mixed>> $layout
     *
     * @return array<string, mixed>
     */
    private function layoutRow(array $layout, ProductField $field): array
    {
        $row = collect($layout)->firstWhere('field', $field->value);

        $this->assertIsArray($row, "Missing layout row for [{$field->value}].");

        return $row;
    }
}
