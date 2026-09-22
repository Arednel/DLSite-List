<div class="bulk-import-ui">
    <div class="refetch-cleanup">
        <div class="option-actions option-actions--primary">
            <button type="button" class="tag tag--soft tag--lg is-clickable refetch-cleanup-button"
                wire:click="askCleanup" wire:loading.attr="disabled"
                data-bulk-import-cleanup-unavailable="{{ $cleanupUnavailable ? 'true' : 'false' }}"
                @disabled($cleanupUnavailable)>
                {{ __('Clean up Bulk Import history') }}
            </button>
            <i class="fa-solid fa-circle-question" tabindex="0" aria-label="{{ __('About Bulk Import cleanup') }}"
                title="{{ __('Permanently deletes all Bulk Import history. Cleanup is unavailable while a Bulk Import is queued or running.') }}"></i>
        </div>

        @if ($cleanupNotice !== '')
            <p class="saved-notice">{{ __($cleanupNotice) }}</p>
        @endif

        @error('cleanup')
            <p class="text-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="bulk-import-runs">
        @if ($runs->isEmpty())
            <p class="option-description">{{ __('No Bulk Imports have been started yet.') }}</p>
        @endif

        @foreach ($runs as $run)
            <livewire:bulk-import-run-card :run-id="$run->getKey()" :highlighted="$highlightedRunId === $run->getKey()" :key="'bulk-import-run-' . $run->getKey()" />
        @endforeach
    </div>

    @if ($runs->total() > 0)
        {{ $runs->links('livewire.index-pagination-links', ['scrollTo' => 'bulk-imports-tab-panel']) }}
    @endif

    @include('livewire.partials.options-reset-confirmation-modal', [
        'open' => $confirmingCleanup,
        'modalId' => 'bulk-import-history-cleanup-modal',
        'message' => 'Permanently delete all Bulk Import history?',
        'confirmLabel' => 'Clean up Bulk Import history',
        'confirmAction' => 'cleanup',
        'cancelAction' => 'cancelCleanup',
        'loadingMessage' => 'Cleaning up Bulk Import history...',
    ])
</div>
