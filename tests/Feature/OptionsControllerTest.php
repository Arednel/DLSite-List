<?php

namespace Tests\Feature;

use App\Jobs\FetchProductTagsJob;
use App\Models\Genre;
use App\Models\Product;
use App\Models\TagRefetchRun;
use App\Models\TagRefetchWorkResult;
use App\Support\TagRefetch\DLSiteTagFetcher;
use App\Support\TagRefetch\TagRefetchService;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use RuntimeException;
use Tests\TestCase;

class OptionsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_options_page_renders_refetch_actions_and_checklist(): void
    {
        $product = Product::factory()->create([
            'work_name' => 'OPTIONS_WORK_TOKEN',
            'work_name_english' => 'OPTIONS_EN_TOKEN',
        ]);

        $this->get('/options')
            ->assertOk()
            ->assertSee('Options')
            ->assertSee('Refetch Tags')
            ->assertSee('Refetch all works')
            ->assertSee('Refetch selected works')
            ->assertSee('wire:model.live.debounce.250ms="search"', false)
            ->assertSee('name="product_ids[]"', false)
            ->assertDontSee('Go to latest refetch')
            ->assertSee($product->id)
            ->assertSee('OPTIONS_WORK_TOKEN')
            ->assertSee('OPTIONS_EN_TOKEN');
    }

    public function test_options_page_shows_empty_state_when_there_are_no_works(): void
    {
        $this->get('/options')
            ->assertOk()
            ->assertSee('No works available for tag refetch.')
            ->assertDontSee('Go to latest refetch')
            ->assertDontSee('Refetch selected works');
    }

    public function test_options_page_links_to_latest_refetch_when_one_exists(): void
    {
        $product = Product::factory()->create(['work_name' => 'LATEST_REFETCH_LINK_TOKEN']);
        $olderRun = app(TagRefetchService::class)->createRun([$product->id]);
        $latestRun = app(TagRefetchService::class)->createRun([$product->id]);

        $this->get('/options')
            ->assertOk()
            ->assertSee('Go to latest refetch')
            ->assertSee('href="'.route('options.refetch-tags.show', $latestRun).'"', false)
            ->assertDontSee('href="'.route('options.refetch-tags.show', $olderRun).'"', false);
    }

    public function test_starting_all_works_creates_run_and_dispatches_one_batch_job_per_product(): void
    {
        Bus::fake();

        $first = Product::factory()->create(['work_name' => 'REFETCH_ALL_FIRST_TOKEN']);
        $second = Product::factory()->create(['work_name' => 'REFETCH_ALL_SECOND_TOKEN']);

        $response = $this->post(route('options.refetch-tags.start'), [
            'scope' => 'all',
        ]);

        $run = TagRefetchRun::query()->firstOrFail();

        $response->assertRedirect(route('options.refetch-tags.show', $run));
        $this->assertSame(TagRefetchRun::STATUS_RUNNING, $run->status);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $run->selected_product_ids);
        $this->assertSame(2, $run->total_count);
        $this->assertSame(2, $run->results()->count());
        $this->assertNotNull($run->batch_id);

        Bus::assertBatched(function (PendingBatch $batch) use ($run, $first, $second): bool {
            return $batch->name === "Refetch tags #{$run->id}"
                && $batch->jobs->count() === 2
                && $batch->jobs->every(fn (FetchProductTagsJob $job): bool => in_array($job->productId, [$first->id, $second->id], true));
        });
    }

    public function test_starting_selected_works_only_queues_selected_products(): void
    {
        Bus::fake();

        $selected = Product::factory()->create(['work_name' => 'REFETCH_SELECTED_TOKEN']);
        $notSelected = Product::factory()->create(['work_name' => 'REFETCH_NOT_SELECTED_TOKEN']);

        $this->post(route('options.refetch-tags.start'), [
            'scope' => 'selected',
            'product_ids' => [$selected->id],
        ])->assertRedirect();

        $run = TagRefetchRun::query()->firstOrFail();

        $this->assertSame([$selected->id], $run->selected_product_ids);
        $this->assertDatabaseHas('tag_refetch_work_results', [
            'tag_refetch_run_id' => $run->id,
            'product_id' => $selected->id,
        ]);
        $this->assertDatabaseMissing('tag_refetch_work_results', [
            'tag_refetch_run_id' => $run->id,
            'product_id' => $notSelected->id,
        ]);

        Bus::assertBatched(function (PendingBatch $batch) use ($selected): bool {
            return $batch->jobs->count() === 1
                && $batch->jobs->first() instanceof FetchProductTagsJob
                && $batch->jobs->first()->productId === $selected->id;
        });
    }

    public function test_starting_refetch_requires_at_least_one_resolved_product(): void
    {
        Bus::fake();

        $this->from('/options')
            ->post(route('options.refetch-tags.start'), [
                'scope' => 'all',
            ])
            ->assertRedirect('/options')
            ->assertSessionHasErrors(['product_ids']);

        Product::factory()->create(['work_name' => 'REFETCH_VALIDATION_TOKEN']);

        $this->from('/options')
            ->post(route('options.refetch-tags.start'), [
                'scope' => 'selected',
                'product_ids' => [],
            ])
            ->assertRedirect('/options')
            ->assertSessionHasErrors(['product_ids']);

        Bus::assertNothingBatched();
    }

    public function test_status_endpoint_reports_progress_totals_and_completion_state(): void
    {
        $service = app(TagRefetchService::class);
        $first = Product::factory()->create();
        $second = Product::factory()->create();
        $run = $service->createRun([$first->id, $second->id]);

        $run->results()->where('product_id', $first->id)->firstOrFail()->forceFill([
            'status' => TagRefetchWorkResult::STATUS_FETCHED,
        ])->save();
        $run->results()->where('product_id', $second->id)->firstOrFail()->forceFill([
            'status' => TagRefetchWorkResult::STATUS_SKIPPED,
            'error' => 'GeoBlocked DLSite work',
        ])->save();

        $service->refreshRunProgress($run);
        $run->refresh();

        $this->getJson(route('options.refetch-tags.status', $run))
            ->assertOk()
            ->assertJson([
                'status' => TagRefetchRun::STATUS_REVIEW,
                'total' => 2,
                'processed' => 2,
                'fetched' => 1,
                'skipped' => 1,
                'failed' => 0,
                'complete' => true,
            ]);
    }

    public function test_fetch_job_stores_fetched_tags_and_diff_without_touching_product_tags(): void
    {
        $product = Product::factory()->create();
        $oldJapanese = $this->createGenre('Old JP Tag', Genre::TYPE_AUTO_GENERATED_JAPANESE);
        $oldEnglish = $this->createGenre('Old EN Tag', Genre::TYPE_AUTO_GENERATED_ENGLISH);
        $custom = $this->createGenre('Keep Custom Tag', Genre::TYPE_CUSTOM);
        $this->attachGenres($product, [$oldJapanese, $oldEnglish, $custom]);

        $service = app(TagRefetchService::class);
        $run = $service->createRun([$product->id]);
        $result = $run->results()->firstOrFail();
        $fetcher = new class extends DLSiteTagFetcher
        {
            public function fetch(string $workId): array
            {
                return [
                    'japanese' => ['New JP Tag'],
                    'english' => ['Old EN Tag', 'New EN Tag'],
                ];
            }
        };

        (new FetchProductTagsJob($run->id, $product->id))->handle($fetcher, $service);

        $result->refresh();
        $product->refresh()->load(['japaneseGenres', 'englishGenres', 'customGenres']);

        $this->assertSame(TagRefetchWorkResult::STATUS_FETCHED, $result->status);
        $this->assertSame(['New JP Tag'], $result->fetched_japanese_tags);
        $this->assertSame(['Old EN Tag', 'New EN Tag'], $result->fetched_english_tags);
        $this->assertSame(['New JP Tag'], $result->added_japanese_tags);
        $this->assertSame(['New EN Tag'], $result->added_english_tags);
        $this->assertSame(['Old JP Tag'], $result->stale_japanese_tags);
        $this->assertSame([], $result->stale_english_tags);
        $this->assertSame(['Old JP Tag'], $product->japaneseGenres->pluck('title')->all());
        $this->assertSame(['Old EN Tag'], $product->englishGenres->pluck('title')->all());
        $this->assertSame(['Keep Custom Tag'], $product->customGenres->pluck('title')->all());
    }

    public function test_fetch_job_skips_errors_and_custom_only_works(): void
    {
        $service = app(TagRefetchService::class);
        $failedProduct = Product::factory()->create();
        $deletedProduct = Product::factory()->create();
        $customOnlyProduct = Product::factory()->create([
            'maker_id' => null,
            'circle' => null,
            'description' => null,
            'description_english' => null,
        ]);
        $run = $service->createRun([$failedProduct->id, $deletedProduct->id, $customOnlyProduct->id]);
        $fetcher = new class extends DLSiteTagFetcher
        {
            public bool $calledForCustomOnly = false;

            public function fetch(string $workId): array
            {
                if ($workId !== 'not-custom-only') {
                    throw new RuntimeException('GeoBlocked DLSite work');
                }

                $this->calledForCustomOnly = true;

                return ['japanese' => [], 'english' => []];
            }
        };

        $deletedProduct->delete();

        (new FetchProductTagsJob($run->id, $failedProduct->id))->handle($fetcher, $service);
        (new FetchProductTagsJob($run->id, $deletedProduct->id))->handle($fetcher, $service);
        (new FetchProductTagsJob($run->id, $customOnlyProduct->id))->handle($fetcher, $service);

        $failedResult = $run->results()->where('product_id', $failedProduct->id)->firstOrFail();
        $deletedResult = $run->results()->where('product_id', $deletedProduct->id)->firstOrFail();
        $customOnlyResult = $run->results()->where('product_id', $customOnlyProduct->id)->firstOrFail();
        $run->refresh();

        $this->assertSame(TagRefetchWorkResult::STATUS_SKIPPED, $failedResult->status);
        $this->assertSame('GeoBlocked DLSite work', $failedResult->error);
        $this->assertSame(TagRefetchWorkResult::STATUS_SKIPPED, $deletedResult->status);
        $this->assertSame('Product no longer exists.', $deletedResult->error);
        $this->assertSame(TagRefetchWorkResult::STATUS_SKIPPED, $customOnlyResult->status);
        $this->assertSame('Custom-only work is skipped.', $customOnlyResult->error);
        $this->assertFalse($fetcher->calledForCustomOnly);
        $this->assertSame(TagRefetchRun::STATUS_REVIEW, $run->status);
        $this->assertSame(3, $run->skipped_count);
    }

    public function test_review_screen_shows_summary_and_expandable_work_details(): void
    {
        $product = Product::factory()->create(['work_name' => 'REVIEW_DETAILS_TOKEN']);
        $run = $this->createReviewRun($product, ['Old JP'], ['Old EN'], ['New JP'], ['New EN']);

        $this->get(route('options.refetch-tags.show', $run))
            ->assertOk()
            ->assertSee('Review')
            ->assertSee('New JP')
            ->assertSee('Stale EN')
            ->assertSee('options-refetch-progress', false)
            ->assertSee('+JP')
            ->assertSee('+EN')
            ->assertSee('-JP')
            ->assertSee('-EN')
            ->assertSee('title="New JP tags"', false)
            ->assertSee('title="Stale EN tags"', false)
            ->assertSee('REVIEW_DETAILS_TOKEN')
            ->assertSee('<details', false)
            ->assertSee('Apply Changes');
    }

    public function test_only_newest_review_run_shows_controls_and_can_be_applied(): void
    {
        $product = Product::factory()->create(['work_name' => 'NEWEST_REVIEW_TOKEN']);
        $olderRun = $this->createReviewRun($product, ['Older JP'], ['Older EN'], ['Older New JP'], ['Older New EN']);
        $newerRun = $this->createReviewRun($product, ['Newer JP'], ['Newer EN'], ['Newer New JP'], ['Newer New EN']);

        $this->get(route('options.refetch-tags.show', $olderRun))
            ->assertOk()
            ->assertSee('A newer refetch run exists. This run is read-only.')
            ->assertDontSee('Apply Changes')
            ->assertDontSee('name="work_actions', false);

        $this->post(route('options.refetch-tags.apply', $olderRun), [
            'global_japanese_action' => TagRefetchWorkResult::STALE_ACTION_MOVE_TO_CUSTOM,
            'global_english_action' => TagRefetchWorkResult::STALE_ACTION_MOVE_TO_CUSTOM,
        ])
            ->assertRedirect(route('options.refetch-tags.show', $olderRun))
            ->assertSessionHasErrors(['run']);

        $this->get(route('options.refetch-tags.show', $newerRun))
            ->assertOk()
            ->assertSee('Apply Changes')
            ->assertSee('name="work_actions', false);
    }

    public function test_apply_moves_stale_fetched_tags_to_custom_by_default(): void
    {
        $product = Product::factory()->create();
        $oldJapanese = $this->createGenre('Move Old JP', Genre::TYPE_AUTO_GENERATED_JAPANESE);
        $oldEnglish = $this->createGenre('Move Old EN', Genre::TYPE_AUTO_GENERATED_ENGLISH);
        $custom = $this->createGenre('Move Keep Custom', Genre::TYPE_CUSTOM);
        $this->attachGenres($product, [$oldJapanese, $oldEnglish, $custom]);
        $run = $this->createReviewRun($product, ['Move Old JP'], ['Move Old EN'], ['Move New JP'], ['Move New EN']);

        $this->post(route('options.refetch-tags.apply', $run), [
            'global_japanese_action' => TagRefetchWorkResult::STALE_ACTION_MOVE_TO_CUSTOM,
            'global_english_action' => TagRefetchWorkResult::STALE_ACTION_MOVE_TO_CUSTOM,
        ])->assertRedirect(route('options.refetch-tags.show', $run));

        $product->refresh()->load(['japaneseGenres', 'englishGenres', 'customGenres']);
        $run->refresh();

        $this->assertSame(TagRefetchRun::STATUS_APPLIED, $run->status);
        $this->assertSame(['Move New JP'], $product->japaneseGenres->pluck('title')->all());
        $this->assertSame(['Move New EN'], $product->englishGenres->pluck('title')->all());
        $this->assertEqualsCanonicalizing(
            ['Move Old JP', 'Move Old EN', 'Move Keep Custom'],
            $product->customGenres->pluck('title')->all()
        );
    }

    public function test_apply_can_remove_stale_tags_with_per_work_override_without_deleting_genre_rows(): void
    {
        $product = Product::factory()->create();
        $oldJapanese = $this->createGenre('Override Old JP', Genre::TYPE_AUTO_GENERATED_JAPANESE);
        $oldEnglish = $this->createGenre('Override Old EN', Genre::TYPE_AUTO_GENERATED_ENGLISH);
        $custom = $this->createGenre('Override Keep Custom', Genre::TYPE_CUSTOM);
        $this->attachGenres($product, [$oldJapanese, $oldEnglish, $custom]);
        $run = $this->createReviewRun($product, ['Override Old JP'], ['Override Old EN'], ['Override New JP'], ['Override New EN']);

        $this->post(route('options.refetch-tags.apply', $run), [
            'global_japanese_action' => TagRefetchWorkResult::STALE_ACTION_MOVE_TO_CUSTOM,
            'global_english_action' => TagRefetchWorkResult::STALE_ACTION_MOVE_TO_CUSTOM,
            'work_actions' => [
                $product->id => [
                    'japanese' => TagRefetchWorkResult::STALE_ACTION_REMOVE,
                    'english' => TagRefetchWorkResult::STALE_ACTION_REMOVE,
                ],
            ],
        ])->assertRedirect(route('options.refetch-tags.show', $run));

        $product->refresh()->load(['japaneseGenres', 'englishGenres', 'customGenres', 'genres']);

        $this->assertSame(['Override New JP'], $product->japaneseGenres->pluck('title')->all());
        $this->assertSame(['Override New EN'], $product->englishGenres->pluck('title')->all());
        $this->assertSame(['Override Keep Custom'], $product->customGenres->pluck('title')->all());
        $this->assertFalse($product->genres->pluck('title')->contains('Override Old JP'));
        $this->assertFalse($product->genres->pluck('title')->contains('Override Old EN'));
        $this->assertDatabaseHas('genres', ['title' => 'Override Old JP']);
        $this->assertDatabaseHas('genres', ['title' => 'Override Old EN']);
    }

    private function createReviewRun(
        Product $product,
        array $staleJapanese,
        array $staleEnglish,
        array $fetchedJapanese,
        array $fetchedEnglish,
    ): TagRefetchRun {
        $service = app(TagRefetchService::class);
        $run = $service->createRun([$product->id]);

        $run->results()->firstOrFail()->forceFill([
            'status' => TagRefetchWorkResult::STATUS_FETCHED,
            'fetched_japanese_tags' => $fetchedJapanese,
            'fetched_english_tags' => $fetchedEnglish,
            'added_japanese_tags' => $fetchedJapanese,
            'added_english_tags' => $fetchedEnglish,
            'stale_japanese_tags' => $staleJapanese,
            'stale_english_tags' => $staleEnglish,
        ])->save();

        $run->forceFill([
            'status' => TagRefetchRun::STATUS_REVIEW,
            'processed_count' => 1,
            'fetched_count' => 1,
            'completed_at' => now(),
        ])->save();

        return $run->refresh();
    }

    private function createGenre(string $title, string $type): Genre
    {
        return Genre::query()->create([
            'group_id' => null,
            'title' => $title,
            'description' => null,
            'order' => null,
            'type' => $type,
            'language' => $type === Genre::TYPE_AUTO_GENERATED_JAPANESE
                ? Genre::LANGUAGE_JAPANESE
                : Genre::LANGUAGE_ENGLISH,
        ]);
    }

    /**
     * @param  list<Genre>  $genres
     */
    private function attachGenres(Product $product, array $genres): void
    {
        $product->genres()->sync(
            collect($genres)
                ->mapWithKeys(fn (Genre $genre): array => [
                    $genre->id => [
                        'source' => $genre->type === Genre::TYPE_CUSTOM
                            ? Genre::PIVOT_SOURCE_CUSTOM
                            : Genre::PIVOT_SOURCE_FETCHED,
                    ],
                ])
                ->all()
        );
    }
}
