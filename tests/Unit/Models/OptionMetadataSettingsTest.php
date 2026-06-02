<?php

namespace Tests\Unit\Models;

use App\Enums\ProductField;
use App\Models\Option;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OptionMetadataSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_series_defaults_to_enabled_and_can_be_saved(): void
    {
        $this->assertTrue(Option::autoSeriesFromTitleName());

        Option::setAutoSeriesFromTitleName(false);

        $this->assertFalse(Option::autoSeriesFromTitleName());
    }

    public function test_field_layouts_are_normalized_when_saved(): void
    {
        Option::setIndexFieldLayout([
            ['field' => ProductField::Description->value, 'visible' => true],
            ['field' => ProductField::Score->value, 'visible' => false],
        ]);

        $layout = Option::indexFieldLayout();

        $this->assertSame(ProductField::Image->value, $layout[0]['field']);
        $this->assertTrue($layout[0]['visible']);
        $this->assertSame(ProductField::Title->value, $layout[1]['field']);
        $this->assertTrue($layout[1]['visible']);
        $this->assertTrue($layout[1]['visibility_locked']);
        $this->assertSame(ProductField::Description->value, $layout[2]['field']);
        $this->assertTrue($layout[2]['visible']);
        $this->assertSame(ProductField::Score->value, $layout[3]['field']);
        $this->assertFalse($layout[3]['visible']);
        $this->assertContains(ProductField::Tags->value, collect($layout)->pluck('field')->all());
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

        $this->assertSame(
            ProductField::RjCode->value,
            json_decode(DB::table('options')->where('key', Option::QUICK_ADD_FIELD_LAYOUT)->value('value'), true)[0]['field'],
        );
        $this->assertSame(
            ProductField::RjCode->value,
            json_decode(DB::table('options')->where('key', Option::CUSTOM_QUICK_ADD_FIELD_LAYOUT)->value('value'), true)[0]['field'],
        );
    }

    public function test_product_index_settings_default_and_saved_values_are_normalized(): void
    {
        $defaults = Option::productIndexSettings();

        $this->assertSame(Option::DEFAULT_INDEX_PER_PAGE, $defaults->perPage);
        $this->assertSame(ProductField::Image->value, $defaults->indexColumns[0]['field']);
        $this->assertContains(ProductField::Title->value, $defaults->visibleIndexFields);
        $this->assertSame(ProductField::Title->value, $defaults->filterFields[0]['field']);
        $this->assertSame([
            ProductField::Title->value,
            ProductField::Series->value,
            ProductField::Notes->value,
            ProductField::AgeCategory->value,
            ProductField::Progress->value,
            ProductField::Score->value,
            ProductField::Priority->value,
            ProductField::TotalTimesReListened->value,
            ProductField::ReListenValue->value,
            ProductField::Tags->value,
        ], collect($defaults->filterFields)->pluck('field')->all());
        $this->assertSame('1024px', $defaults->tableWidthCss);

        Option::setIndexPerPage(250);
        Option::setIndexFieldLayout([
            ['field' => ProductField::Description->value, 'visible' => true],
            ['field' => ProductField::Score->value, 'visible' => false],
        ]);
        Option::setFilterFieldLayout([
            ['field' => ProductField::Priority->value, 'visible' => true],
            ['field' => ProductField::Title->value, 'visible' => false],
        ]);
        Option::setIndexTableWidth([
            'mode' => Option::INDEX_TABLE_WIDTH_CUSTOM,
            'custom' => '75%',
        ]);

        $settings = Option::productIndexSettings();

        $this->assertSame(250, $settings->perPage);
        $this->assertSame(ProductField::Description->value, $settings->indexColumns[2]['field']);
        $this->assertContains(ProductField::Description->value, $settings->visibleIndexFields);
        $this->assertNotContains(ProductField::Score->value, $settings->visibleIndexFields);
        $filterFieldIds = collect($settings->filterFields)->pluck('field')->all();

        $this->assertContains(ProductField::Priority->value, $filterFieldIds);
        $this->assertNotContains(ProductField::Title->value, $filterFieldIds);
        $this->assertSame('75%', $settings->tableWidthCss);
    }

    public function test_product_index_settings_fall_back_from_invalid_saved_values(): void
    {
        Option::query()->updateOrCreate(['key' => Option::INDEX_PER_PAGE], ['value' => '0']);
        Option::query()->updateOrCreate(['key' => Option::INDEX_FIELD_LAYOUT], ['value' => 'not-json']);
        Option::query()->updateOrCreate(['key' => Option::FILTER_FIELD_LAYOUT], ['value' => 'not-json']);
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
        $this->assertSame('1024px', $settings->tableWidthCss);
    }

    public function test_index_table_width_normalizes_invalid_custom_values(): void
    {
        $this->assertSame('1024px', Option::indexTableWidthCss());

        Option::setIndexTableWidth([
            'mode' => Option::INDEX_TABLE_WIDTH_WIDE,
            'custom' => '',
        ]);

        $this->assertSame('1400px', Option::indexTableWidthCss());

        Option::setIndexTableWidth([
            'mode' => Option::INDEX_TABLE_WIDTH_CUSTOM,
            'custom' => '75%',
        ]);

        $this->assertSame('75%', Option::indexTableWidthCss());

        $this->assertSame([
            'mode' => Option::INDEX_TABLE_WIDTH_DEFAULT,
            'custom' => '',
        ], Option::normalizeIndexTableWidth([
            'mode' => Option::INDEX_TABLE_WIDTH_CUSTOM,
            'custom' => 'calc(100%)',
        ]));
    }
}
