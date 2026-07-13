<?php

namespace Tests\Unit\Models;

use App\Models\TagRefetchRun;
use App\Models\TagRefetchWorkResult;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use PHPUnit\Framework\TestCase;

class TagRefetchStateTest extends TestCase
{
    public function test_run_state_helpers_describe_review_visibility(): void
    {
        $running = new TagRefetchRun(['status' => TagRefetchRun::STATUS_RUNNING]);
        $cancelling = new TagRefetchRun;
        $cancelling->setRawAttributes([
            'status' => TagRefetchRun::STATUS_CANCELLING,
            'cancelled_at' => '2026-05-26 00:00:00',
        ], true);
        $review = new TagRefetchRun(['status' => TagRefetchRun::STATUS_REVIEW]);
        $applied = new TagRefetchRun(['status' => TagRefetchRun::STATUS_APPLIED]);

        $this->assertTrue($running->isRunning());
        $this->assertTrue($running->isActive());
        $this->assertTrue($running->canBeCancelled());
        $this->assertFalse($running->wasCancelled());
        $this->assertFalse($running->hasReviewResults());

        $this->assertTrue($cancelling->isCancelling());
        $this->assertTrue($cancelling->isActive());
        $this->assertTrue($cancelling->wasCancelled());
        $this->assertFalse($cancelling->canBeCancelled());
        $this->assertFalse($cancelling->hasReviewResults());
        $this->assertFalse($cancelling->canBeApplied());
        $this->assertSame('This refetch run is still cancelling.', $cancelling->applyUnavailableMessage());

        $this->assertTrue($review->isReview());
        $this->assertFalse($review->isActive());
        $this->assertFalse($review->canBeCancelled());
        $this->assertTrue($review->hasReviewResults());

        $this->assertTrue($applied->isApplied());
        $this->assertFalse($applied->isActive());
        $this->assertFalse($applied->canBeCancelled());
        $this->assertTrue($applied->hasReviewResults());
    }

    public function test_work_result_state_helpers_describe_result_status(): void
    {
        $pending = new TagRefetchWorkResult(['status' => TagRefetchWorkResult::STATUS_PENDING]);
        $fetched = new TagRefetchWorkResult(['status' => TagRefetchWorkResult::STATUS_FETCHED]);
        $skipped = new TagRefetchWorkResult(['status' => TagRefetchWorkResult::STATUS_SKIPPED]);

        $this->assertTrue($pending->isPending());
        $this->assertFalse($pending->isFetched());
        $this->assertFalse($pending->isSkipped());

        $this->assertTrue($fetched->isFetched());
        $this->assertFalse($fetched->isPending());
        $this->assertFalse($fetched->isSkipped());

        $this->assertTrue($skipped->isSkipped());
        $this->assertFalse($skipped->isPending());
        $this->assertFalse($skipped->isFetched());
    }

    public function test_work_result_change_helpers_describe_tag_buckets(): void
    {
        $changed = new TagRefetchWorkResult([
            'added_japanese_tags' => ['New JP'],
            'added_english_tags' => [],
            'stale_japanese_tags' => ['Old JP'],
            'stale_english_tags' => null,
            'custom_to_fetched_japanese_tags' => ['Custom JP'],
            'custom_to_fetched_english_tags' => [],
        ]);
        $englishOnly = new TagRefetchWorkResult([
            'custom_to_fetched_japanese_tags' => [],
            'custom_to_fetched_english_tags' => ['Custom EN'],
        ]);
        $unchanged = new TagRefetchWorkResult([
            'added_japanese_tags' => [],
            'added_english_tags' => [],
            'stale_japanese_tags' => [],
            'stale_english_tags' => [],
            'custom_to_fetched_japanese_tags' => [],
            'custom_to_fetched_english_tags' => [],
        ]);

        $this->assertTrue($changed->hasAddedJapaneseTags());
        $this->assertFalse($changed->hasAddedEnglishTags());
        $this->assertTrue($changed->hasStaleJapaneseTags());
        $this->assertFalse($changed->hasStaleEnglishTags());
        $this->assertTrue($changed->hasCustomToFetchedJapaneseTags());
        $this->assertFalse($changed->hasCustomToFetchedEnglishTags());
        $this->assertTrue($changed->hasCustomToFetchedTags());
        $this->assertTrue($changed->hasTagChanges());
        $this->assertFalse($englishOnly->hasCustomToFetchedJapaneseTags());
        $this->assertTrue($englishOnly->hasCustomToFetchedEnglishTags());
        $this->assertTrue($englishOnly->hasCustomToFetchedTags());
        $this->assertTrue($englishOnly->hasTagChanges());

        $this->assertFalse($unchanged->hasAddedJapaneseTags());
        $this->assertFalse($unchanged->hasAddedEnglishTags());
        $this->assertFalse($unchanged->hasStaleJapaneseTags());
        $this->assertFalse($unchanged->hasStaleEnglishTags());
        $this->assertFalse($unchanged->hasCustomToFetchedJapaneseTags());
        $this->assertFalse($unchanged->hasCustomToFetchedEnglishTags());
        $this->assertFalse($unchanged->hasCustomToFetchedTags());
        $this->assertFalse($unchanged->hasTagChanges());
    }

    public function test_run_summary_counts_result_tag_buckets(): void
    {
        $run = new TagRefetchRun;
        $run->setRelation('results', new EloquentCollection([
            new TagRefetchWorkResult([
                'status' => TagRefetchWorkResult::STATUS_FETCHED,
                'added_japanese_tags' => ['New JP 1', 'New JP 2'],
                'added_english_tags' => ['New EN'],
                'stale_japanese_tags' => [],
                'stale_english_tags' => ['Old EN'],
                'custom_to_fetched_japanese_tags' => ['Custom JP'],
                'custom_to_fetched_english_tags' => ['Custom EN'],
            ]),
            new TagRefetchWorkResult([
                'status' => TagRefetchWorkResult::STATUS_SKIPPED,
                'added_japanese_tags' => [],
                'added_english_tags' => [],
                'stale_japanese_tags' => ['Old JP'],
                'stale_english_tags' => [],
                'custom_to_fetched_japanese_tags' => [],
                'custom_to_fetched_english_tags' => [],
            ]),
        ]));

        $this->assertSame([
            'added_japanese' => 2,
            'added_english' => 1,
            'stale_japanese' => 1,
            'stale_english' => 1,
            'custom_to_fetched' => 2,
            'skipped' => 1,
        ], $run->summary());
    }
}
