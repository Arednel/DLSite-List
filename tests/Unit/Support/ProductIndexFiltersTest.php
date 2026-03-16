<?php

namespace Tests\Unit\Support;

use App\Enums\ProductIndexSortDirection;
use App\Enums\ProductIndexSortField;
use App\Enums\ProductIndexTagMatch;
use App\Enums\ProductPriority;
use App\Enums\ProductProgress;
use App\Support\ProductIndexFilters;
use PHPUnit\Framework\TestCase;

class ProductIndexFiltersTest extends TestCase
{
    public function test_it_normalizes_query_values_into_typed_filters(): void
    {
        $filters = ProductIndexFilters::fromQuery([
            'progress' => 'Listening',
            'priority' => '0',
            'tags' => '"Junior / Senior (at work, school, etc)", Office Lady',
            'tag_match' => 'any',
            'sort_first_field' => 'score',
            'sort_first_direction' => 'desc',
            'sort_second_field' => 'score',
            'sort_second_direction' => 'asc',
        ]);

        $this->assertSame(ProductProgress::Listening, $filters->progress);
        $this->assertSame(ProductPriority::Low, $filters->priority);
        $this->assertSame(ProductIndexTagMatch::Any, $filters->resolvedTagMatch());
        $this->assertSame(
            ['Junior / Senior (at work, school, etc)', 'Office Lady'],
            $filters->parsedTags(),
        );
        $this->assertSame(ProductIndexSortField::Score, $filters->primarySort?->field);
        $this->assertSame(ProductIndexSortDirection::Desc, $filters->primarySort?->direction);
        $this->assertNull($filters->secondarySort);
    }

    public function test_it_drops_invalid_values_from_query_output(): void
    {
        $filters = ProductIndexFilters::fromQuery([
            'progress' => 'NOT_VALID',
            'priority' => '-1',
            'num_re_listen_times' => 'abc',
            'sort_first_field' => 'priority',
        ]);

        $this->assertSame([
            'sort_first_field' => 'priority',
            'sort_first_direction' => 'desc',
        ], $filters->toQuery());
    }

    public function test_it_defaults_tag_match_and_sort_direction_for_new_filter_state(): void
    {
        $filters = ProductIndexFilters::fromQuery([
            'tags' => 'Office Lady',
            'sort_first_field' => 'score',
        ]);

        $this->assertSame(ProductIndexTagMatch::All, $filters->resolvedTagMatch());
        $this->assertSame(ProductIndexSortDirection::Desc, $filters->primarySort?->direction);
    }
}
