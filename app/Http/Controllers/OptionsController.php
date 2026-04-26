<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApplyTagRefetchRequest;
use App\Http\Requests\StartTagRefetchRequest;
use App\Jobs\FetchProductTagsJob;
use App\Models\TagRefetchRun;
use App\Models\TagRefetchWorkResult;
use App\Support\TagRefetch\TagRefetchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\View\View;

class OptionsController extends Controller
{
    public function index(): View
    {
        return view('Options', [
            'latestRefetchRun' => TagRefetchRun::query()
                ->latest('id')
                ->first(['id']),
        ]);
    }

    public function startRefetchTags(StartTagRefetchRequest $request, TagRefetchService $service): RedirectResponse
    {
        $productIds = $request->productIds();
        $run = $service->createRun($productIds);

        $batch = Bus::batch(
            collect($productIds)
                ->map(fn (string $productId): FetchProductTagsJob => new FetchProductTagsJob($run->getKey(), $productId))
                ->all()
        )
            ->name("Refetch tags #{$run->getKey()}")
            ->dispatch();

        $run->forceFill(['batch_id' => $batch->id])->save();

        return redirect()->route('options.refetch-tags.show', $run);
    }

    public function showRefetchTags(TagRefetchRun $run): View
    {
        $run->load(['results.product']);

        return view('OptionsRefetchTags', [
            'run' => $run,
            'summary' => $run->summary(),
            'canApply' => $run->canBeApplied(),
            'moveAction' => TagRefetchWorkResult::STALE_ACTION_MOVE_TO_CUSTOM,
            'removeAction' => TagRefetchWorkResult::STALE_ACTION_REMOVE,
        ]);
    }

    public function refetchTagsStatus(TagRefetchRun $run): JsonResponse
    {
        $run->refresh();
        $failed = $this->failedJobCount($run);

        return response()->json([
            'status' => $run->status,
            'total' => $run->total_count,
            'processed' => $run->processed_count,
            'fetched' => $run->fetched_count,
            'skipped' => $run->skipped_count,
            'failed' => $failed,
            'complete' => $run->hasReviewResults(),
            'review_url' => route('options.refetch-tags.show', $run, false),
        ]);
    }

    public function applyRefetchTags(
        ApplyTagRefetchRequest $request,
        TagRefetchRun $run,
        TagRefetchService $service,
    ): RedirectResponse {
        $service->applyRun(
            $run,
            $request->globalJapaneseAction(),
            $request->globalEnglishAction(),
            $request->workActions(),
        );

        return redirect()->route('options.refetch-tags.show', $run);
    }

    private function failedJobCount(TagRefetchRun $run): int
    {
        if ($run->batch_id === null) {
            return 0;
        }

        return Bus::findBatch($run->batch_id)?->failedJobs ?? 0;
    }
}
