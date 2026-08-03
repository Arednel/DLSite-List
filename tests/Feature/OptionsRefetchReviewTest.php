<?php

namespace Tests\Feature;

use App\Enums\RefetchCategory;
use App\Livewire\OptionsRefetchReview;
use App\Models\Product;
use App\Models\RefetchRun;
use App\Models\RefetchWorkResult;
use App\Support\Refetch\RefetchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OptionsRefetchReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_opens_the_first_unresolved_changed_category_and_preserves_choices_between_tabs(): void
    {
        [$run,, $result] = $this->reviewRun();

        Livewire::test(OptionsRefetchReview::class, ['run' => $run])
            ->assertSet('activeCategory', RefetchCategory::Titles->value)
            ->set(
                "actions.titles.{$result->getKey()}.work_name",
                RefetchService::ACTION_IGNORE,
            )
            ->call('showCategory', RefetchCategory::Tags->value)
            ->assertSet('activeCategory', RefetchCategory::Tags->value)
            ->assertSet(
                "actions.titles.{$result->getKey()}.work_name",
                RefetchService::ACTION_IGNORE,
            );
    }

    public function test_apply_tab_preserves_unsaved_choices_for_other_tabs(): void
    {
        [$run,, $result] = $this->reviewRun();

        Livewire::test(OptionsRefetchReview::class, ['run' => $run])
            ->set('globalActions.titles', RefetchService::ACTION_OVERWRITE)
            ->set('globalActions.descriptions', RefetchService::ACTION_OVERWRITE)
            ->set(
                "actions.descriptions.{$result->getKey()}.description",
                RefetchService::ACTION_IGNORE,
            )
            ->call('askApplyTab', RefetchCategory::Titles->value)
            ->call('applyTab')
            ->assertNoRedirect()
            ->assertSet(
                'globalActions.descriptions',
                RefetchService::ACTION_OVERWRITE,
            )
            ->assertSet(
                "actions.descriptions.{$result->getKey()}.description",
                RefetchService::ACTION_IGNORE,
            );

        $this->assertTrue($run->refresh()->tabResolved(RefetchCategory::Titles));
        $this->assertFalse($run->tabResolved(RefetchCategory::Descriptions));
    }

    public function test_overwrite_all_updates_only_unresolved_changed_categories_and_preserves_overrides(): void
    {
        [$run,, $result] = $this->reviewRun();
        $run->forceFill([
            'resolved_tabs' => array_values(array_unique([
                ...$run->resolved_tabs,
                RefetchCategory::Descriptions->value,
            ])),
        ])->save();

        Livewire::test(OptionsRefetchReview::class, ['run' => $run])
            ->set(
                "actions.titles.{$result->getKey()}.work_name",
                RefetchService::ACTION_IGNORE,
            )
            ->call('overwriteAll')
            ->assertSet(
                'globalActions.' . RefetchCategory::Titles->value,
                RefetchService::ACTION_OVERWRITE,
            )
            ->assertSet(
                'globalActions.' . RefetchCategory::Tags->value,
                RefetchService::ACTION_OVERWRITE,
            )
            ->assertSet(
                'globalActions.' . RefetchCategory::Descriptions->value,
                RefetchService::ACTION_IGNORE,
            )
            ->assertSet(
                'globalActions.' . RefetchCategory::Series->value,
                RefetchService::ACTION_IGNORE,
            )
            ->assertSet(
                "actions.titles.{$result->getKey()}.work_name",
                RefetchService::ACTION_IGNORE,
            );
    }

    public function test_read_only_review_cannot_change_overwrite_all_presets(): void
    {
        [$run, $product] = $this->reviewRun();
        app(RefetchService::class)->createRun([$product->getKey()], false);

        Livewire::test(OptionsRefetchReview::class, ['run' => $run])
            ->call('overwriteAll')
            ->assertSet(
                'globalActions.' . RefetchCategory::Titles->value,
                RefetchService::ACTION_IGNORE,
            )
            ->assertSet(
                'globalActions.' . RefetchCategory::Tags->value,
                RefetchService::ACTION_IGNORE,
            )
            ->assertDontSee('Set Overwrite for All');
    }

    public function test_apply_actions_validate_bound_choices_before_calling_the_service(): void
    {
        [$run, $product] = $this->reviewRun();

        Livewire::test(OptionsRefetchReview::class, ['run' => $run])
            ->set('globalActions.titles', 'unexpected')
            ->call('askApplyTab', 'titles')
            ->call('applyTab')
            ->assertHasErrors('globalActions.titles')
            ->assertNoRedirect();

        $this->assertSame('Old Title', $product->refresh()->work_name);
        $this->assertSame(RefetchRun::STATUS_REVIEW, $run->refresh()->status);
    }

    /**
     * @return array{RefetchRun, Product, RefetchWorkResult}
     */
    private function reviewRun(): array
    {
        $product = Product::factory()->create([
            'work_name' => 'Old Title',
            'description' => 'Old Description',
        ]);
        $run = app(RefetchService::class)->createRun([$product->getKey()], false);
        $result = $run->results()->firstOrFail();
        $result->forceFill([
            'status' => RefetchWorkResult::STATUS_FETCHED,
            'changes' => [
                RefetchCategory::Titles->value => [
                    'work_name' => [
                        'label' => 'Japanese Title',
                        'old' => 'Old Title',
                        'new' => 'New Title',
                    ],
                ],
                RefetchCategory::Descriptions->value => [
                    'description' => [
                        'label' => 'Japanese Description',
                        'old' => 'Old Description',
                        'new' => 'New Description',
                    ],
                ],
                RefetchCategory::Tags->value => [
                    'tags' => [
                        'label' => 'Fetched Tags',
                        'old' => [
                            'japanese' => ['Old JP'],
                            'english' => [],
                            'custom' => [],
                        ],
                        'new' => [
                            'japanese' => ['New JP'],
                            'english' => [],
                            'custom' => [],
                        ],
                        'details' => [
                            'added_japanese' => ['New JP'],
                            'stale_japanese' => ['Old JP'],
                        ],
                    ],
                ],
            ],
        ])->save();
        $run->forceFill([
            'status' => RefetchRun::STATUS_REVIEW,
            'processed_count' => 1,
            'fetched_count' => 1,
            'completed_at' => now(),
            'resolved_tabs' => array_values(array_diff(
                RefetchCategory::values(),
                [
                    RefetchCategory::Titles->value,
                    RefetchCategory::Descriptions->value,
                    RefetchCategory::Tags->value,
                ],
            )),
        ])->save();

        return [$run, $product, $result];
    }
}
