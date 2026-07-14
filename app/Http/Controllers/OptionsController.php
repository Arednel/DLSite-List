<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApplyTagRefetchRequest;
use App\Http\Requests\StartTagRefetchRequest;
use App\Jobs\FetchProductTagsJob;
use App\Models\Genre;
use App\Models\Option;
use App\Models\TagRefetchRun;
use App\Models\TagRefetchWorkResult;
use App\Support\TagColor;
use App\Support\TagRefetch\TagRefetchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\View\View;

class OptionsController extends Controller
{
    private const REFETCH_TAG_FIELDS = [
        'fetched_japanese_tags',
        'fetched_english_tags',
        'added_japanese_tags',
        'added_english_tags',
        'stale_japanese_tags',
        'stale_english_tags',
        'custom_to_fetched_japanese_tags',
        'custom_to_fetched_english_tags',
    ];

    public function index(): View
    {
        return view('Options', [
            'latestRefetchRun' => TagRefetchRun::query()
                ->latest('id')
                ->first(['id']),
            ...$this->productFormModalSettings(),
        ]);
    }

    public function startRefetchTags(StartTagRefetchRequest $request, TagRefetchService $service): RedirectResponse
    {
        $productIds = $request->productIds();
        $run = $service->createRun($productIds);

        $batch = Bus::batch(
            collect($productIds)
                ->map(fn(string $productId): FetchProductTagsJob => new FetchProductTagsJob($run->getKey(), $productId))
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
        $tagColors = $this->refetchTagColors($run);

        return view('OptionsRefetchTags', [
            'run' => $run,
            'summary' => $run->summary(),
            'canApply' => $run->canBeApplied(),
            'tagRows' => $this->refetchTagRows($run, $tagColors),
            'moveAction' => TagRefetchWorkResult::STALE_ACTION_MOVE_TO_CUSTOM,
            'removeAction' => TagRefetchWorkResult::STALE_ACTION_REMOVE,
            'addAction' => TagRefetchWorkResult::ADDED_ACTION_ADD,
            'ignoreAction' => TagRefetchWorkResult::ADDED_ACTION_IGNORE,
            'promoteCustomAction' => TagRefetchWorkResult::CUSTOM_TO_FETCHED_ACTION_PROMOTE,
            'keepCustomAction' => TagRefetchWorkResult::CUSTOM_TO_FETCHED_ACTION_KEEP_CUSTOM,
            ...$this->productFormModalSettings(),
        ]);
    }

    /**
     * @return array{productFormModalEnabled: bool, productFormModalCompletionAction: string}
     */
    private function productFormModalSettings(): array
    {
        return [
            'productFormModalEnabled' => Option::productFormModalEnabled(),
            'productFormModalCompletionAction' => Option::productFormModalCompletionAction(),
        ];
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
            'cancelled_at' => $run->cancelled_at?->toIso8601String(),
            'complete' => $run->hasReviewResults(),
            'review_url' => route('options.refetch-tags.show', $run, false),
        ]);
    }

    public function cancelRefetchTags(TagRefetchRun $run, TagRefetchService $service): RedirectResponse
    {
        if (! $service->cancelRun($run)) {
            return redirect()
                ->route('options.refetch-tags.show', $run)
                ->withErrors(['run' => 'Only running refetch runs can be cancelled.']);
        }

        return redirect()->route('options.refetch-tags.show', $run);
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
            $request->globalAddedJapaneseAction(),
            $request->globalAddedEnglishAction(),
            $request->globalCustomToFetchedAction(),
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

    private function refetchTagColors(TagRefetchRun $run): array
    {
        if (! Option::tagColorSurfaceEnabled(Option::TAG_COLOR_SURFACE_REFETCH)) {
            return [];
        }

        $titleKeys = $run->results
            ->flatMap(fn(TagRefetchWorkResult $result): array => collect(self::REFETCH_TAG_FIELDS)
                ->flatMap(fn(string $field): array => $result->{$field} ?? [])
                ->all())
            ->map(fn(string $title): string => Genre::titleKey($title))
            ->unique()
            ->values();

        return TagColor::effectiveColorPairsForTitleKeys($titleKeys)->all();
    }

    private function refetchTagRows(TagRefetchRun $run, array $tagColors): array
    {
        return $run->results
            ->mapWithKeys(function (TagRefetchWorkResult $result) use ($tagColors): array {
                $fields = collect(self::REFETCH_TAG_FIELDS)
                    ->mapWithKeys(fn(string $field): array => [
                        $field => collect($result->{$field} ?? [])
                            ->map(function (string $tag) use ($tagColors): array {
                                $colors = $tagColors[Genre::titleKey($tag)] ?? TagColor::pair(null, null);

                                return [
                                    'title' => $tag,
                                    ...TagColor::viewData($colors['color'], $colors['text_color']),
                                ];
                            })
                            ->all(),
                    ])
                    ->all();

                return [$result->getKey() => $fields];
            })
            ->all();
    }
}
