<?php

namespace Tests\Unit\Models;

use App\Enums\ProductField;
use App\Enums\ProductIndexSortField;
use App\Enums\UiLanguage;
use App\Models\Option;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OptionMetadataSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ui_language_defaults_to_english_and_normalizes_saved_values(): void
    {
        $this->assertSame(UiLanguage::English, Option::uiLanguage());

        Option::setUiLanguage(UiLanguage::Japanese);

        $this->assertSame(UiLanguage::Japanese, Option::uiLanguage());
        $this->assertDatabaseHas('options', [
            'key' => Option::UI_LANGUAGE,
            'value' => UiLanguage::Japanese->value,
        ]);

        Option::setUiLanguage('unsupported');

        $this->assertSame(UiLanguage::English, Option::uiLanguage());
        $this->assertDatabaseHas('options', [
            'key' => Option::UI_LANGUAGE,
            'value' => UiLanguage::English->value,
        ]);
    }

    public function test_ui_language_can_be_reset_to_english_by_removing_its_option(): void
    {
        Option::setUiLanguage(UiLanguage::Japanese);

        Option::resetUiLanguageToDefault();

        $this->assertSame(UiLanguage::English, Option::uiLanguage());
        $this->assertDatabaseMissing('options', ['key' => Option::UI_LANGUAGE]);
    }

    public function test_auto_series_defaults_to_enabled_and_can_be_saved(): void
    {
        $this->assertTrue(Option::autoSeriesFromTitleName());

        Option::setAutoSeriesFromTitleName(false);

        $this->assertFalse(Option::autoSeriesFromTitleName());
    }

    public function test_dlsite_age_appropriate_links_default_to_disabled_and_can_be_reset(): void
    {
        $this->assertFalse(Option::dlsiteAgeAppropriateLinksEnabled());

        Option::setDlsiteAgeAppropriateLinksEnabled(true);

        $this->assertTrue(Option::dlsiteAgeAppropriateLinksEnabled());

        Option::resetDlsiteAgeAppropriateLinksEnabledToDefault();

        $this->assertFalse(Option::dlsiteAgeAppropriateLinksEnabled());
    }

    public function test_tag_library_index_group_ordering_defaults_to_disabled_and_can_be_saved(): void
    {
        $this->assertFalse(Option::tagLibraryIndexGroupOrderingEnabled());

        Option::setTagLibraryIndexGroupOrderingEnabled(true);

        $this->assertTrue(Option::tagLibraryIndexGroupOrderingEnabled());

        Option::setTagLibraryIndexGroupOrderingEnabled(false);

        $this->assertFalse(Option::tagLibraryIndexGroupOrderingEnabled());
    }

    public function test_tag_color_surfaces_default_to_index_and_tag_library_only_and_can_be_saved(): void
    {
        $this->assertSame([
            'index' => true,
            'tag_library' => true,
            'autocomplete' => false,
            'edit_readonly' => false,
            'refetch' => false,
        ], Option::tagColorSurfaces());

        Option::setTagColorSurfaces([
            'index' => false,
            'tag_library' => false,
            'autocomplete' => true,
            'edit_readonly' => true,
            'refetch' => true,
            'unknown' => true,
        ]);

        $this->assertSame([
            'index' => false,
            'tag_library' => false,
            'autocomplete' => true,
            'edit_readonly' => true,
            'refetch' => true,
        ], Option::tagColorSurfaces());

        DB::table('options')->updateOrInsert(
            ['key' => Option::TAG_COLOR_SURFACES],
            ['value' => '{"index":true,"tag_library":true'],
        );

        $this->assertSame([
            'index' => true,
            'tag_library' => true,
            'autocomplete' => false,
            'edit_readonly' => false,
            'refetch' => false,
        ], Option::tagColorSurfaces());
    }

    public function test_product_form_theme_defaults_to_black_and_normalizes_invalid_values(): void
    {
        $this->assertSame(Option::PRODUCT_FORM_THEME_BLACK, Option::productFormTheme());
        $this->assertSame([
            Option::PRODUCT_FORM_THEME_CHERRY => 'Cherry',
            Option::PRODUCT_FORM_THEME_BLACK => 'Black',
        ], Option::productFormThemeOptions());

        Option::setProductFormTheme(Option::PRODUCT_FORM_THEME_CHERRY);

        $this->assertSame(Option::PRODUCT_FORM_THEME_CHERRY, Option::productFormTheme());

        Option::setProductFormTheme(Option::PRODUCT_FORM_THEME_BLACK);

        $this->assertSame(Option::PRODUCT_FORM_THEME_BLACK, Option::productFormTheme());

        Option::query()->updateOrCreate(
            ['key' => Option::PRODUCT_FORM_THEME],
            ['value' => 'not-a-theme'],
        );

        $this->assertSame(Option::PRODUCT_FORM_THEME_BLACK, Option::productFormTheme());
    }

    public function test_product_form_modal_settings_use_safe_defaults_and_normalize_invalid_values(): void
    {
        $this->assertFalse(Option::productFormModalEnabled());
        $this->assertSame(
            Option::PRODUCT_FORM_MODAL_COMPLETION_REDIRECT,
            Option::productFormModalCompletionAction(),
        );
        $this->assertSame([
            Option::PRODUCT_FORM_MODAL_COMPLETION_REDIRECT => 'Follow redirect',
            Option::PRODUCT_FORM_MODAL_COMPLETION_REFRESH => 'Refresh current page',
            Option::PRODUCT_FORM_MODAL_COMPLETION_CLOSE => 'Close modal only',
        ], Option::productFormModalCompletionOptions());

        Option::setProductFormModalEnabled(true);
        Option::setProductFormModalCompletionAction(Option::PRODUCT_FORM_MODAL_COMPLETION_CLOSE);

        $this->assertTrue(Option::productFormModalEnabled());
        $this->assertSame(
            Option::PRODUCT_FORM_MODAL_COMPLETION_CLOSE,
            Option::productFormModalCompletionAction(),
        );

        Option::query()->updateOrCreate(
            ['key' => Option::PRODUCT_FORM_MODAL_COMPLETION_ACTION],
            ['value' => 'not-an-action'],
        );

        $this->assertSame(
            Option::PRODUCT_FORM_MODAL_COMPLETION_REDIRECT,
            Option::productFormModalCompletionAction(),
        );

        Option::resetProductFormModalSettingsToDefault();

        $this->assertFalse(Option::productFormModalEnabled());
        $this->assertSame(
            Option::PRODUCT_FORM_MODAL_COMPLETION_REDIRECT,
            Option::productFormModalCompletionAction(),
        );
    }

    public function test_field_layouts_are_normalized_when_saved(): void
    {
        Option::setIndexFieldLayout([
            ['field' => 'description', 'visible' => true],
            ['field' => ProductField::DescriptionJapanese->value, 'visible' => true],
            ['field' => ProductField::DescriptionEnglish->value, 'visible' => false],
            ['field' => ProductField::Score->value, 'visible' => false],
        ]);

        $layout = Option::indexFieldLayout();

        $this->assertSame(ProductField::Image->value, $layout[0]['field']);
        $this->assertTrue($layout[0]['visible']);
        $this->assertSame(ProductField::Title->value, $layout[1]['field']);
        $this->assertTrue($layout[1]['visible']);
        $this->assertTrue($layout[1]['visibility_locked']);
        $this->assertSame(ProductField::DescriptionJapanese->value, $layout[2]['field']);
        $this->assertTrue($layout[2]['visible']);
        $this->assertSame(ProductField::DescriptionEnglish->value, $layout[3]['field']);
        $this->assertFalse($layout[3]['visible']);
        $this->assertSame(ProductField::Score->value, $layout[4]['field']);
        $this->assertFalse($layout[4]['visible']);
        $this->assertNotContains('description', collect($layout)->pluck('field')->all());
        $this->assertContains(ProductField::Tags->value, collect($layout)->pluck('field')->all());
        $this->assertSame(
            'Notes are already shown inside Title; enable this for a separate column.',
            collect($layout)->firstWhere('field', ProductField::Notes->value)['note'],
        );

        $storedLayout = json_decode(DB::table('options')->where('key', Option::INDEX_FIELD_LAYOUT)->value('value'), true);

        $this->assertArrayNotHasKey('note', collect($storedLayout)->firstWhere('field', ProductField::Notes->value));
    }

    public function test_quick_add_field_layouts_are_normalized_when_saved(): void
    {
        Option::setQuickAddFieldLayout([
            ['field' => ProductField::Notes->value, 'visible' => false],
            ['field' => ProductField::RjCode->value, 'visible' => false],
            ['field' => 'not_real', 'visible' => true],
        ]);
        Option::setCustomQuickAddFieldLayout([
            ['field' => ProductField::RjCode->value, 'visible' => false],
            ['field' => ProductField::Title->value, 'visible' => false],
            ['field' => ProductField::AgeCategory->value, 'visible' => false],
            ['field' => ProductField::Image->value, 'visible' => false],
            ['field' => ProductField::SampleImages->value, 'visible' => false],
        ]);

        $quickAddLayout = Option::quickAddFieldLayout();
        $customQuickAddLayout = Option::customQuickAddFieldLayout();

        $rjRow = collect($quickAddLayout)->firstWhere('field', ProductField::RjCode->value);

        $this->assertTrue($rjRow['visible']);
        $this->assertTrue($rjRow['visibility_locked']);
        $this->assertFalse(collect($quickAddLayout)->firstWhere('field', ProductField::Notes->value)['visible']);
        $this->assertTrue(collect($customQuickAddLayout)->firstWhere('field', ProductField::Title->value)['visible']);
        $this->assertTrue(collect($customQuickAddLayout)->firstWhere('field', ProductField::AgeCategory->value)['visible']);
        $this->assertTrue(collect($customQuickAddLayout)->firstWhere('field', ProductField::Image->value)['visible']);
        $this->assertFalse(collect($customQuickAddLayout)->firstWhere('field', ProductField::SampleImages->value)['visible']);

        Option::resetFieldLayoutsToDefault();

        $this->assertDatabaseMissing('options', ['key' => Option::QUICK_ADD_FIELD_LAYOUT]);
        $this->assertDatabaseMissing('options', ['key' => Option::CUSTOM_QUICK_ADD_FIELD_LAYOUT]);
        $this->assertSame(
            ProductField::RjCode->value,
            Option::quickAddFieldLayout()[0]['field'],
        );
        $this->assertSame(
            ProductField::RjCode->value,
            Option::customQuickAddFieldLayout()[0]['field'],
        );
    }

    public function test_product_index_settings_default_and_saved_values_are_normalized(): void
    {
        $defaults = Option::productIndexSettings();

        $this->assertSame(Option::DEFAULT_INDEX_PER_PAGE, $defaults->perPage);
        $this->assertSame($this->visibleDefaultSortOptions(), $defaults->indexSortFieldOptions);
        $this->assertFalse(collect($defaults->indexSortFieldLayout)->firstWhere('field', ProductIndexSortField::UpdatedAt->value)['visible']);
        $this->assertFalse(collect($defaults->indexSortFieldLayout)->firstWhere('field', ProductIndexSortField::Circle->value)['visible']);
        $this->assertSame(ProductField::Image->value, $defaults->indexColumns[0]['field']);
        $this->assertContains(ProductField::Title->value, $defaults->visibleIndexFields);
        $this->assertSame(ProductField::Title->value, $defaults->filterFields[0]['field']);
        $this->assertFalse($defaults->indexGroupOrderingEnabled);
        $this->assertFalse($defaults->searchHiddenDescriptionsEnabled);
        $this->assertSame(Option::DEFAULT_TAG_COLOR_SURFACES, $defaults->tagColorSurfaces);
        $this->assertFalse($defaults->productFormModalEnabled);
        $this->assertFalse($defaults->dlsiteAgeAppropriateLinksEnabled);
        $this->assertSame(
            Option::PRODUCT_FORM_MODAL_COMPLETION_REDIRECT,
            $defaults->productFormModalCompletionAction,
        );
        $this->assertSame([
            ProductField::Title->value,
            ProductField::Score->value,
            ProductField::Series->value,
            ProductField::AgeCategory->value,
            ProductField::Progress->value,
            ProductField::Notes->value,
            ProductField::Priority->value,
            ProductField::TotalTimesReListened->value,
            ProductField::ReListenValue->value,
            ProductField::Tags->value,
        ], collect($defaults->filterFields)->pluck('field')->all());
        $this->assertSame('1024px', $defaults->tableWidthCss);

        Option::setIndexPerPage(250);
        Option::setIndexFieldLayout([
            ['field' => ProductField::DescriptionJapanese->value, 'visible' => true],
            ['field' => ProductField::DescriptionEnglish->value, 'visible' => true],
            ['field' => ProductField::Score->value, 'visible' => false],
        ]);
        Option::setFilterFieldLayout([
            ['field' => ProductField::Priority->value, 'visible' => true],
            ['field' => ProductField::Title->value, 'visible' => false],
        ]);
        Option::setIndexSortFieldLayout([
            ['field' => ProductIndexSortField::Series->value, 'visible' => true],
            ['field' => ProductIndexSortField::Score->value, 'visible' => false],
        ]);
        Option::setIndexTableWidth([
            'mode' => Option::INDEX_TABLE_WIDTH_CUSTOM,
            'custom' => '75%',
        ]);
        Option::setTagLibraryIndexGroupOrderingEnabled(true);
        Option::setIndexSearchHiddenDescriptionsEnabled(true);
        Option::setTagColorSurfaces([
            Option::TAG_COLOR_SURFACE_INDEX => false,
            Option::TAG_COLOR_SURFACE_REFETCH => true,
        ]);
        Option::setProductFormModalEnabled(true);
        Option::setProductFormModalCompletionAction(Option::PRODUCT_FORM_MODAL_COMPLETION_REFRESH);
        Option::setDlsiteAgeAppropriateLinksEnabled(true);

        $settings = Option::productIndexSettings();

        $this->assertSame(250, $settings->perPage);
        $this->assertSame(ProductField::DescriptionJapanese->value, $settings->indexColumns[2]['field']);
        $this->assertSame(ProductField::DescriptionEnglish->value, $settings->indexColumns[3]['field']);
        $this->assertContains(ProductField::DescriptionJapanese->value, $settings->visibleIndexFields);
        $this->assertContains(ProductField::DescriptionEnglish->value, $settings->visibleIndexFields);
        $this->assertNotContains(ProductField::Score->value, $settings->visibleIndexFields);
        $filterFieldIds = collect($settings->filterFields)->pluck('field')->all();

        $this->assertContains(ProductField::Priority->value, $filterFieldIds);
        $this->assertNotContains(ProductField::Title->value, $filterFieldIds);
        $this->assertSame(ProductIndexSortField::Series->value, $settings->indexSortFieldLayout[0]['field']);
        $this->assertArrayHasKey(ProductIndexSortField::Series->value, $settings->indexSortFieldOptions);
        $this->assertArrayNotHasKey(ProductIndexSortField::Score->value, $settings->indexSortFieldOptions);
        $this->assertSame('75%', $settings->tableWidthCss);
        $this->assertTrue($settings->indexGroupOrderingEnabled);
        $this->assertTrue($settings->searchHiddenDescriptionsEnabled);
        $this->assertFalse($settings->tagColorSurfaces[Option::TAG_COLOR_SURFACE_INDEX]);
        $this->assertTrue($settings->tagColorSurfaces[Option::TAG_COLOR_SURFACE_REFETCH]);
        $this->assertTrue($settings->productFormModalEnabled);
        $this->assertTrue($settings->dlsiteAgeAppropriateLinksEnabled);
        $this->assertSame(
            Option::PRODUCT_FORM_MODAL_COMPLETION_REFRESH,
            $settings->productFormModalCompletionAction,
        );
    }

    public function test_product_index_settings_fall_back_from_invalid_saved_values(): void
    {
        Option::query()->updateOrCreate(['key' => Option::INDEX_PER_PAGE], ['value' => '0']);
        Option::query()->updateOrCreate(['key' => Option::INDEX_FIELD_LAYOUT], ['value' => 'not-json']);
        Option::query()->updateOrCreate(['key' => Option::FILTER_FIELD_LAYOUT], ['value' => 'not-json']);
        Option::query()->updateOrCreate(['key' => Option::INDEX_SORT_FIELD_LAYOUT], ['value' => 'not-json']);
        Option::query()->updateOrCreate([
            'key' => Option::INDEX_TABLE_WIDTH,
        ], [
            'value' => json_encode([
                'mode' => Option::INDEX_TABLE_WIDTH_CUSTOM,
                'custom' => 'calc(100%)',
            ]),
        ]);

        $settings = Option::productIndexSettings();

        $this->assertSame(Option::DEFAULT_INDEX_PER_PAGE, $settings->perPage);
        $this->assertSame(ProductField::Image->value, $settings->indexColumns[0]['field']);
        $this->assertSame(ProductField::Title->value, $settings->filterFields[0]['field']);
        $this->assertSame($this->visibleDefaultSortOptions(), $settings->indexSortFieldOptions);
        $this->assertSame('1024px', $settings->tableWidthCss);
        $this->assertFalse($settings->indexGroupOrderingEnabled);
        $this->assertFalse($settings->searchHiddenDescriptionsEnabled);
    }

    public function test_reset_visible_settings_restores_index_search_and_tag_library_ordering_defaults(): void
    {
        Option::setTagLibraryIndexGroupOrderingEnabled(true);
        Option::setIndexSearchHiddenDescriptionsEnabled(true);
        Option::setProductFormTheme(Option::PRODUCT_FORM_THEME_CHERRY);
        Option::setProductFormModalEnabled(true);
        Option::setProductFormModalCompletionAction(Option::PRODUCT_FORM_MODAL_COMPLETION_CLOSE);
        Option::setDlsiteAgeAppropriateLinksEnabled(true);
        Option::setTagColorSurfaces([
            'index' => false,
            'tag_library' => false,
            'autocomplete' => true,
            'edit_readonly' => true,
            'refetch' => true,
        ]);

        Option::resetVisibleSettingsToDefault();

        $this->assertFalse(Option::tagLibraryIndexGroupOrderingEnabled());
        $this->assertFalse(Option::indexSearchHiddenDescriptionsEnabled());
        $this->assertSame(Option::PRODUCT_FORM_THEME_BLACK, Option::productFormTheme());
        $this->assertFalse(Option::productFormModalEnabled());
        $this->assertFalse(Option::dlsiteAgeAppropriateLinksEnabled());
        $this->assertSame(
            Option::PRODUCT_FORM_MODAL_COMPLETION_REDIRECT,
            Option::productFormModalCompletionAction(),
        );
        $this->assertSame([
            'index' => true,
            'tag_library' => true,
            'autocomplete' => false,
            'edit_readonly' => false,
            'refetch' => false,
        ], Option::tagColorSurfaces());
    }

    public function test_index_sort_field_layout_is_normalized_when_saved_and_reset(): void
    {
        Option::setIndexSortFieldLayout([
            ['field' => ProductIndexSortField::Series->value, 'visible' => true],
            ['field' => ProductIndexSortField::Score->value, 'visible' => false],
            ['field' => ProductIndexSortField::Series->value, 'visible' => false],
            ['field' => 'not_real', 'visible' => true],
        ]);

        $layout = Option::indexSortFieldLayout();

        $this->assertSame(ProductIndexSortField::Series->value, $layout[0]['field']);
        $this->assertTrue($layout[0]['visible']);
        $this->assertSame(ProductIndexSortField::Score->value, $layout[1]['field']);
        $this->assertFalse($layout[1]['visible']);
        $this->assertSame(ProductIndexSortField::RJ->value, $layout[2]['field']);
        $this->assertFalse(collect($layout)->firstWhere('field', ProductIndexSortField::UpdatedAt->value)['visible']);
        $this->assertFalse(collect($layout)->firstWhere('field', ProductIndexSortField::Author->value)['visible']);

        $storedLayout = json_decode(
            DB::table('options')->where('key', Option::INDEX_SORT_FIELD_LAYOUT)->value('value'),
            true,
        );

        $this->assertSame([
            'field' => ProductIndexSortField::Series->value,
            'visible' => true,
        ], $storedLayout[0]);
        $this->assertArrayNotHasKey('label', $storedLayout[0]);

        Option::resetIndexSortFieldLayoutToDefault();

        $this->assertSame(ProductIndexSortField::RJ->value, Option::indexSortFieldLayout()[0]['field']);
        $this->assertTrue(Option::indexSortFieldLayout()[0]['visible']);
        $this->assertFalse(collect(Option::indexSortFieldLayout())->firstWhere('field', ProductIndexSortField::UpdatedAt->value)['visible']);
    }

    public function test_index_table_width_normalizes_invalid_custom_values(): void
    {
        $this->assertSame('1024px', Option::productIndexSettings()->tableWidthCss);

        Option::setIndexTableWidth([
            'mode' => Option::INDEX_TABLE_WIDTH_WIDE,
            'custom' => '',
        ]);

        $this->assertSame('1400px', Option::productIndexSettings()->tableWidthCss);

        Option::setIndexTableWidth([
            'mode' => Option::INDEX_TABLE_WIDTH_CUSTOM,
            'custom' => '75%',
        ]);

        $this->assertSame('75%', Option::productIndexSettings()->tableWidthCss);

        $this->assertSame([
            'mode' => Option::INDEX_TABLE_WIDTH_DEFAULT,
            'custom' => '',
        ], Option::normalizeIndexTableWidth([
            'mode' => Option::INDEX_TABLE_WIDTH_CUSTOM,
            'custom' => 'calc(100%)',
        ]));
    }

    public function test_individual_resets_remove_saved_option_rows(): void
    {
        $resetCases = [
            [[Option::INDEX_PER_PAGE], fn() => Option::resetIndexPerPageToDefault()],
            [[Option::INDEX_SEARCH_HIDDEN_DESCRIPTIONS_ENABLED], fn() => Option::resetIndexSearchHiddenDescriptionsEnabledToDefault()],
            [[Option::TAG_AUTOCOMPLETE_ORDER, Option::SERIES_AUTOCOMPLETE_ORDER], fn() => Option::resetAutocompleteToDefault()],
            [[Option::AUTO_SERIES_FROM_TITLE_NAME], fn() => Option::resetAutoSeriesFromTitleNameToDefault()],
            [[Option::PRODUCT_FORM_THEME], fn() => Option::resetProductFormThemeToDefault()],
            [[Option::TAG_LIBRARY_TAGS_EXPANDED_BY_DEFAULT], fn() => Option::resetTagLibraryTagsExpandedByDefaultToDefault()],
            [[Option::TAG_LIBRARY_INDEX_GROUP_ORDERING_ENABLED], fn() => Option::resetTagLibraryIndexGroupOrderingEnabledToDefault()],
            [[Option::TAG_COLOR_SURFACES], fn() => Option::resetTagColorSurfacesToDefault()],
            [[
                Option::INDEX_FIELD_LAYOUT,
                Option::EDIT_FIELD_LAYOUT,
                Option::FILTER_FIELD_LAYOUT,
                Option::QUICK_ADD_FIELD_LAYOUT,
                Option::CUSTOM_QUICK_ADD_FIELD_LAYOUT,
            ], fn() => Option::resetFieldLayoutsToDefault()],
            [[Option::INDEX_SORT_FIELD_LAYOUT], fn() => Option::resetIndexSortFieldLayoutToDefault()],
            [[Option::INDEX_TABLE_WIDTH], fn() => Option::resetIndexTableWidthToDefault()],
        ];

        foreach ($resetCases as [$keys, $reset]) {
            foreach ($keys as $key) {
                Option::query()->updateOrCreate(['key' => $key], ['value' => 'custom']);
            }

            $reset();

            foreach ($keys as $key) {
                $this->assertDatabaseMissing('options', ['key' => $key]);
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function visibleDefaultSortOptions(): array
    {
        return array_diff_key(ProductIndexSortField::options(), array_flip([
            ProductIndexSortField::UpdatedAt->value,
            ProductIndexSortField::Circle->value,
            ProductIndexSortField::Scenario->value,
            ProductIndexSortField::Illustration->value,
            ProductIndexSortField::VoiceActor->value,
            ProductIndexSortField::Author->value,
        ]));
    }
}
