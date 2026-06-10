<?php

namespace Tests\Feature;

use App\Jobs\FetchProductTagsJob;
use App\Models\Genre;
use App\Models\Product;
use App\Models\TagRefetchRun;
use App\Models\TagRefetchWorkResult;
use App\Support\ProductGenreSync;
use App\Support\TagRefetch\DLSiteTagFetcher;
use App\Support\TagRefetch\TagRefetchService;
use Carbon\CarbonImmutable;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class OptionsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_options_page_renders_general_tab_by_default(): void
    {
        $product = Product::factory()->create([
            'work_name' => 'OPTIONS_WORK_TOKEN',
            'work_name_english' => 'OPTIONS_EN_TOKEN',
        ]);

        $this->get('/options')
            ->assertOk()
            ->assertSee('Options')
            ->assertSee('font-awesome/7.0.1/css/all.min.css', false)
            ->assertSee('href="/options?tab=general"', false)
            ->assertSee('href="/options?tab=field-layouts"', false)
            ->assertSee('href="/options?tab=refetch"', false)
            ->assertDontSee('href="/options?tab=options"', false)
            ->assertSee('Index Pagination')
            ->assertSee('Index page size')
            ->assertSee('Reset All Options')
            ->assertDontSee('Index Sort Fields')
            ->assertDontSee('Editable Fetched EN Tags')
            ->assertDontSee('Refetch Tags')
            ->assertDontSee('Refetch all works')
            ->assertDontSee('Refetch selected works')
            ->assertDontSee('OPTIONS_WORK_TOKEN')
            ->assertDontSee('OPTIONS_EN_TOKEN');
    }

    public function test_field_layouts_tab_renders_field_layout_settings(): void
    {
        $product = Product::factory()->create([
            'work_name' => 'OPTIONS_WORK_TOKEN',
            'work_name_english' => 'OPTIONS_EN_TOKEN',
        ]);

        $this->get('/options?tab=field-layouts')
            ->assertOk()
            ->assertSee('Options')
            ->assertSee('href="/options?tab=general"', false)
            ->assertSee('href="/options?tab=field-layouts"', false)
            ->assertSee('href="/options?tab=refetch"', false)
            ->assertDontSee('href="/options?tab=options"', false)
            ->assertSee('Field Layouts')
            ->assertSee('Index Table')
            ->assertSee('Index Sort Fields')
            ->assertSee('Editable Fetched EN Tags')
            ->assertSee('Reset All Options')
            ->assertDontSee('Index page size')
            ->assertDontSee('Refetch Tags')
            ->assertDontSee('Refetch all works')
            ->assertDontSee('Refetch selected works')
            ->assertDontSee('OPTIONS_WORK_TOKEN')
            ->assertDontSee('OPTIONS_EN_TOKEN')
            ->assertDontSee($product->id);
    }

    public function test_invalid_options_tab_falls_back_to_general(): void
    {
        $this->get('/options?tab=options')
            ->assertOk()
            ->assertSee('Index Pagination')
            ->assertSee('Index page size')
            ->assertDontSee('Index Sort Fields')
            ->assertDontSee('Refetch Tags');
    }

    public function test_refetch_tab_renders_refetch_actions_and_checklist(): void
    {
        $product = Product::factory()->create([
            'work_name' => 'OPTIONS_WORK_TOKEN',
            'work_name_english' => 'OPTIONS_EN_TOKEN',
        ]);

        $this->get('/options?tab=refetch')
            ->assertOk()
            ->assertSee('Options')
            ->assertSee('href="/options?tab=general"', false)
            ->assertSee('href="/options?tab=field-layouts"', false)
            ->assertSee('href="/options?tab=refetch"', false)
            ->assertDontSee('href="/options?tab=options"', false)
            ->assertSee('class="options-tab is-active"', false)
            ->assertDontSee('Index Pagination')
            ->assertDontSee('Index Sort Fields')
            ->assertSee('Refetch Tags')
            ->assertSee('Refetch all works')
            ->assertSee('Refetch selected works')
            ->assertSee('name="tab" value="refetch"', false)
            ->assertSee('wire:model.live.debounce.250ms="search"', false)
            ->assertSee('name="product_ids[]"', false)
            ->assertDontSee('Go to latest refetch')
            ->assertSee($product->id)
            ->assertSee('OPTIONS_WORK_TOKEN')
            ->assertSee('OPTIONS_EN_TOKEN');
    }

    public function test_options_page_shows_empty_state_when_there_are_no_works(): void
    {
        $this->get('/options?tab=refetch')
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

        $this->get('/options?tab=refetch')
            ->assertOk()
            ->assertSee('Go to latest refetch')
            ->assertSee('href="' . route('options.refetch-tags.show', $latestRun) . '"', false)
            ->assertDontSee('href="' . route('options.refetch-tags.show', $olderRun) . '"', false);
    }

    public function test_starting_all_works_creates_run_and_dispatches_one_batch_job_per_product(): void
    {
        Bus::fake();

        $first = Product::factory()->create([
            'id' => 'RJ000000002',
            'work_name' => 'REFETCH_ALL_FIRST_TOKEN',
        ]);
        $second = Product::factory()->create([
            'id' => 'RJ000000010',
            'work_name' => 'REFETCH_ALL_SECOND_TOKEN',
        ]);

        $response = $this->post(route('options.refetch-tags.start'), [
            'scope' => 'all',
        ]);

        $run = TagRefetchRun::query()->firstOrFail();

        $response->assertRedirect(route('options.refetch-tags.show', $run));
        $this->assertSame(TagRefetchRun::STATUS_RUNNING, $run->status);
        $this->assertSame([$second->id, $first->id], $run->selected_product_ids);
        $this->assertSame(2, $run->total_count);
        $this->assertSame(2, $run->results()->count());
        $this->assertNotNull($run->batch_id);

        Bus::assertBatched(function (PendingBatch $batch) use ($run, $first, $second): bool {
            $jobProductIds = $batch->jobs
                ->map(fn(FetchProductTagsJob $job): string => $job->productId)
                ->all();

            return $batch->name === "Refetch tags #{$run->id}"
                && $batch->jobs->count() === 2
                && $jobProductIds === [$second->id, $first->id];
        });
    }

    public function test_starting_selected_works_only_queues_selected_products_in_numeric_rj_desc_order(): void
    {
        Bus::fake();

        $selectedLow = Product::factory()->create([
            'id' => 'RJ000000002',
            'work_name' => 'REFETCH_SELECTED_LOW_TOKEN',
        ]);
        $selectedHigh = Product::factory()->create([
            'id' => 'RJ000000010',
            'work_name' => 'REFETCH_SELECTED_HIGH_TOKEN',
        ]);
        $notSelected = Product::factory()->create([
            'id' => 'RJ000000001',
            'work_name' => 'REFETCH_NOT_SELECTED_TOKEN',
        ]);

        $this->post(route('options.refetch-tags.start'), [
            'scope' => 'selected',
            'product_ids' => [$selectedLow->id, $selectedHigh->id],
        ])->assertRedirect();

        $run = TagRefetchRun::query()->firstOrFail();

        $this->assertSame([$selectedHigh->id, $selectedLow->id], $run->selected_product_ids);
        $this->assertDatabaseHas('tag_refetch_work_results', [
            'tag_refetch_run_id' => $run->id,
            'product_id' => $selectedLow->id,
        ]);
        $this->assertDatabaseHas('tag_refetch_work_results', [
            'tag_refetch_run_id' => $run->id,
            'product_id' => $selectedHigh->id,
        ]);
        $this->assertDatabaseMissing('tag_refetch_work_results', [
            'tag_refetch_run_id' => $run->id,
            'product_id' => $notSelected->id,
        ]);

        Bus::assertBatched(function (PendingBatch $batch) use ($selectedHigh, $selectedLow): bool {
            $jobProductIds = $batch->jobs
                ->map(fn(FetchProductTagsJob $job): string => $job->productId)
                ->all();

            return $batch->jobs->count() === 2
                && $jobProductIds === [$selectedHigh->id, $selectedLow->id];
        });
    }

    public function test_starting_refetch_requires_at_least_one_resolved_product(): void
    {
        Bus::fake();

        $this->from('/options?tab=refetch')
            ->post(route('options.refetch-tags.start'), [
                'scope' => 'all',
                'tab' => 'refetch',
            ])
            ->assertRedirect('/options?tab=refetch')
            ->assertSessionHasErrors(['product_ids']);

        Product::factory()->create(['work_name' => 'REFETCH_VALIDATION_TOKEN']);

        $this->from('/options?tab=refetch')
            ->post(route('options.refetch-tags.start'), [
                'scope' => 'selected',
                'product_ids' => [],
                'tab' => 'refetch',
            ])
            ->assertRedirect('/options?tab=refetch')
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

    public function test_status_endpoint_reports_cancelling_state_and_cancel_metadata(): void
    {
        $product = Product::factory()->create();
        $run = app(TagRefetchService::class)->createRun([$product->id]);
        $run->forceFill([
            'status' => TagRefetchRun::STATUS_CANCELLING,
            'cancelled_at' => now(),
        ])->save();

        $response = $this->getJson(route('options.refetch-tags.status', $run))
            ->assertOk()
            ->assertJson([
                'status' => TagRefetchRun::STATUS_CANCELLING,
                'total' => 1,
                'processed' => 0,
                'complete' => false,
            ]);

        $this->assertNotNull($response->json('cancelled_at'));
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
            public function __construct() {}

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

    public function test_fetch_job_marks_cancelled_batch_work_as_skipped_without_fetching(): void
    {
        $product = Product::factory()->create();
        $service = app(TagRefetchService::class);
        $run = $service->createRun([$product->id]);
        $fetcher = new class extends DLSiteTagFetcher
        {
            public bool $called = false;

            public function __construct() {}

            public function fetch(string $workId): array
            {
                $this->called = true;

                return ['japanese' => ['Should Not Fetch'], 'english' => []];
            }
        };

        [$job] = (new FetchProductTagsJob($run->id, $product->id))
            ->withFakeBatch(cancelledAt: CarbonImmutable::now());

        $job->handle($fetcher, $service);

        $result = $run->results()->firstOrFail();
        $run->refresh();

        $this->assertFalse($fetcher->called);
        $this->assertSame(TagRefetchWorkResult::STATUS_SKIPPED, $result->status);
        $this->assertSame(TagRefetchService::CANCELLED_BEFORE_FETCH_MESSAGE, $result->error);
        $this->assertSame(TagRefetchRun::STATUS_REVIEW, $run->status);
        $this->assertSame(1, $run->skipped_count);
    }

    public function test_fetch_job_records_current_fetch_when_run_is_cancelled_during_python_fetch(): void
    {
        $product = Product::factory()->create();
        $service = app(TagRefetchService::class);
        $run = $service->createRun([$product->id]);
        $fetcher = new class($run) extends DLSiteTagFetcher
        {
            public function __construct(private TagRefetchRun $run) {}

            public function fetch(string $workId): array
            {
                $this->run->forceFill([
                    'status' => TagRefetchRun::STATUS_CANCELLING,
                    'cancelled_at' => now(),
                ])->save();

                return ['japanese' => ['During Cancel JP'], 'english' => []];
            }
        };

        (new FetchProductTagsJob($run->id, $product->id))->handle($fetcher, $service);

        $result = $run->results()->firstOrFail();
        $run->refresh();

        $this->assertSame(TagRefetchWorkResult::STATUS_FETCHED, $result->status);
        $this->assertSame(['During Cancel JP'], $result->fetched_japanese_tags);
        $this->assertTrue($run->wasCancelled());
        $this->assertSame(TagRefetchRun::STATUS_REVIEW, $run->status);
        $this->assertSame(1, $run->fetched_count);
    }

    public function test_cancelling_run_moves_to_review_after_pending_results_settle(): void
    {
        $service = app(TagRefetchService::class);
        $first = Product::factory()->create();
        $second = Product::factory()->create();
        $run = $service->createRun([$first->id, $second->id]);

        $run->forceFill([
            'status' => TagRefetchRun::STATUS_CANCELLING,
            'cancelled_at' => now(),
        ])->save();

        $run->results()->where('product_id', $first->id)->firstOrFail()->forceFill([
            'status' => TagRefetchWorkResult::STATUS_FETCHED,
        ])->save();

        $service->refreshRunProgress($run);
        $run->refresh();

        $this->assertSame(TagRefetchRun::STATUS_CANCELLING, $run->status);
        $this->assertSame(1, $run->processed_count);

        $run->results()->where('product_id', $second->id)->firstOrFail()->forceFill([
            'status' => TagRefetchWorkResult::STATUS_SKIPPED,
            'error' => TagRefetchService::CANCELLED_BEFORE_FETCH_MESSAGE,
        ])->save();

        $service->refreshRunProgress($run);
        $run->refresh();

        $this->assertSame(TagRefetchRun::STATUS_REVIEW, $run->status);
        $this->assertSame(2, $run->processed_count);
        $this->assertSame(1, $run->fetched_count);
        $this->assertSame(1, $run->skipped_count);
        $this->assertNotNull($run->completed_at);
    }

    public function test_tag_diff_uses_relationship_titles_and_sorted_fetched_tags(): void
    {
        $product = Product::factory()->create();
        $zuluJapanese = $this->createGenre('Zulu JP', Genre::TYPE_AUTO_GENERATED_JAPANESE);
        $alphaJapanese = $this->createGenre('Alpha JP', Genre::TYPE_AUTO_GENERATED_JAPANESE);
        $zuluEnglish = $this->createGenre('Zulu EN', Genre::TYPE_AUTO_GENERATED_ENGLISH);
        $alphaEnglish = $this->createGenre('Alpha EN', Genre::TYPE_AUTO_GENERATED_ENGLISH);
        $custom = $this->createGenre('Already Custom', Genre::TYPE_CUSTOM);

        $this->attachGenres($product, [
            $zuluJapanese,
            $custom,
            $zuluEnglish,
            $alphaJapanese,
            $alphaEnglish,
        ]);

        $diff = app(TagRefetchService::class)->diffProductTags($product, [], ['Already Custom']);

        $this->assertSame(['Alpha JP', 'Zulu JP'], $diff['stale_japanese_tags']);
        $this->assertSame(['Alpha EN', 'Zulu EN'], $diff['stale_english_tags']);
        $this->assertSame([], $diff['added_english_tags']);
    }

    public function test_tag_diff_detects_language_bucket_changes_and_custom_to_fetched_overlap(): void
    {
        $product = Product::factory()->create();
        $asmr = $this->createGenre('ASMR', Genre::TYPE_AUTO_GENERATED_JAPANESE);
        $customOverlap = $this->createGenre('Custom Overlap', Genre::TYPE_CUSTOM);
        $this->attachGenres($product, [$asmr, $customOverlap]);

        $diff = app(TagRefetchService::class)->diffProductTags(
            $product,
            ['ASMR', 'Custom Overlap'],
            ['ASMR', 'Custom Overlap'],
        );

        $this->assertSame([], $diff['added_japanese_tags']);
        $this->assertSame(['ASMR'], $diff['added_english_tags']);
        $this->assertSame([], $diff['stale_japanese_tags']);
        $this->assertSame([], $diff['stale_english_tags']);
        $this->assertSame(['Custom Overlap'], $diff['custom_to_fetched_japanese_tags']);
        $this->assertSame(['Custom Overlap'], $diff['custom_to_fetched_english_tags']);
    }

    public function test_tag_diff_uses_case_insensitive_but_kana_sensitive_tag_identity(): void
    {
        $product = Product::factory()->create();
        $caseOnly = $this->createGenre('ASMR', Genre::TYPE_AUTO_GENERATED_JAPANESE);
        $hiragana = $this->createGenre('かなタグ', Genre::TYPE_AUTO_GENERATED_JAPANESE);
        $this->attachGenres($product, [$caseOnly, $hiragana]);

        $diff = app(TagRefetchService::class)->diffProductTags(
            $product,
            ['asmr', 'かなタグ', 'カナタグ'],
            [],
        );

        $this->assertSame(['カナタグ'], $diff['added_japanese_tags']);
        $this->assertSame([], $diff['stale_japanese_tags']);
    }

    public function test_tag_diff_detects_custom_to_fetched_overlap_case_insensitively(): void
    {
        $product = Product::factory()->create();
        $custom = $this->createGenre('ASMR', Genre::TYPE_CUSTOM);
        $this->attachGenres($product, [$custom]);

        $diff = app(TagRefetchService::class)->diffProductTags($product, ['asmr'], []);

        $this->assertSame([], $diff['added_japanese_tags']);
        $this->assertSame(['asmr'], $diff['custom_to_fetched_japanese_tags']);
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

            public function __construct() {}

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

    public function test_running_refetch_page_shows_cancel_form_only_while_running(): void
    {
        $product = Product::factory()->create(['work_name' => 'CANCEL_FORM_TOKEN']);
        $run = app(TagRefetchService::class)->createRun([$product->id]);

        $this->get(route('options.refetch-tags.show', $run))
            ->assertOk()
            ->assertSee('Cancel Refetch')
            ->assertSee('action="' . route('options.refetch-tags.cancel', $run) . '"', false);

        $run->forceFill([
            'status' => TagRefetchRun::STATUS_CANCELLING,
            'cancelled_at' => now(),
        ])->save();

        $this->get(route('options.refetch-tags.show', $run))
            ->assertOk()
            ->assertSee('Cancelling')
            ->assertDontSee('Cancel Refetch');

        $run->forceFill([
            'status' => TagRefetchRun::STATUS_REVIEW,
            'completed_at' => now(),
        ])->save();

        $this->get(route('options.refetch-tags.show', $run))
            ->assertOk()
            ->assertDontSee('Cancel Refetch');
    }

    public function test_cancel_refetch_cancels_only_the_selected_run_batch(): void
    {
        $first = Product::factory()->create();
        $second = Product::factory()->create();
        $service = app(TagRefetchService::class);
        $run = $service->createRun([$first->id]);
        $newerRun = $service->createRun([$second->id]);
        $batchId = $this->createBatchRecord();
        $newerBatchId = $this->createBatchRecord();

        $run->forceFill(['batch_id' => $batchId])->save();
        $newerRun->forceFill(['batch_id' => $newerBatchId])->save();

        $this->post(route('options.refetch-tags.cancel', $run))
            ->assertRedirect(route('options.refetch-tags.show', $run))
            ->assertSessionHasNoErrors();

        $run->refresh();
        $newerRun->refresh();

        $this->assertSame(TagRefetchRun::STATUS_CANCELLING, $run->status);
        $this->assertTrue($run->wasCancelled());
        $this->assertSame(TagRefetchRun::STATUS_RUNNING, $newerRun->status);
        $this->assertNull($newerRun->cancelled_at);
        $this->assertNotNull(DB::table('job_batches')->where('id', $batchId)->value('cancelled_at'));
        $this->assertNull(DB::table('job_batches')->where('id', $newerBatchId)->value('cancelled_at'));
    }

    public function test_cancel_refetch_is_harmless_when_run_is_already_cancelling(): void
    {
        $product = Product::factory()->create();
        $run = app(TagRefetchService::class)->createRun([$product->id]);
        $cancelledAt = now()->subMinute();

        $run->forceFill([
            'status' => TagRefetchRun::STATUS_CANCELLING,
            'cancelled_at' => $cancelledAt,
        ])->save();

        $this->post(route('options.refetch-tags.cancel', $run))
            ->assertRedirect(route('options.refetch-tags.show', $run))
            ->assertSessionHasNoErrors();

        $run->refresh();

        $this->assertSame(TagRefetchRun::STATUS_CANCELLING, $run->status);
        $this->assertNotNull($run->cancelled_at);
    }

    public function test_review_and_applied_refetch_runs_cannot_be_cancelled(): void
    {
        $product = Product::factory()->create();
        $reviewRun = $this->createReviewRun($product, [], [], [], []);
        $appliedRun = $this->createReviewRun($product, [], [], [], []);
        $appliedRun->forceFill([
            'status' => TagRefetchRun::STATUS_APPLIED,
            'applied_at' => now(),
        ])->save();

        $this->post(route('options.refetch-tags.cancel', $reviewRun))
            ->assertRedirect(route('options.refetch-tags.show', $reviewRun))
            ->assertSessionHasErrors(['run']);

        $this->post(route('options.refetch-tags.cancel', $appliedRun))
            ->assertRedirect(route('options.refetch-tags.show', $appliedRun))
            ->assertSessionHasErrors(['run']);

        $this->assertSame(TagRefetchRun::STATUS_REVIEW, $reviewRun->refresh()->status);
        $this->assertSame(TagRefetchRun::STATUS_APPLIED, $appliedRun->refresh()->status);
    }

    public function test_review_screen_shows_summary_and_expandable_work_details(): void
    {
        $product = Product::factory()->create(['work_name' => 'REVIEW_DETAILS_TOKEN']);
        $run = $this->createReviewRun(
            $product,
            ['Old JP'],
            ['Old EN'],
            ['New JP'],
            ['New EN'],
            ['Custom JP'],
            ['Custom EN'],
        );

        $this->get(route('options.refetch-tags.show', $run))
            ->assertOk()
            ->assertSee('Review')
            ->assertSee('New JP')
            ->assertSee('Stale EN')
            ->assertSee('Custom -> Fetched', false)
            ->assertSee('options-refetch-progress', false)
            ->assertSee('+JP')
            ->assertSee('+EN')
            ->assertSee('-JP')
            ->assertSee('-EN')
            ->assertSee('C->F', false)
            ->assertSee('title="New JP tags"', false)
            ->assertSee('title="Stale EN tags"', false)
            ->assertSee('title="Custom tags now fetched"', false)
            ->assertSee('REVIEW_DETAILS_TOKEN')
            ->assertSee('<details', false)
            ->assertSee('name="global_added_japanese_action"', false)
            ->assertSee('name="global_added_english_action"', false)
            ->assertSee('name="work_actions[' . $product->id . '][added_japanese]"', false)
            ->assertSee('name="work_actions[' . $product->id . '][added_english]"', false)
            ->assertSee('name="global_custom_to_fetched_action"', false)
            ->assertSee('name="work_actions[' . $product->id . '][custom_to_fetched]"', false)
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

    public function test_apply_can_ignore_new_fetched_tags_with_global_actions(): void
    {
        $product = Product::factory()->create();
        $run = $this->createReviewRun($product, [], [], ['Ignore New JP'], ['Ignore New EN']);

        $this->post(route('options.refetch-tags.apply', $run), [
            'global_japanese_action' => TagRefetchWorkResult::STALE_ACTION_MOVE_TO_CUSTOM,
            'global_english_action' => TagRefetchWorkResult::STALE_ACTION_MOVE_TO_CUSTOM,
            'global_added_japanese_action' => TagRefetchWorkResult::ADDED_ACTION_IGNORE,
            'global_added_english_action' => TagRefetchWorkResult::ADDED_ACTION_IGNORE,
        ])->assertRedirect(route('options.refetch-tags.show', $run));

        $product->refresh()->load(['japaneseGenres', 'englishGenres', 'customGenres']);
        $result = $run->results()->firstOrFail();

        $this->assertSame([], $product->japaneseGenres->pluck('title')->all());
        $this->assertSame([], $product->englishGenres->pluck('title')->all());
        $this->assertSame([], $product->customGenres->pluck('title')->all());
        $this->assertSame(TagRefetchWorkResult::ADDED_ACTION_IGNORE, $result->added_japanese_action);
        $this->assertSame(TagRefetchWorkResult::ADDED_ACTION_IGNORE, $result->added_english_action);
    }

    public function test_apply_can_override_new_fetched_tag_action_per_work(): void
    {
        $product = Product::factory()->create();
        $run = $this->createReviewRun($product, [], [], ['Override New JP'], ['Override New EN']);

        $this->post(route('options.refetch-tags.apply', $run), [
            'global_japanese_action' => TagRefetchWorkResult::STALE_ACTION_MOVE_TO_CUSTOM,
            'global_english_action' => TagRefetchWorkResult::STALE_ACTION_MOVE_TO_CUSTOM,
            'global_added_japanese_action' => TagRefetchWorkResult::ADDED_ACTION_IGNORE,
            'global_added_english_action' => TagRefetchWorkResult::ADDED_ACTION_IGNORE,
            'work_actions' => [
                $product->id => [
                    'added_english' => TagRefetchWorkResult::ADDED_ACTION_ADD,
                ],
            ],
        ])->assertRedirect(route('options.refetch-tags.show', $run));

        $product->refresh()->load(['japaneseGenres', 'englishGenres', 'customGenres']);
        $result = $run->results()->firstOrFail();

        $this->assertSame([], $product->japaneseGenres->pluck('title')->all());
        $this->assertSame(['Override New EN'], $product->englishGenres->pluck('title')->all());
        $this->assertSame([], $product->customGenres->pluck('title')->all());
        $this->assertSame(TagRefetchWorkResult::ADDED_ACTION_IGNORE, $result->added_japanese_action);
        $this->assertSame(TagRefetchWorkResult::ADDED_ACTION_ADD, $result->added_english_action);
    }

    public function test_cancelled_partial_review_run_can_apply_completed_fetched_results(): void
    {
        $fetchedProduct = Product::factory()->create();
        $skippedProduct = Product::factory()->create();
        $run = app(TagRefetchService::class)->createRun([$fetchedProduct->id, $skippedProduct->id]);

        $run->results()->where('product_id', $fetchedProduct->id)->firstOrFail()->forceFill([
            'status' => TagRefetchWorkResult::STATUS_FETCHED,
            'fetched_japanese_tags' => ['Partial JP'],
            'fetched_english_tags' => [],
            'added_japanese_tags' => ['Partial JP'],
            'added_english_tags' => [],
            'stale_japanese_tags' => [],
            'stale_english_tags' => [],
            'custom_to_fetched_japanese_tags' => [],
            'custom_to_fetched_english_tags' => [],
        ])->save();

        $run->results()->where('product_id', $skippedProduct->id)->firstOrFail()->forceFill([
            'status' => TagRefetchWorkResult::STATUS_SKIPPED,
            'error' => TagRefetchService::CANCELLED_BEFORE_FETCH_MESSAGE,
        ])->save();

        $run->forceFill([
            'status' => TagRefetchRun::STATUS_REVIEW,
            'processed_count' => 2,
            'fetched_count' => 1,
            'skipped_count' => 1,
            'cancelled_at' => now(),
            'completed_at' => now(),
        ])->save();

        $this->post(route('options.refetch-tags.apply', $run), [
            'global_japanese_action' => TagRefetchWorkResult::STALE_ACTION_MOVE_TO_CUSTOM,
            'global_english_action' => TagRefetchWorkResult::STALE_ACTION_MOVE_TO_CUSTOM,
        ])->assertRedirect(route('options.refetch-tags.show', $run));

        $fetchedProduct->refresh()->load('japaneseGenres');
        $skippedProduct->refresh()->load('japaneseGenres');
        $run->refresh();

        $this->assertSame(TagRefetchRun::STATUS_APPLIED, $run->status);
        $this->assertTrue($run->wasCancelled());
        $this->assertSame(['Partial JP'], $fetchedProduct->japaneseGenres->pluck('title')->all());
        $this->assertSame([], $skippedProduct->japaneseGenres->pluck('title')->all());
    }

    public function test_apply_can_store_hiragana_and_katakana_fetched_tags_on_the_same_work(): void
    {
        $product = Product::factory()->create();
        $hiragana = $this->createGenre('かなタグ', Genre::TYPE_AUTO_GENERATED_JAPANESE);
        $this->attachGenres($product, [$hiragana]);
        $run = $this->createReviewRun($product, [], [], ['かなタグ', 'カナタグ'], []);

        $this->post(route('options.refetch-tags.apply', $run), [
            'global_japanese_action' => TagRefetchWorkResult::STALE_ACTION_MOVE_TO_CUSTOM,
            'global_english_action' => TagRefetchWorkResult::STALE_ACTION_MOVE_TO_CUSTOM,
        ])->assertRedirect(route('options.refetch-tags.show', $run));

        $product->refresh()->load('japaneseGenres');

        $this->assertEqualsCanonicalizing(
            ['かなタグ', 'カナタグ'],
            $product->japaneseGenres->pluck('title')->all(),
        );
        $this->assertSame(2, Genre::query()
            ->whereIn('title_key', [Genre::titleKey('かなタグ'), Genre::titleKey('カナタグ')])
            ->count());
    }

    public function test_apply_promotes_custom_to_fetched_overlap_by_default(): void
    {
        $product = Product::factory()->create();
        $overlap = $this->createGenre('Promote Overlap', Genre::TYPE_CUSTOM);
        $this->attachGenres($product, [$overlap]);
        $run = $this->createReviewRun(
            $product,
            [],
            [],
            ['Promote Overlap'],
            ['Promote Overlap'],
            ['Promote Overlap'],
            ['Promote Overlap'],
        );

        $this->post(route('options.refetch-tags.apply', $run), [
            'global_japanese_action' => TagRefetchWorkResult::STALE_ACTION_MOVE_TO_CUSTOM,
            'global_english_action' => TagRefetchWorkResult::STALE_ACTION_MOVE_TO_CUSTOM,
            'global_custom_to_fetched_action' => TagRefetchWorkResult::CUSTOM_TO_FETCHED_ACTION_PROMOTE,
        ])->assertRedirect(route('options.refetch-tags.show', $run));

        $product->refresh()->load(['japaneseGenres', 'englishGenres', 'customGenres']);

        $this->assertSame(['Promote Overlap'], $product->japaneseGenres->pluck('title')->all());
        $this->assertSame(['Promote Overlap'], $product->englishGenres->pluck('title')->all());
        $this->assertSame([], $product->customGenres->pluck('title')->all());
        $this->assertGenreLanguages($product, $overlap, [Genre::LANGUAGE_JAPANESE, Genre::LANGUAGE_ENGLISH]);
    }

    public function test_apply_can_keep_custom_to_fetched_overlap_with_per_work_override(): void
    {
        $product = Product::factory()->create();
        $overlap = $this->createGenre('Keep Overlap', Genre::TYPE_CUSTOM);
        $this->attachGenres($product, [$overlap]);
        $run = $this->createReviewRun(
            $product,
            [],
            [],
            [],
            ['Keep Overlap'],
            [],
            ['Keep Overlap'],
        );

        $this->post(route('options.refetch-tags.apply', $run), [
            'global_japanese_action' => TagRefetchWorkResult::STALE_ACTION_MOVE_TO_CUSTOM,
            'global_english_action' => TagRefetchWorkResult::STALE_ACTION_MOVE_TO_CUSTOM,
            'global_custom_to_fetched_action' => TagRefetchWorkResult::CUSTOM_TO_FETCHED_ACTION_PROMOTE,
            'work_actions' => [
                $product->id => [
                    'custom_to_fetched' => TagRefetchWorkResult::CUSTOM_TO_FETCHED_ACTION_KEEP_CUSTOM,
                ],
            ],
        ])->assertRedirect(route('options.refetch-tags.show', $run));

        $product->refresh()->load(['japaneseGenres', 'englishGenres', 'customGenres']);

        $this->assertSame([], $product->japaneseGenres->pluck('title')->all());
        $this->assertSame([], $product->englishGenres->pluck('title')->all());
        $this->assertSame(['Keep Overlap'], $product->customGenres->pluck('title')->all());
        $this->assertGenreLanguages($product, $overlap, []);
    }

    public function test_apply_removes_only_stale_language_when_tag_is_still_fetched_in_another_language(): void
    {
        $product = Product::factory()->create();
        $asmr = $this->createGenre('Language Move ASMR', Genre::TYPE_AUTO_GENERATED_JAPANESE);
        $this->attachGenres($product, [$asmr]);
        $run = $this->createReviewRun($product, ['Language Move ASMR'], [], [], ['Language Move ASMR']);

        $this->post(route('options.refetch-tags.apply', $run), [
            'global_japanese_action' => TagRefetchWorkResult::STALE_ACTION_REMOVE,
            'global_english_action' => TagRefetchWorkResult::STALE_ACTION_MOVE_TO_CUSTOM,
            'global_custom_to_fetched_action' => TagRefetchWorkResult::CUSTOM_TO_FETCHED_ACTION_PROMOTE,
        ])->assertRedirect(route('options.refetch-tags.show', $run));

        $product->refresh()->load(['japaneseGenres', 'englishGenres', 'customGenres']);

        $this->assertSame([], $product->japaneseGenres->pluck('title')->all());
        $this->assertSame(['Language Move ASMR'], $product->englishGenres->pluck('title')->all());
        $this->assertSame([], $product->customGenres->pluck('title')->all());
        $this->assertGenreLanguages($product, $asmr, [Genre::LANGUAGE_ENGLISH]);
    }

    private function createReviewRun(
        Product $product,
        array $staleJapanese,
        array $staleEnglish,
        array $fetchedJapanese,
        array $fetchedEnglish,
        array $customToFetchedJapanese = [],
        array $customToFetchedEnglish = [],
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
            'custom_to_fetched_japanese_tags' => $customToFetchedJapanese,
            'custom_to_fetched_english_tags' => $customToFetchedEnglish,
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
        $genre = Genre::query()->create([
            'group_id' => null,
            'title' => $title,
            'description' => null,
            'order' => null,
        ]);

        $genre->setAttribute('type', $type);

        return $genre;
    }

    /**
     * @param  list<Genre>  $genres
     */
    private function attachGenres(Product $product, array $genres): void
    {
        $fetchedByLanguage = [
            Genre::LANGUAGE_JAPANESE => [],
            Genre::LANGUAGE_ENGLISH => [],
        ];
        $customGenreIds = [];

        foreach ($genres as $genre) {
            match ($genre->getAttribute('type')) {
                Genre::TYPE_AUTO_GENERATED_JAPANESE => $fetchedByLanguage[Genre::LANGUAGE_JAPANESE][] = $genre->getKey(),
                Genre::TYPE_AUTO_GENERATED_ENGLISH => $fetchedByLanguage[Genre::LANGUAGE_ENGLISH][] = $genre->getKey(),
                default => $customGenreIds[] = $genre->getKey(),
            };
        }

        app(ProductGenreSync::class)->sync($product, $fetchedByLanguage, $customGenreIds);
    }

    private function assertGenreLanguages(Product $product, Genre $genre, array $languages): void
    {
        $pivotId = DB::table('genre_product')
            ->where('product_id', $product->getKey())
            ->where('genre_id', $genre->getKey())
            ->value('id');

        $this->assertEqualsCanonicalizing(
            $languages,
            DB::table('genre_product_languages')
                ->where('genre_product_id', $pivotId)
                ->pluck('language')
                ->all()
        );
    }

    private function createBatchRecord(): string
    {
        $id = (string) Str::orderedUuid();

        DB::table('job_batches')->insert([
            'id' => $id,
            'name' => "Refetch tags {$id}",
            'total_jobs' => 1,
            'pending_jobs' => 1,
            'failed_jobs' => 0,
            'failed_job_ids' => '[]',
            'options' => serialize([]),
            'cancelled_at' => null,
            'created_at' => time(),
            'finished_at' => null,
        ]);

        return $id;
    }
}
