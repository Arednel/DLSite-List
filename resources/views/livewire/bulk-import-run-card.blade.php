<div class="bulk-import-run-group" wire:key="bulk-import-run-{{ $run->getKey() }}"
    @if ($active) wire:poll.visible.2s="refreshRun" @endif>
    <article class="bulk-import-run result-card"
        @if ($highlighted) id="bulk-import-run-{{ $run->getKey() }}" @endif>
        <div class="bulk-import-run__header">
            <div>
                <strong>{{ __('Bulk Import #:id', ['id' => $run->getKey()]) }}</strong>
                <span class="result-status">{{ $run->status->label() }}</span>
            </div>
            <span>{{ $run->processed_count }} / {{ $run->total_count }}</span>
        </div>

        @if ($run->isActive())
            <div class="progress-summary">
                @if ($run->currentItem !== null)
                    <strong>{{ __('Importing :rj - :position / :total', [
                        'rj' => $run->currentItem->product_id,
                        'position' => $run->currentItem->position,
                        'total' => $run->total_count,
                    ]) }}</strong>
                @else
                    <strong>{{ __('Queued - :processed / :total processed', [
                        'processed' => $run->processed_count,
                        'total' => $run->total_count,
                    ]) }}</strong>
                @endif
            </div>
            <div class="progress-track" aria-hidden="true">
                <div class="progress-fill" style="width: {{ $run->progressPercent() }}%"></div>
            </div>
        @endif

        <div class="summary-grid bulk-import-summary">
            <div>{{ __('Imported') }}<strong>{{ $run->imported_count }}</strong></div>
            <div>{{ __('Skipped') }}<strong>{{ $run->skipped_count }}</strong></div>
            <div>{{ __('Failed') }}<strong>{{ $run->failed_count }}</strong></div>
            <div>{{ __('Remaining') }}<strong>{{ $run->remainingCount() }}</strong></div>
        </div>

        @if ($issueCount > 0)
            <details class="review-errors">
                <summary>{{ __('Errors') }} ({{ $issueCount }})</summary>
                <ul class="review-errors__list">
                    @if ($run->error)
                        <li wire:key="bulk-import-run-error-{{ $run->getKey() }}">{{ $run->displayError() }}</li>
                    @endif
                    @foreach ($issues as $item)
                        <li wire:key="bulk-import-issue-{{ $item->getKey() }}">
                            {{ $item->product_id }} - {{ $item->status->label() }}
                            @if ($item->error)
                                - {{ $item->displayError() }}
                            @endif
                            @if ($item->warning)
                                - {{ __('Warning: :message', ['message' => $item->warning]) }}
                            @endif
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif

        @if ($importedWorks->isNotEmpty())
            <details class="review-errors imported-works">
                <summary>{{ __('Imported works') }} ({{ $importedWorks->count() }})</summary>
                <ul class="review-errors__list">
                    @foreach ($importedWorks as $item)
                        <li wire:key="bulk-import-imported-{{ $item->getKey() }}">
                            {{ $item->product_id }}@if ($item->product?->work_name)
                                - {{ $item->product->work_name }}
                            @endif
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif
    </article>
</div>
