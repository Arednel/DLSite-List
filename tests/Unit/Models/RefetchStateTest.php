<?php

namespace Tests\Unit\Models;

use App\Enums\RefetchCategory;
use App\Models\RefetchRun;
use App\Models\RefetchWorkResult;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Tests\TestCase;

class RefetchStateTest extends TestCase
{
    public function test_run_state_helpers_describe_review_visibility(): void
    {
        $running = new RefetchRun(['status' => RefetchRun::STATUS_RUNNING]);
        $cancelling = new RefetchRun(['status' => RefetchRun::STATUS_CANCELLING]);
        $review = new RefetchRun(['status' => RefetchRun::STATUS_REVIEW]);
        $applied = new RefetchRun(['status' => RefetchRun::STATUS_APPLIED]);
        $rejected = new RefetchRun(['status' => RefetchRun::STATUS_REJECTED]);

        $this->assertTrue($running->isRunning());
        $this->assertTrue($running->isActive());
        $this->assertTrue($running->canBeCancelled());
        $this->assertFalse($running->hasReviewResults());

        $this->assertTrue($cancelling->isCancelling());
        $this->assertTrue($cancelling->isActive());
        $this->assertFalse($cancelling->canBeCancelled());
        $this->assertFalse($cancelling->hasReviewResults());

        $this->assertTrue($review->isReview());
        $this->assertFalse($review->isActive());
        $this->assertFalse($review->canBeCancelled());
        $this->assertTrue($review->hasReviewResults());

        $this->assertTrue($applied->isApplied());
        $this->assertFalse($applied->isActive());
        $this->assertFalse($applied->canBeCancelled());
        $this->assertTrue($applied->hasReviewResults());
        $this->assertTrue($rejected->isRejected());
        $this->assertTrue($rejected->hasReviewResults());
    }

    public function test_work_result_state_helpers_describe_result_status(): void
    {
        $pending = new RefetchWorkResult(['status' => RefetchWorkResult::STATUS_PENDING]);
        $fetched = new RefetchWorkResult(['status' => RefetchWorkResult::STATUS_FETCHED]);
        $failed = new RefetchWorkResult(['status' => RefetchWorkResult::STATUS_FAILED]);

        $this->assertTrue($pending->isPending());
        $this->assertFalse($pending->isFetched());
        $this->assertFalse($pending->isFailed());

        $this->assertTrue($fetched->isFetched());
        $this->assertFalse($fetched->isPending());
        $this->assertFalse($fetched->isFailed());

        $this->assertTrue($failed->isFailed());
        $this->assertFalse($failed->isPending());
        $this->assertFalse($failed->isFetched());
    }

    public function test_work_result_change_helpers_describe_categories(): void
    {
        $result = new RefetchWorkResult([
            'changes' => [
                RefetchCategory::Titles->value => [
                    'work_name' => ['old' => 'Old', 'new' => 'New'],
                ],
            ],
        ]);

        $this->assertTrue($result->hasChangesFor(RefetchCategory::Titles));
        $this->assertFalse($result->hasChangesFor(RefetchCategory::Tags));
        $this->assertSame(
            ['work_name' => ['old' => 'Old', 'new' => 'New']],
            $result->changesFor(RefetchCategory::Titles),
        );
    }

    public function test_run_detects_applied_decisions_from_results(): void
    {
        $run = new RefetchRun;
        $run->setRelation('results', new EloquentCollection([
            new RefetchWorkResult([
                'decisions' => [
                    RefetchCategory::Titles->value => [
                        'work_name' => ['action' => 'overwrite'],
                    ],
                ],
            ]),
        ]));

        $this->assertTrue($run->hasAppliedDecisions());
    }

    public function test_work_result_detects_only_decisions_that_changed_the_work(): void
    {
        $unchanged = new RefetchWorkResult([
            'decisions' => [
                RefetchCategory::Titles->value => [
                    'work_name' => ['action' => 'ignore', 'changed' => false],
                ],
            ],
        ]);
        $changed = new RefetchWorkResult([
            'decisions' => [
                RefetchCategory::VoiceActor->value => [
                    'voice_actor' => ['action' => 'overwrite', 'changed' => true],
                ],
            ],
        ]);
        $this->assertFalse($unchanged->hasAppliedChanges());
        $this->assertTrue($changed->hasAppliedChanges());
    }
}
