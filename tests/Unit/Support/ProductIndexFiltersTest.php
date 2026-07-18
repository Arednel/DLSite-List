<?php

namespace Tests\Unit\Support;

use App\Enums\ProductIndexSortDirection;
use App\Enums\ProductIndexSortField;
use App\Enums\ProductIndexTagMatch;
use App\Enums\ProductPriority;
use App\Enums\ProductProgress;
use App\Support\ProductIndexFilters;
use Tests\TestCase;

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
            'start_date_from' => '2026-02-31',
            'created_at_to' => '2026/01/01',
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

    public function test_date_range_filters_round_trip_through_input_and_query_output(): void
    {
        $filters = ProductIndexFilters::fromQuery([
            'start_date_from' => '2026-01-01',
            'start_date_to' => '2026-01-31',
            'end_date_from' => '2026-02-01',
            'end_date_to' => '2026-02-28',
            'created_at_from' => '2026-03-01',
            'created_at_to' => '2026-03-31',
            'updated_at_from' => '2026-04-01',
            'updated_at_to' => '2026-04-30',
        ]);

        $this->assertSame('2026-01-01', $filters->startDateFrom);
        $this->assertSame('2026-02-28', $filters->endDateTo);
        $this->assertSame('2026-03-01', $filters->createdAtFrom);
        $this->assertSame('2026-04-30', $filters->updatedAtTo);
        $this->assertSame([
            'start_date_from' => '2026-01-01',
            'start_date_to' => '2026-01-31',
            'end_date_from' => '2026-02-01',
            'end_date_to' => '2026-02-28',
            'created_at_from' => '2026-03-01',
            'created_at_to' => '2026-03-31',
            'updated_at_from' => '2026-04-01',
            'updated_at_to' => '2026-04-30',
        ], $filters->toQuery());
    }

    public function test_metadata_text_filters_round_trip_through_input_and_query_output(): void
    {
        $filters = ProductIndexFilters::fromQuery([
            'circle' => '  Circle Token  ',
            'scenario' => 'Scenario Token',
            'voice_actor' => 'Voice Token',
            'illustration' => 'Illustration Token',
            'author' => 'Author Token',
            'description' => 'Japanese Description Token',
            'description_english' => 'English Description Token',
        ]);

        $this->assertSame('Circle Token', $filters->circle);
        $this->assertSame('Scenario Token', $filters->scenario);
        $this->assertSame('Voice Token', $filters->voiceActor);
        $this->assertSame('Illustration Token', $filters->illustration);
        $this->assertSame('Author Token', $filters->author);
        $this->assertSame('Japanese Description Token', $filters->description);
        $this->assertSame('English Description Token', $filters->descriptionEnglish);
        $this->assertSame([
            'circle' => 'Circle Token',
            'scenario' => 'Scenario Token',
            'voice_actor' => 'Voice Token',
            'illustration' => 'Illustration Token',
            'author' => 'Author Token',
            'description' => 'Japanese Description Token',
            'description_english' => 'English Description Token',
        ], array_intersect_key($filters->toInput(), array_flip([
            'circle',
            'scenario',
            'voice_actor',
            'illustration',
            'author',
            'description',
            'description_english',
        ])));
        $this->assertSame([
            'circle' => 'Circle Token',
            'scenario' => 'Scenario Token',
            'voice_actor' => 'Voice Token',
            'illustration' => 'Illustration Token',
            'author' => 'Author Token',
            'description' => 'Japanese Description Token',
            'description_english' => 'English Description Token',
        ], $filters->toQuery());
    }

    public function test_it_exposes_the_index_input_keys_in_query_order(): void
    {
        $this->assertSame(
            array_keys((new ProductIndexFilters)->toInput()),
            ProductIndexFilters::INPUT_KEYS,
        );
    }

    public function test_it_exposes_visibility_filter_groups_separately_from_sort_and_page_state(): void
    {
        $this->assertSame([
            ['search'],
            ['title'],
            ['notes'],
            ['genre'],
            ['series'],
            ['circle'],
            ['scenario'],
            ['voice_actor'],
            ['illustration'],
            ['author'],
            ['description'],
            ['description_english'],
            ['tags', 'tag_match'],
            ['age_category'],
            ['progress'],
            ['score'],
            ['priority'],
            ['num_re_listen_times'],
            ['re_listen_value'],
            ['start_date_from', 'start_date_to'],
            ['end_date_from', 'end_date_to'],
            ['created_at_from', 'created_at_to'],
            ['updated_at_from', 'updated_at_to'],
        ], ProductIndexFilters::VISIBILITY_FILTER_GROUPS);

        $visibilityKeys = array_merge(...ProductIndexFilters::VISIBILITY_FILTER_GROUPS);

        $this->assertContains('sort_first_field', ProductIndexFilters::INPUT_KEYS);
        $this->assertContains('sort_second_direction', ProductIndexFilters::INPUT_KEYS);
        $this->assertNotContains('sort_first_field', $visibilityKeys);
        $this->assertNotContains('sort_second_direction', $visibilityKeys);
        $this->assertNotContains('page', ProductIndexFilters::INPUT_KEYS);
        $this->assertNotContains('page', $visibilityKeys);
    }

    public function test_visibility_filter_groups_cover_every_non_sort_input_key(): void
    {
        $sortKeys = [
            'sort_first_field',
            'sort_first_direction',
            'sort_second_field',
            'sort_second_direction',
        ];

        $visibilityKeys = array_unique(array_merge(...ProductIndexFilters::VISIBILITY_FILTER_GROUPS));
        $filterKeys = array_diff(ProductIndexFilters::INPUT_KEYS, $sortKeys);

        $this->assertSame([], array_values(array_diff($filterKeys, $visibilityKeys)));
    }

    public function test_it_can_drop_selected_keys_from_query_output(): void
    {
        $filters = ProductIndexFilters::fromQuery([
            'search' => 'rain',
            'genre' => '36',
            'progress' => 'Listening',
            'series' => 'SERIES_ALPHA',
        ]);

        $this->assertSame([
            'search' => 'rain',
            'series' => 'SERIES_ALPHA',
        ], $filters->toQueryWithout(['progress', 'genre']));

        $this->assertSame([
            'genre' => '36',
            'series' => 'SERIES_ALPHA',
            'progress' => 'Listening',
        ], $filters->toQueryWithout('search'));
    }

    public function test_option_sets_can_use_configured_visible_sort_fields(): void
    {
        $options = ProductIndexFilters::optionSets([
            ProductIndexSortField::Series->value => ProductIndexSortField::Series->label(),
            ProductIndexSortField::RJ->value => ProductIndexSortField::RJ->label(),
        ]);

        $this->assertSame([
            ProductIndexSortField::Series->value => 'Series',
            ProductIndexSortField::RJ->value => 'RJ / Title',
        ], $options['sort_fields']);
        $this->assertArrayHasKey('sort_directions', $options);
    }
}
