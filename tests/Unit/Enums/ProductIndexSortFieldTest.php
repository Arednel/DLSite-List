<?php

namespace Tests\Unit\Enums;

use App\Enums\ProductIndexSortField;
use Tests\TestCase;

class ProductIndexSortFieldTest extends TestCase
{
    public function test_it_exposes_sql_sort_columns_for_all_fields(): void
    {
        $expectedColumns = [
            ProductIndexSortField::RJ->value => 'rj_number',
            ProductIndexSortField::Score->value => 'score',
            ProductIndexSortField::Series->value => 'series',
            ProductIndexSortField::AgeCategory->value => 'age_category',
            ProductIndexSortField::Progress->value => 'progress',
            ProductIndexSortField::Priority->value => 'priority',
            ProductIndexSortField::TotalTimesReListened->value => 'num_re_listen_times',
            ProductIndexSortField::ReListenValue->value => 're_listen_value',
            ProductIndexSortField::StartDate->value => 'start_date_sort',
            ProductIndexSortField::FinishDate->value => 'end_date_sort',
            ProductIndexSortField::AddedToTheSiteDate->value => 'created_at',
            ProductIndexSortField::UpdatedAt->value => 'updated_at',
            ProductIndexSortField::Circle->value => 'circle',
            ProductIndexSortField::Scenario->value => 'scenario',
            ProductIndexSortField::Illustration->value => 'illustration',
            ProductIndexSortField::VoiceActor->value => 'voice_actor',
            ProductIndexSortField::Author->value => 'author',
        ];

        foreach (ProductIndexSortField::cases() as $field) {
            $this->assertSame($expectedColumns[$field->value], $field->sqlColumn());
        }
    }

    public function test_it_exposes_default_sort_dropdown_order(): void
    {
        $this->assertSame([
            ProductIndexSortField::RJ->value,
            ProductIndexSortField::Score->value,
            ProductIndexSortField::Series->value,
            ProductIndexSortField::AgeCategory->value,
            ProductIndexSortField::Progress->value,
            ProductIndexSortField::Priority->value,
            ProductIndexSortField::TotalTimesReListened->value,
            ProductIndexSortField::ReListenValue->value,
            ProductIndexSortField::StartDate->value,
            ProductIndexSortField::FinishDate->value,
            ProductIndexSortField::AddedToTheSiteDate->value,
            ProductIndexSortField::UpdatedAt->value,
            ProductIndexSortField::Circle->value,
            ProductIndexSortField::Scenario->value,
            ProductIndexSortField::Illustration->value,
            ProductIndexSortField::VoiceActor->value,
            ProductIndexSortField::Author->value,
        ], array_column(ProductIndexSortField::normalizeLayout(null), 'field'));

        $visibleOptions = ProductIndexSortField::optionsFromLayout(ProductIndexSortField::normalizeLayout(null));

        $this->assertArrayHasKey(ProductIndexSortField::AddedToTheSiteDate->value, $visibleOptions);
        $this->assertArrayNotHasKey(ProductIndexSortField::UpdatedAt->value, $visibleOptions);
        $this->assertArrayNotHasKey(ProductIndexSortField::Circle->value, $visibleOptions);
    }

    public function test_it_normalizes_sort_dropdown_layout_order_and_visibility(): void
    {
        $layout = ProductIndexSortField::normalizeLayout([
            ['field' => ProductIndexSortField::Series->value, 'visible' => false],
            ['field' => 'not_real', 'visible' => true],
            ['field' => ProductIndexSortField::Score->value, 'visible' => true],
            ['field' => ProductIndexSortField::Series->value, 'visible' => true],
        ]);

        $this->assertSame([
            ProductIndexSortField::Series->value,
            ProductIndexSortField::Score->value,
            ProductIndexSortField::RJ->value,
            ProductIndexSortField::AgeCategory->value,
            ProductIndexSortField::Progress->value,
            ProductIndexSortField::Priority->value,
            ProductIndexSortField::TotalTimesReListened->value,
            ProductIndexSortField::ReListenValue->value,
            ProductIndexSortField::StartDate->value,
            ProductIndexSortField::FinishDate->value,
            ProductIndexSortField::AddedToTheSiteDate->value,
            ProductIndexSortField::UpdatedAt->value,
            ProductIndexSortField::Circle->value,
            ProductIndexSortField::Scenario->value,
            ProductIndexSortField::Illustration->value,
            ProductIndexSortField::VoiceActor->value,
            ProductIndexSortField::Author->value,
        ], array_column($layout, 'field'));
        $this->assertFalse($layout[0]['visible']);
        $this->assertTrue($layout[1]['visible']);
        $this->assertSame('Series', $layout[0]['label']);
    }

    public function test_it_exposes_visible_options_from_sort_dropdown_layout(): void
    {
        $layout = ProductIndexSortField::normalizeLayout([
            ['field' => ProductIndexSortField::Series->value, 'visible' => true],
            ['field' => ProductIndexSortField::Score->value, 'visible' => false],
        ]);

        $options = ProductIndexSortField::optionsFromLayout($layout);

        $this->assertSame(ProductIndexSortField::Series->label(), $options[ProductIndexSortField::Series->value]);
        $this->assertArrayNotHasKey(ProductIndexSortField::Score->value, $options);
        $this->assertSame([
            ['field' => ProductIndexSortField::Series->value, 'visible' => true],
            ['field' => ProductIndexSortField::Score->value, 'visible' => false],
            ['field' => ProductIndexSortField::RJ->value, 'visible' => true],
            ['field' => ProductIndexSortField::AgeCategory->value, 'visible' => true],
            ['field' => ProductIndexSortField::Progress->value, 'visible' => true],
            ['field' => ProductIndexSortField::Priority->value, 'visible' => true],
            ['field' => ProductIndexSortField::TotalTimesReListened->value, 'visible' => true],
            ['field' => ProductIndexSortField::ReListenValue->value, 'visible' => true],
            ['field' => ProductIndexSortField::StartDate->value, 'visible' => true],
            ['field' => ProductIndexSortField::FinishDate->value, 'visible' => true],
            ['field' => ProductIndexSortField::AddedToTheSiteDate->value, 'visible' => true],
            ['field' => ProductIndexSortField::UpdatedAt->value, 'visible' => false],
            ['field' => ProductIndexSortField::Circle->value, 'visible' => false],
            ['field' => ProductIndexSortField::Scenario->value, 'visible' => false],
            ['field' => ProductIndexSortField::Illustration->value, 'visible' => false],
            ['field' => ProductIndexSortField::VoiceActor->value, 'visible' => false],
            ['field' => ProductIndexSortField::Author->value, 'visible' => false],
        ], ProductIndexSortField::storageLayout($layout));
    }
}
