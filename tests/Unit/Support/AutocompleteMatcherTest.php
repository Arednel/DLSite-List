<?php

namespace Tests\Unit\Support;

use App\Support\Autocomplete\AutocompleteMatcher;
use Tests\TestCase;

class AutocompleteMatcherTest extends TestCase
{
    public function test_match_rank_prioritizes_first_word_before_later_word(): void
    {
        $matcher = new AutocompleteMatcher;

        $this->assertLessThan(
            $matcher->matchRank('One Two', 'tw'),
            $matcher->matchRank('Twenty', 'tw')
        );
    }

    public function test_match_rank_prioritizes_later_word_before_non_ascii_substring(): void
    {
        $matcher = new AutocompleteMatcher;

        $this->assertLessThan(
            $matcher->matchRank('癒しテスト', 'テス'),
            $matcher->matchRank('One テスト', 'テス')
        );
    }

    public function test_match_rank_falls_back_for_non_matching_values(): void
    {
        $matcher = new AutocompleteMatcher;

        $this->assertSame(3, $matcher->matchRank('Milady', 'lady'));
    }

    public function test_usage_comparison_ignores_match_rank(): void
    {
        $matcher = new AutocompleteMatcher;

        $this->assertLessThan(
            0,
            $matcher->compareSuggestions(
                ['value' => 'One Two', 'label' => 'One Two', 'count' => 3, 'type' => 'tag'],
                ['value' => 'Twenty', 'label' => 'Twenty', 'count' => 1, 'type' => 'tag'],
                'tw',
                false
            )
        );
    }
}
