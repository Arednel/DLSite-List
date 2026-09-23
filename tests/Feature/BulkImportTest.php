<?php

namespace Tests\Feature;

use App\Enums\BulkImportItemStatus;
use App\Enums\BulkImportRunStatus;
use App\Enums\ProductField;
use App\Jobs\FinishBulkImportRunJob;
use App\Jobs\ImportBulkWorkJob;
use App\Models\BulkImportItem;
use App\Models\BulkImportRun;
use App\Models\Genre;
use App\Models\Product;
use App\Support\BulkImport\BulkImportService;
use App\Support\DLSite\DLSiteProductImporter;
use App\Support\DLSite\DLSiteProductImportInput;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class BulkImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_job_uses_fixed_timeout_and_shared_overlap_lock(): void
    {
        $job = new ImportBulkWorkJob(1);

        $this->assertSame(3500, ImportBulkWorkJob::TIMEOUT_SECONDS);
        $this->assertSame(ImportBulkWorkJob::TIMEOUT_SECONDS, $job->timeout);
        $this->assertTrue($job->failOnTimeout);
        $this->assertSame(1, $job->maxExceptions);

        $middleware = $job->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
        $this->assertSame('bulk-import-dlsite-work', $middleware[0]->key);
        $this->assertSame(5, $middleware[0]->releaseAfter);
        $this->assertSame(3600, $middleware[0]->expiresAfter);
        $this->assertTrue($middleware[0]->shareKey);
    }

    public function test_start_is_blocked_while_bulk_import_lifecycle_lock_is_held(): void
    {
        Bus::fake();
        $lock = Cache::lock(BulkImportRun::LIFECYCLE_LOCK, BulkImportRun::LIFECYCLE_LOCK_SECONDS);

        $this->assertTrue($lock->get());

        try {
            try {
                app(BulkImportService::class)->start(
                    ['RJ000000099'],
                    new DLSiteProductImportInput(
                        values: [],
                        visibleFields: [ProductField::RjCode->value],
                        submitted: [],
                        autoSeriesFromTitleName: true,
                    ),
                );
                $this->fail('Expected the shared Bulk Import lifecycle lock to block run creation.');
            } catch (RuntimeException $exception) {
                $this->assertSame(BulkImportService::BUSY_MESSAGE, $exception->getMessage());
            }
        } finally {
            $lock->release();
        }

        $this->assertDatabaseCount('bulk_import_runs', 0);
        Bus::assertNothingDispatched();
    }

    public function test_bulk_import_page_reuses_quick_add_form_with_rj_list_input(): void
    {
        $this->get(route('products.create.bulk'))
            ->assertOk()
            ->assertSee('Bulk Import')
            ->assertSee('name="rj_list"', false)
            ->assertSee('fa-circle-question', false)
            ->assertSee('RJ Codes or Links')
            ->assertSee('Paste RJ codes or links. All occurrences of RJ followed by numbers are imported, e.g. RJ123456.')
            ->assertSee('Start Bulk Import')
            ->assertSee(route('products.create', [], false), false)
            ->assertSee(route('products.create.custom', [], false), false);
    }

    public function test_start_normalizes_deduplicates_snapshots_and_queues_rjs_in_order(): void
    {
        Bus::fake();

        $response = $this->post(route('products.store.bulk'), [
            'rj_list' => implode("\n", [
                'First: rj000000101',
                'https://www.dlsite.com/maniax/work/=/product_id/RJ000000102.html',
                'duplicate RJ000000101 and unrelated text',
                'Copied row [RJ000000103] title',
            ]),
            'progress' => 'Completed',
            'notes' => 'Shared bulk note',
        ]);

        $run = BulkImportRun::query()->firstOrFail();

        $response->assertRedirect(route('options.index', [
            'tab' => 'bulk-imports',
            'bulk_import_run' => $run->getKey(),
        ]));

        $this->assertSame(BulkImportRunStatus::Queued, $run->status);
        $this->assertSame(3, $run->total_count);
        $this->assertSame(
            ['RJ000000101', 'RJ000000102', 'RJ000000103'],
            $run->items()->orderBy('position')->pluck('product_id')->all(),
        );
        $this->assertSame('Completed', data_get($run->input_snapshot, 'values.progress'));
        $this->assertSame('Shared bulk note', data_get($run->input_snapshot, 'values.notes'));
        $this->assertTrue((bool) data_get($run->input_snapshot, 'submitted.progress'));
        $this->assertTrue((bool) data_get($run->input_snapshot, 'submitted.notes'));

        Bus::assertChained([
            ImportBulkWorkJob::class,
            ImportBulkWorkJob::class,
            ImportBulkWorkJob::class,
            FinishBulkImportRunJob::class,
        ]);
    }

    public function test_bulk_import_accepts_up_to_five_hundred_unique_rj_codes(): void
    {
        Bus::fake();

        $codes = collect(range(1, 500))
            ->map(fn(int $number): string => sprintf('RJ%09d', $number))
            ->implode("\n");

        $this->post(route('products.store.bulk'), [
            'rj_list' => $codes,
        ])->assertRedirect();

        $run = BulkImportRun::query()->firstOrFail();
        $this->assertSame(500, $run->total_count);
        $this->assertSame(500, $run->items()->count());
    }

    public function test_bulk_import_rejects_more_than_five_hundred_unique_rj_codes(): void
    {
        Bus::fake();

        $codes = collect(range(1, 501))
            ->map(fn(int $number): string => sprintf('RJ%09d', $number))
            ->implode("\n");

        $this->from(route('products.create.bulk'))
            ->post(route('products.store.bulk'), [
                'rj_list' => $codes,
            ])
            ->assertRedirect(route('products.create.bulk'))
            ->assertSessionHasErrors([
                'rj_codes' => 'You can import up to 500 RJ codes at once.',
            ]);

        $this->assertDatabaseCount('bulk_import_runs', 0);
        Bus::assertNothingDispatched();
    }

    public function test_text_without_any_rj_code_rejects_the_run_without_dispatching_jobs(): void
    {
        Bus::fake();

        $this->from(route('products.create.bulk'))
            ->post(route('products.store.bulk'), [
                'rj_list' => "DLsite links and copied text, but no work code here.",
            ])
            ->assertRedirect(route('products.create.bulk'))
            ->assertSessionHasErrors([
                'rj_list' => 'Could not find an RJ code (format: RJ + numbers) in your input.',
            ]);

        $this->assertDatabaseCount('bulk_import_runs', 0);
        Bus::assertNothingDispatched();
    }

    public function test_existing_product_is_skipped_without_fetching_dlsite(): void
    {
        app()->setLocale('ja');
        Process::fake()->preventStrayProcesses();
        $product = Product::factory()->create(['id' => 'RJ000000201']);
        [$run, $item] = $this->createRunItem($product->id);

        (new ImportBulkWorkJob($item->id))->handle(app(DLSiteProductImporter::class));

        $item->refresh();
        $run->refresh();

        $this->assertSame(BulkImportItemStatus::Skipped, $item->status);
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame('Already in library.', $item->error);
        $this->assertSame(1, $run->processed_count);
        $this->assertSame(1, $run->skipped_count);
        $this->assertSame(0, $run->failed_count);
        Process::assertNothingRan();
    }

    public function test_expected_dlsite_failure_is_recorded_without_failing_the_run(): void
    {
        app()->setLocale('ja');
        Storage::fake('local');
        Storage::fake('public');
        Process::fake([
            '*' => Process::result(errorOutput: 'GeoBlocked DLSite work', exitCode: 2),
        ])->preventStrayProcesses();
        [$run, $item] = $this->createRunItem('RJ000000301');

        (new ImportBulkWorkJob($item->id))->handle(app(DLSiteProductImporter::class));

        $item->refresh();
        $run->refresh();

        $this->assertSame(BulkImportItemStatus::Failed, $item->status);
        $this->assertSame('GeoBlocked DLSite work', $item->error);
        $this->assertSame(BulkImportRunStatus::Running, $run->status);
        $this->assertSame(1, $run->processed_count);
        $this->assertSame(1, $run->failed_count);
        $this->assertNull($run->error);
        Process::assertRanTimes(fn(): bool => true, 5);
    }

    public function test_unexpected_scraper_failure_fails_the_queue_run_instead_of_becoming_a_work_failure(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Process::fake([
            '*' => Process::result(errorOutput: 'Python environment failure', exitCode: 1),
        ])->preventStrayProcesses();
        [$run, $item] = $this->createRunItem('RJ000000302');
        $job = new ImportBulkWorkJob($item->id);

        try {
            $job->handle(app(DLSiteProductImporter::class));
            $this->fail('Expected the scraper infrastructure failure to escape the job.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Python environment failure', $exception->getMessage());
            $job->failed($exception);
        }

        $item->refresh();
        $run->refresh();

        $this->assertSame(BulkImportItemStatus::Failed, $item->status);
        $this->assertSame(BulkImportRunStatus::Failed, $run->status);
        $this->assertSame('Python environment failure', $run->error);
        Process::assertRanTimes(fn(): bool => true, 5);
    }

    public function test_database_failure_during_genre_sync_rolls_back_product_and_fails_the_run(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Process::fake([
            '*' => Process::result(output: '{"failed_images":[]}'),
        ])->preventStrayProcesses();

        $rjCode = 'RJ000000303';
        $payload = $this->scrapedWorkPayload($rjCode);
        $payload['japanese']['genre'] = ['ROLLBACK_TEST_GENRE'];
        Storage::disk('local')->put(
            "Works/{$rjCode}.json",
            json_encode($payload, JSON_THROW_ON_ERROR),
        );
        [$run, $item] = $this->createRunItem($rjCode);
        $job = new ImportBulkWorkJob($item->id);
        $event = 'eloquent.creating: ' . Genre::class;

        Event::listen($event, function (): void {
            DB::table('bulk_import_missing_table')->insert(['id' => 1]);
        });

        try {
            try {
                $job->handle(app(DLSiteProductImporter::class));
                $this->fail('Expected the forced database failure to escape the job.');
            } catch (QueryException $exception) {
                $job->failed($exception);
            }
        } finally {
            Event::forget($event);
        }

        $item->refresh();
        $run->refresh();

        $this->assertDatabaseMissing('products', ['id' => $rjCode]);
        $this->assertSame(BulkImportItemStatus::Failed, $item->status);
        $this->assertSame(BulkImportRunStatus::Failed, $run->status);
        $this->assertNotSame('Already in library.', $item->error);
    }

    public function test_completion_database_failure_rolls_back_product_and_fails_the_run(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Process::fake([
            '*' => Process::result(output: '{"failed_images":[]}'),
        ])->preventStrayProcesses();

        $rjCode = 'RJ000000306';
        Storage::disk('local')->put(
            "Works/{$rjCode}.json",
            json_encode($this->scrapedWorkPayload($rjCode), JSON_THROW_ON_ERROR),
        );
        [$run, $item] = $this->createRunItem($rjCode);
        $job = new ImportBulkWorkJob($item->id);
        $event = 'eloquent.updating: ' . BulkImportItem::class;

        Event::listen($event, function (BulkImportItem $updating): void {
            if ($updating->status === BulkImportItemStatus::Imported) {
                DB::table('bulk_import_missing_table')->insert(['id' => 1]);
            }
        });

        try {
            try {
                $job->handle(app(DLSiteProductImporter::class));
                $this->fail('Expected the completion write to fail.');
            } catch (QueryException $exception) {
                // With maxExceptions=1, the queue fails the job rather than retrying it.
                $job->failed($exception);
            }
        } finally {
            Event::forget($event);
        }

        $this->assertDatabaseMissing('products', ['id' => $rjCode]);
        $this->assertSame(BulkImportItemStatus::Failed, $item->fresh()->status);
        $this->assertSame(BulkImportRunStatus::Failed, $run->fresh()->status);
        $this->assertSame(1, $run->fresh()->processed_count);
        $this->assertSame(1, $run->fresh()->failed_count);
        $this->assertSame(0, $run->fresh()->imported_count);
    }

    public function test_worker_interruption_before_commit_rolls_back_product_and_can_be_retried(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Process::fake([
            '*' => Process::result(output: '{"failed_images":[]}'),
        ])->preventStrayProcesses();

        $rjCode = 'RJ000000307';
        Storage::disk('local')->put(
            "Works/{$rjCode}.json",
            json_encode($this->scrapedWorkPayload($rjCode), JSON_THROW_ON_ERROR),
        );
        [$run, $item] = $this->createRunItem($rjCode);
        $job = new ImportBulkWorkJob($item->id);
        $event = 'eloquent.updated: ' . BulkImportItem::class;

        Event::listen($event, function (BulkImportItem $updated): void {
            if ($updated->status === BulkImportItemStatus::Imported) {
                throw new RuntimeException('Simulated worker interruption before commit.');
            }
        });

        try {
            try {
                $job->handle(app(DLSiteProductImporter::class));
                $this->fail('Expected the simulated interruption.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Simulated worker interruption before commit.', $exception->getMessage());
                // A worker disappearing does not invoke the ordinary queue failure callback.
            }
        } finally {
            Event::forget($event);
        }

        $this->assertDatabaseMissing('products', ['id' => $rjCode]);
        $this->assertSame(BulkImportItemStatus::Importing, $item->fresh()->status);
        $this->assertSame(BulkImportRunStatus::Running, $run->fresh()->status);
        $this->assertSame(0, $run->fresh()->processed_count);

        $job->handle(app(DLSiteProductImporter::class));

        $this->assertDatabaseHas('products', ['id' => $rjCode]);
        $this->assertSame(BulkImportItemStatus::Imported, $item->fresh()->status);
        $this->assertSame(1, $run->fresh()->processed_count);
        $this->assertSame(1, $run->fresh()->imported_count);
        $this->assertSame(0, $run->fresh()->skipped_count);
    }

    public static function invalidSuccessfulCompletionStates(): array
    {
        return [
            'item already terminal' => ['item'],
            'run no longer active' => ['run'],
        ];
    }

    #[DataProvider('invalidSuccessfulCompletionStates')]
    public function test_successful_import_rolls_back_if_item_or_run_is_no_longer_active(string $changed): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Process::fake([
            '*' => Process::result(output: '{"failed_images":[]}'),
        ])->preventStrayProcesses();

        $rjCode = $changed === 'item' ? 'RJ000000308' : 'RJ000000309';
        Storage::disk('local')->put(
            "Works/{$rjCode}.json",
            json_encode($this->scrapedWorkPayload($rjCode), JSON_THROW_ON_ERROR),
        );
        [$run, $item] = $this->createRunItem($rjCode);
        $job = new ImportBulkWorkJob($item->id);
        $event = 'eloquent.created: ' . Product::class;

        Event::listen($event, function () use ($changed, $run, $item): void {
            // Simulate another path changing the state after the initial eligibility check.
            if ($changed === 'item') {
                DB::table('bulk_import_items')->where('id', $item->id)
                    ->update(['status' => BulkImportItemStatus::Failed->value]);
            } else {
                DB::table('bulk_import_runs')->where('id', $run->id)
                    ->update(['status' => BulkImportRunStatus::Failed->value]);
            }
        });

        try {
            try {
                $job->handle(app(DLSiteProductImporter::class));
                $this->fail('Expected stale successful completion to fail.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('Bulk Import item', $exception->getMessage());
            }
        } finally {
            Event::forget($event);
        }

        $this->assertDatabaseMissing('products', ['id' => $rjCode]);
        $this->assertSame(BulkImportItemStatus::Importing, $item->fresh()->status);
        $this->assertSame(BulkImportRunStatus::Running, $run->fresh()->status);
        $this->assertSame(0, $run->fresh()->processed_count);
    }

    public function test_timeout_failure_marks_the_item_and_run_failed(): void
    {
        [$run, $item] = $this->createRunItem('RJ000000305');
        $run->forceFill([
            'status' => BulkImportRunStatus::Running,
            'started_at' => now(),
        ])->save();
        $item->forceFill([
            'status' => BulkImportItemStatus::Importing,
            'started_at' => now(),
        ])->save();

        (new ImportBulkWorkJob($item->id))->failed(new TimeoutExceededException('Bulk import timed out.'));

        $item->refresh();
        $run->refresh();

        $this->assertSame(BulkImportItemStatus::Failed, $item->status);
        $this->assertSame('Bulk import timed out.', $item->error);
        $this->assertNotNull($item->completed_at);
        $this->assertSame(BulkImportRunStatus::Failed, $run->status);
        $this->assertSame('Bulk import timed out.', $run->error);
        $this->assertNotNull($run->failed_at);
        $this->assertSame(1, $run->processed_count);
        $this->assertSame(1, $run->failed_count);
    }

    public function test_finalizer_failure_marks_an_active_run_failed(): void
    {
        [$run] = $this->createRunItem('RJ000000304');
        $run->forceFill([
            'status' => BulkImportRunStatus::Running,
        ])->save();

        (new FinishBulkImportRunJob($run->id))->failed(new RuntimeException('Finalizer failed.'));

        $run->refresh();

        $this->assertSame(BulkImportRunStatus::Failed, $run->status);
        $this->assertSame('Finalizer failed.', $run->error);
        $this->assertNotNull($run->failed_at);
    }

    public function test_successful_job_applies_shared_fields_and_finish_marks_run_completed(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Process::fake([
            '*' => Process::result(output: '{"failed_images":[]}'),
        ])->preventStrayProcesses();

        $rjCode = 'RJ000000401';
        Storage::disk('local')->put(
            "Works/{$rjCode}.json",
            json_encode($this->scrapedWorkPayload($rjCode), JSON_THROW_ON_ERROR),
        );

        $input = new DLSiteProductImportInput(
            values: [
                'progress' => 'Completed',
                'notes' => 'Shared bulk note',
            ],
            visibleFields: [
                ProductField::RjCode->value,
                ProductField::Progress->value,
                ProductField::Notes->value,
            ],
            submitted: [
                'progress' => true,
                'notes' => true,
            ],
            autoSeriesFromTitleName: true,
        );
        [$run, $item] = $this->createRunItem($rjCode, $input);

        (new ImportBulkWorkJob($item->id))->handle(app(DLSiteProductImporter::class));
        (new FinishBulkImportRunJob($run->id))->handle();

        $product = Product::query()->findOrFail($rjCode);
        $item->refresh();
        $run->refresh();

        $this->assertSame('Completed', $product->progress);
        $this->assertSame('Shared bulk note', $product->notes);
        $this->assertSame('SCRAPED_JP_TITLE_TOKEN', $product->work_name);
        $this->assertSame(BulkImportItemStatus::Imported, $item->status);
        $this->assertSame(BulkImportRunStatus::Completed, $run->status);
        $this->assertSame(1, $run->processed_count);
        $this->assertSame(1, $run->imported_count);
        $this->assertNotNull($run->completed_at);
    }

    public function test_image_warning_is_stored_as_formatted_text(): void
    {
        app()->setLocale('ja');
        Storage::fake('local');
        Storage::fake('public');
        Process::fake([
            '*' => Process::result(output: '{"failed_images":["cover.jpg","sample_1.jpg"]}'),
        ])->preventStrayProcesses();
        $rjCode = 'RJ000000402';
        Storage::disk('local')->put(
            "Works/{$rjCode}.json",
            json_encode($this->scrapedWorkPayload($rjCode), JSON_THROW_ON_ERROR),
        );
        [, $item] = $this->createRunItem($rjCode);

        (new ImportBulkWorkJob($item->id))->handle(app(DLSiteProductImporter::class));

        $item->refresh();
        $this->assertSame(BulkImportItemStatus::Imported, $item->status);
        $this->assertSame(
            __('DLSite data was fetched, but these images could not be downloaded: :images', [
                'images' => 'cover.jpg, sample_1.jpg',
            ]),
            $item->warning,
        );
    }

    /** @return array{0: BulkImportRun, 1: \App\Models\BulkImportItem} */
    private function createRunItem(
        string $rjCode,
        ?DLSiteProductImportInput $input = null,
    ): array {
        $input ??= new DLSiteProductImportInput(
            values: [],
            visibleFields: [ProductField::RjCode->value],
            submitted: [],
            autoSeriesFromTitleName: true,
        );

        $run = BulkImportRun::create([
            'status' => BulkImportRunStatus::Queued,
            'input_snapshot' => $input->toSnapshot(),
            'total_count' => 1,
        ]);
        $item = $run->items()->create([
            'position' => 1,
            'product_id' => $rjCode,
            'status' => BulkImportItemStatus::Pending,
        ]);

        return [$run, $item];
    }

    private function scrapedWorkPayload(string $workId): array
    {
        return [
            'japanese' => [
                'product_id' => $workId,
                'maker_id' => 'RG12345',
                'work_name' => 'SCRAPED_JP_TITLE_TOKEN',
                'age_category' => ['_name_' => 'R18'],
                'circle' => 'SCRAPED_CIRCLE_TOKEN',
                'sample_images' => [],
                'genre' => [],
                'scenario' => [],
                'voice_actor' => [],
                'illustration' => [],
                'author' => [],
                'description' => 'SCRAPED_JP_DESCRIPTION_TOKEN',
            ],
            'english' => [
                'work_name' => 'SCRAPED_EN_TITLE_TOKEN',
                'genre' => [],
                'description' => 'SCRAPED_EN_DESCRIPTION_TOKEN',
            ],
        ];
    }
}
