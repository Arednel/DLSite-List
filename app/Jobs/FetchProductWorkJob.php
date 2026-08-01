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

class FetchProductWorkJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 0;

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
}
