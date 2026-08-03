<?php

namespace App\Http\Controllers;

use App\Http\Requests\StartRefetchRequest;
use App\Jobs\FetchProductWorkJob;
use App\Models\Option;
use App\Models\RefetchRun;
use App\Support\Refetch\RefetchService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\View\View;

class RefetchController extends Controller
{
    public function start(
        StartRefetchRequest $request,
        RefetchService $service,
    ): RedirectResponse {
        $productIds = $request->productIds();

        if ($productIds === []) {
            return redirect()
                ->route('options.index', ['tab' => 'refetch'])
                ->withInput()
                ->withErrors(['product_ids' => __('Select at least one work to refetch.')]);
        }

        try {
            $run = $service->createRun($productIds, $request->boolean('check_images'));
        } catch (LockTimeoutException) {
            return redirect()
                ->route('options.index', ['tab' => 'refetch'])
                ->withInput()
                ->withErrors([
                    'product_ids' => __('Refetch cannot start while another refetch action is in progress.'),
                ]);
        }

        $batch = Bus::batch(
            collect($productIds)
                ->map(fn(string $productId): FetchProductWorkJob => new FetchProductWorkJob(
                    $run->getKey(),
                    $productId,
                ))
                ->all()
        )
            ->name("Refetch works #{$run->getKey()}")
            ->dispatch();

        $run->forceFill(['batch_id' => $batch->id])->save();

        return redirect()->route('options.refetch.show', $run);
    }

    public function show(RefetchRun $run): View
    {
        return view('OptionsRefetch', [
            'run' => $run,
            'productFormModalEnabled' => Option::productFormModalEnabled(),
            'productFormModalCompletionAction' => Option::productFormModalCompletionAction(),
        ]);
    }

    public function cancel(
        RefetchRun $run,
        RefetchService $service,
    ): RedirectResponse {
        if (! $service->cancelRun($run)) {
            return redirect()
                ->route('options.refetch.show', $run)
                ->withErrors(['run' => __('Only running refetch runs can be cancelled.')]);
        }

        return redirect()->route('options.refetch.show', $run);
    }
}
