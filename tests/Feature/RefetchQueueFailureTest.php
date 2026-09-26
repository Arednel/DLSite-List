<?php

namespace Tests\Feature;

use App\Jobs\FetchProductWorkJob;
use App\Models\Product;
use App\Models\RefetchRun;
use App\Models\RefetchWorkResult;
use App\Support\DLSite\DLSitePythonRunner;
use App\Support\Refetch\RefetchService;
use Illuminate\Bus\Events\BatchDispatched;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/** Regression coverage for the formerly orphaned Refetch results. */
class RefetchQueueFailureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['queue.default' => 'database', 'logging.default' => 'null']);
        $this->freezeTime();

        // Worker::runNextJob does not install the console command's failure logger.
        Event::listen(JobFailed::class, function (JobFailed $event): void {
            app('queue.failer')->log(
                $event->connectionName,
                $event->job->getQueue(),
                $event->job->getRawBody(),
                $event->exception,
            );
        });
    }

    public function test_refetch_job_has_a_finite_ten_minute_timeout_and_fails_on_timeout(): void
    {
        $job = new FetchProductWorkJob(1, 'RJ000001');

        $this->assertSame(FetchProductWorkJob::TIMEOUT_SECONDS, $job->timeout);
        $this->assertSame(600, $job->timeout);
        $this->assertTrue($job->failOnTimeout);
        $this->assertLessThan(config('queue.connections.database.retry_after'), $job->timeout);
    }

    #[DataProvider('runCancellationStates')]
    public function test_abandoned_reservation_settles_on_terminal_failure_before_handle(bool $cancelRun): void
    {
        $this->mock(DLSitePythonRunner::class)->shouldNotReceive('fetchWork');
        $run = $this->startRun();
        $result = $run->results()->sole();
        $reserved = Queue::connection('database')->pop();
        $this->assertSame(1, $reserved->attempts());
        $this->assertSame(7200, config('queue.connections.database.retry_after'));

        if ($cancelRun) {
            app(RefetchService::class)->cancelRun($run);
        }

        // Simulate a worker dying without deleting or releasing its reservation.
        $this->travel(7199)->seconds();
        $this->assertNull(Queue::connection('database')->pop());
        $this->travel(2)->seconds();
        $this->workOnce();

        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertStringContainsString(
            MaxAttemptsExceededException::class,
            DB::table('failed_jobs')->value('exception'),
        );
        $this->assertSame($reserved->uuid(), DB::table('failed_jobs')->value('uuid'));
        $this->assertSame($cancelRun, Bus::findBatch($run->batch_id)->cancelled());
        $this->assertTrue($result->refresh()->isFailed());
        $this->assertSame(RefetchService::QUEUE_FAILURE_MESSAGE, $result->error);
        $this->assertSame(
            $cancelRun ? RefetchRun::STATUS_REVIEW : RefetchRun::STATUS_APPLIED,
            $run->refresh()->status,
        );
        $this->assertSame(1, $run->processed_count);
        $this->assertSame(1, $run->failed_count);
        $this->assertNotNull($run->completed_at);
    }

    public function test_one_queue_failure_does_not_cancel_another_work_and_the_run_reaches_review(): void
    {
        $run = $this->startRun(2);
        [$first, $second] = $run->results()->orderBy('id')->get()->all();
        $jsonPath = Storage::disk('local')->path("Refetch/{$run->id}/Works/{$second->product_id}.json");
        $this->mock(DLSitePythonRunner::class)
            ->shouldReceive('fetchWork')->once()->with(
                $second->product_id,
                $jsonPath,
                null,
                RefetchService::FETCH_PROCESS_TIMEOUT_SECONDS,
            )
            ->andReturn(Process::result(output: '{"failed_images":[]}'));
        File::partialMock()->shouldReceive('isFile')->with($jsonPath)->andReturnTrue();
        File::shouldReceive('json')->with($jsonPath)->andReturn([
            'japanese' => ['product_id' => $second->product_id, 'work_name' => 'Refetched title'],
            'english' => [],
        ]);

        $this->expireFirstReservation();
        $this->workOnce();
        $this->workOnce();

        $this->assertFalse(Bus::findBatch($run->batch_id)->cancelled());
        $this->assertTrue($first->refresh()->isFailed());
        $this->assertTrue($second->refresh()->isFetched());
        $this->assertTrue($run->refresh()->isReview());
        $this->assertSame(2, $run->processed_count);
        $this->assertSame(1, $run->fetched_count);
        $this->assertSame(1, $run->failed_count);
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_ordinary_fetch_errors_still_record_the_original_message_without_queue_failure(): void
    {
        $this->mock(DLSitePythonRunner::class)
            ->shouldReceive('fetchWork')->once()->andThrow(new RuntimeException('Ordinary fetch error.'));
        $run = $this->startRun();

        $this->workOnce();

        $result = $run->results()->sole();
        $this->assertTrue($result->isFailed());
        $this->assertSame('Ordinary fetch error.', $result->error);
        $this->assertSame(1, $run->refresh()->processed_count);
        $this->assertFalse($run->isActive());
        $this->assertFalse(Bus::findBatch($run->batch_id)->cancelled());
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    #[DataProvider('terminalStatuses')]
    public function test_failure_callback_preserves_terminal_results(string $status): void
    {
        $run = $this->createRun();
        $result = $run->results()->sole();
        $pendingSnapshot = clone $result;
        $result->forceFill(['status' => $status, 'error' => 'Original diagnostic.'])->save();
        $before = $result->refresh()->getAttributes();
        $job = new FetchProductWorkJob($run->id, $result->product_id);

        $job->failed(new RuntimeException('Later queue failure.'));
        $job->failed(new RuntimeException('Repeated queue failure.'));
        app(RefetchService::class)->recordFailedResult($pendingSnapshot, 'Stale failure.');

        $this->assertSame($before, $result->refresh()->getAttributes());
    }

    public function test_failure_callback_is_idempotent_and_safe_after_history_cleanup(): void
    {
        $run = $this->createRun();
        app(RefetchService::class)->cancelRun($run);
        $result = $run->results()->sole();
        $job = new FetchProductWorkJob($run->id, $result->product_id);

        $job->failed(null);
        $resultBefore = $result->refresh()->getAttributes();
        $runBefore = $run->refresh()->getAttributes();
        $this->travel(1)->minute();
        $job->failed(new RuntimeException('Repeated failure with sensitive details.'));

        $this->assertSame($resultBefore, $result->refresh()->getAttributes());
        $this->assertSame($runBefore, $run->refresh()->getAttributes());
        $this->assertTrue($run->isReview());
        $this->assertSame(RefetchService::QUEUE_FAILURE_MESSAGE, $result->error);

        $run->delete();
        $job->failed(null);
        $this->assertDatabaseCount('refetch_work_results', 0);
    }

    #[DataProvider('runCancellationStates')]
    public function test_retrying_a_legacy_cancelled_batch_recovers_its_orphan_without_fetching(bool $cancelRun): void
    {
        $this->mock(DLSitePythonRunner::class)->shouldNotReceive('fetchWork');
        $run = $this->createRun(2);
        [$fetched, $pending] = $run->results()->orderBy('id')->get()->all();
        $fetched->forceFill([
            'status' => RefetchWorkResult::STATUS_FETCHED,
            'changes' => ['titles' => ['work_name' => ['old' => 'Old title', 'new' => 'New title']]],
        ])->save();
        $fetchedBefore = $fetched->refresh()->getAttributes();
        $batch = Bus::batch([new FetchProductWorkJob($run->id, $pending->product_id)])->dispatch();
        $run->forceFill(['batch_id' => $batch->id])->save();

        // Recreate the old failure bookkeeping without invoking the new callback.
        $reserved = Queue::connection('database')->pop();
        $exception = new RuntimeException('Legacy worker interruption.');
        app('queue.failer')->log('database', 'default', $reserved->getRawBody(), $exception);
        $batch->recordFailedJob($reserved->uuid(), $exception);
        $reserved->delete();
        if ($cancelRun) {
            app(RefetchService::class)->cancelRun($run);
        }

        $this->assertTrue($pending->refresh()->isPending());
        $this->artisan('queue:retry-batch', ['id' => [$run->batch_id]])->assertSuccessful();
        $this->assertTrue(Bus::findBatch($run->batch_id)->cancelled());
        $this->workOnce();

        $this->assertTrue($run->refresh()->isReview());
        $this->assertSame(2, $run->processed_count);
        $this->assertSame(1, $run->fetched_count);
        $this->assertSame(1, $run->failed_count);
        $this->assertSame(RefetchService::CANCELLED_BEFORE_FETCH_MESSAGE, $pending->refresh()->error);
        $this->assertSame($fetchedBefore, $fetched->refresh()->getAttributes());
        $this->assertDatabaseCount('failed_jobs', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    #[DataProvider('dispatchFailurePoints')]
    public function test_start_failure_rolls_back_run_results_batch_and_queue_jobs(string $point): void
    {
        $product = Product::factory()->create();
        if ($point === 'payload') {
            \Illuminate\Queue\Queue::createPayloadUsing(function (): array {
                throw new RuntimeException('Dispatch interrupted.');
            });
        } elseif ($point === 'batch_dispatched') {
            Event::listen(BatchDispatched::class, function (): void {
                $this->assertDatabaseCount('jobs', 1);
                throw new RuntimeException('Dispatch interrupted.');
            });
        } else {
            RefetchRun::updating(function (RefetchRun $run): void {
                if ($run->isDirty('batch_id')) {
                    $this->assertDatabaseCount('jobs', 1);
                    throw new RuntimeException('Dispatch interrupted.');
                }
            });
        }
        $this->withoutExceptionHandling();

        try {
            $this->post(route('options.refetch.start'), [
                'scope' => 'selected',
                'product_ids' => [$product->id],
            ]);
            $this->fail('Expected the dispatch exception to escape the controller.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Dispatch interrupted.', $exception->getMessage());
        } finally {
            \Illuminate\Queue\Queue::createPayloadUsing(null);
        }

        $this->assertDatabaseCount('refetch_runs', 0);
        $this->assertDatabaseCount('refetch_work_results', 0);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('job_batches', 0);
        $lock = Cache::lock(RefetchRun::LIFECYCLE_LOCK);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    public function test_start_keeps_the_lifecycle_lock_until_dispatch_and_run_link_are_complete(): void
    {
        Event::listen(BatchDispatched::class, function (): void {
            $lock = Cache::lock(RefetchRun::LIFECYCLE_LOCK);
            $this->assertFalse($lock->get());
        });

        $run = $this->startRun();
        $this->assertNotNull($run->batch_id);
        $this->assertTrue(Bus::findBatch($run->batch_id)->allowsFailures());
        $this->assertDatabaseCount('jobs', 1);
        $lock = Cache::lock(RefetchRun::LIFECYCLE_LOCK);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    public static function runCancellationStates(): array
    {
        return ['cancelling run' => [true], 'running run' => [false]];
    }

    public static function terminalStatuses(): array
    {
        return [
            'fetched' => [RefetchWorkResult::STATUS_FETCHED],
            'failed' => [RefetchWorkResult::STATUS_FAILED],
        ];
    }

    public static function dispatchFailurePoints(): array
    {
        return [
            'before queue insertion' => ['payload'],
            'after queue insertion' => ['batch_dispatched'],
            'while linking batch to run' => ['run_link'],
        ];
    }

    private function createRun(int $count = 1): RefetchRun
    {
        return app(RefetchService::class)->createRun(Product::factory()->count($count)->create()->modelKeys(), false);
    }

    private function startRun(int $count = 1): RefetchRun
    {
        return app(RefetchService::class)->startRun(Product::factory()->count($count)->create()->modelKeys(), false);
    }

    private function expireFirstReservation(): void
    {
        $this->assertNotNull(Queue::connection('database')->pop());
        $this->travel(config('queue.connections.database.retry_after') + 1)->seconds();
    }

    private function workOnce(): void
    {
        /** @var Worker $worker */
        $worker = app('queue.worker');
        $worker->runNextJob('database', 'default', new WorkerOptions(sleep: 0, maxTries: 1));
    }
}
