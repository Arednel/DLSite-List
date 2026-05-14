<?php

namespace Tests\Unit\Enums;

use App\Enums\ProductIndexSortField;
use PHPUnit\Framework\TestCase;

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
        ];

        foreach (ProductIndexSortField::cases() as $field) {
            $this->assertSame($expectedColumns[$field->value], $field->sqlColumn());
        }
    }
}
