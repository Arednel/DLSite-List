<?php

namespace App\Jobs;

use App\Models\RefetchWorkResult;
use App\Support\Refetch\RefetchService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class FetchProductWorkJob implements ShouldQueue
{
    public const TIMEOUT_SECONDS = 600;

    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = self::TIMEOUT_SECONDS;

    public bool $failOnTimeout = true;

    public function __construct(
        public int $runId,
        public string $productId,
    ) {}

    public function handle(RefetchService $service): void
    {
        $result = RefetchWorkResult::query()
            ->with('run')
            ->where('refetch_run_id', $this->runId)
            ->where('product_id', $this->productId)
            ->first();

        if (! $result || ! $result->isPending()) {
            return;
        }

        if ($this->batch()?->cancelled() || $result->run?->isCancelling()) {
            $service->recordFailedResult($result, RefetchService::CANCELLED_BEFORE_FETCH_MESSAGE);

            return;
        }

        $service->fetchAndRecordResult($result);
    }

    public function failed(?Throwable $exception): void
    {
        $result = RefetchWorkResult::query()
            ->where('refetch_run_id', $this->runId)
            ->where('product_id', $this->productId)
            ->first();

        if (! $result || ! $result->isPending()) {
            return;
        }

        app(RefetchService::class)->recordFailedResult($result, RefetchService::QUEUE_FAILURE_MESSAGE);
    }
}
