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

    public function test_failed_results_and_warnings_use_the_collapsed_error_list(): void
    {
        [$run,, $result] = $this->reviewRun();
        $result->forceFill([
            'status' => RefetchWorkResult::STATUS_FAILED,
            'error' => 'Refetch failed.',
            'warnings' => [[
                'key' => 'Sample image download failed after five attempts: :images',
                'replace' => ['images' => 'sample_8.jpg'],
            ]],
        ])->save();

        Livewire::test(OptionsRefetchReview::class, ['run' => $run->fresh()])
            ->assertSee('Errors (2)')
            ->assertSee("{$result->product_id}: Refetch failed.")
            ->assertSee("{$result->product_id}: Sample image download failed after five attempts: sample_8.jpg")
            ->assertSee('<details class="review-errors">', false)
            ->assertHasNoErrors();
    }

    public function test_review_paginates_changes_and_resets_when_switching_categories(): void
    {
        [$run] = $this->reviewRunWithTitleChanges(101);

        $component = Livewire::test(OptionsRefetchReview::class, ['run' => $run])
            ->assertSee('Showing 1-100 of 101')
            ->assertSee('New Title for ', false)
            ->assertViewHas('activeReview', fn(array $review): bool =>
                $review['cards']->count() === 100
                && $review['cards']->total() === 101
                && $review['cards']->currentPage() === 1
            );

        $component
            ->call('nextPage')
            ->assertSet('paginators.page', 2)
            ->assertSee('Showing 101-101 of 101')
            ->assertSee('New Title for ', false)
            ->assertViewHas('activeReview', fn(array $review): bool =>
                $review['cards']->count() === 1
                && $review['cards']->total() === 101
                && $review['cards']->currentPage() === 2
            );

        $component
            ->call('previousPage')
            ->assertSet('paginators.page', 1)
            ->call('gotoPage', 2)
            ->assertSet('paginators.page', 2)
            ->call('showCategory', RefetchCategory::Descriptions->value)
            ->assertSet('paginators.page', 1)
            ->assertSee('No changes detected.');
    }

    public function test_review_paginates_change_cards_instead_of_work_results(): void
    {
        [$run] = $this->reviewRunWithTitleChanges(51, true);

        Livewire::test(OptionsRefetchReview::class, ['run' => $run])
            ->assertSee('Showing 1-100 of 102')
            ->assertViewHas('activeReview', fn(array $review): bool =>
                $review['count'] === 102
                && $review['cards']->count() === 100
                && $review['cards']->total() === 102
            )
            ->call('nextPage')
            ->assertSee('Showing 101-102 of 102')
            ->assertViewHas('activeReview', fn(array $review): bool =>
                $review['cards']->count() === 2
                && $review['cards']->total() === 102
                && $review['cards']->currentPage() === 2
            );
    }

    public function test_every_refetch_tab_keeps_its_associated_tabpanel_in_the_dom(): void
    {
        [$run] = $this->reviewRun();
        $component = Livewire::test(OptionsRefetchReview::class, ['run' => $run]);

        foreach (RefetchCategory::cases() as $category) {
            $panelId = "refetch-panel-{$run->getKey()}-{$category->value}";

            $component
                ->assertSee('aria-controls="' . $panelId . '"', false)
                ->assertSee('id="' . $panelId . '"', false);
        }
    }

    public function test_review_choices_survive_pagination_and_category_switches(): void
    {
        [$run, $products] = $this->reviewRunWithTitleChanges(101);
        $result = $run->results()->where('product_id', $products->firstOrFail()->getKey())->firstOrFail();

        Livewire::test(OptionsRefetchReview::class, ['run' => $run])
            ->set(
                "actions.titles.{$result->getKey()}.work_name",
                RefetchService::ACTION_IGNORE,
            )
            ->call('nextPage')
            ->call('showCategory', RefetchCategory::Descriptions->value)
            ->call('showCategory', RefetchCategory::Titles->value)
            ->assertSet('paginators.page', 1)
            ->assertSet(
                "actions.titles.{$result->getKey()}.work_name",
                RefetchService::ACTION_IGNORE,
            );
    }

    public function test_apply_tab_consumes_choices_across_all_review_pages(): void
    {
        [$run, $products] = $this->reviewRunWithTitleChanges(101);
        $firstProduct = $products->firstOrFail();
        $lastProduct = $products->last();
        $firstResult = $run->results()->where('product_id', $firstProduct->getKey())->firstOrFail();
        $lastResult = $run->results()->where('product_id', $lastProduct->getKey())->firstOrFail();

        Livewire::test(OptionsRefetchReview::class, ['run' => $run])
            ->set('globalActions.titles', RefetchService::ACTION_OVERWRITE)
            ->set(
                "actions.titles.{$firstResult->getKey()}.work_name",
                RefetchService::ACTION_IGNORE,
            )
            ->call('nextPage')
            ->call('askApplyTab', RefetchCategory::Titles->value)
            ->call('applyTab')
            ->assertNoRedirect();

        $this->assertSame('Old Title for ' . $firstProduct->id, $firstProduct->refresh()->work_name);
        $this->assertSame('New Title for ' . $lastProduct->id, $lastProduct->refresh()->work_name);
        $this->assertSame(
            RefetchService::ACTION_IGNORE,
            data_get($firstResult->refresh()->decisions, 'titles.work_name.action'),
        );
        $this->assertSame(
            RefetchService::ACTION_OVERWRITE,
            data_get($lastResult->refresh()->decisions, 'titles.work_name.action'),
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

    /**
     * @return array{RefetchRun, \Illuminate\Database\Eloquent\Collection<int, Product>}
     */
    private function reviewRunWithTitleChanges(int $count, bool $includeEnglishTitle = false): array
    {
        $products = Product::factory()->count($count)->create();
        $products->values()->each(function (Product $product) use ($includeEnglishTitle): void {
            $product->forceFill([
                'work_name' => "Old Title for {$product->getKey()}",
                ...($includeEnglishTitle
                    ? ['work_name_english' => "Old English Title for {$product->getKey()}"]
                    : []),
            ])->save();
        });

        $run = app(RefetchService::class)->createRun($products->modelKeys(), false);

        foreach ($run->results()->get() as $result) {
            $result->forceFill([
                'status' => RefetchWorkResult::STATUS_FETCHED,
                'changes' => [
                    RefetchCategory::Titles->value => [
                        'work_name' => [
                            'label' => 'Japanese Title',
                            'old' => "Old Title for {$result->product_id}",
                            'new' => "New Title for {$result->product_id}",
                        ],
                        ...($includeEnglishTitle
                            ? [
                                'work_name_english' => [
                                    'label' => 'English Title',
                                    'old' => "Old English Title for {$result->product_id}",
                                    'new' => "New English Title for {$result->product_id}",
                                ],
                            ]
                            : []),
                    ],
                ],
            ])->save();
        }

        $run->forceFill([
            'status' => RefetchRun::STATUS_REVIEW,
            'processed_count' => $count,
            'fetched_count' => $count,
            'completed_at' => now(),
            'resolved_tabs' => array_values(array_diff(
                RefetchCategory::values(),
                [
                    RefetchCategory::Titles->value,
                    RefetchCategory::Descriptions->value,
                ],
            )),
        ])->save();

        $productsById = $products->keyBy('id');
        $orderedProducts = $run->load('results.product')->results
            ->map(fn (RefetchWorkResult $result): Product => $productsById->get($result->product_id))
            ->values();

        return [$run, $orderedProducts];
    }
}
