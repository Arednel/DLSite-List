<div class="refetch-cleanup">
    <div class="option-actions option-actions--primary">
        @if ($latestRefetchRunId)
            <a class="tag tag--soft tag--lg is-clickable" href="{{ route('options.refetch.show', $latestRefetchRunId) }}">
                {{ __('Go to latest refetch') }}
            </a>
        @endif

        <button type="button" class="tag tag--soft tag--lg is-clickable refetch-cleanup-button" wire:click="askCleanup"
            wire:loading.attr="disabled" data-refetch-cleanup-unavailable="{{ $cleanupUnavailable ? 'true' : 'false' }}"
            @disabled($cleanupUnavailable)>
            {{ __('Clean up refetch data') }}
        </button>
        <i class="fa-solid fa-circle-question" tabindex="0" aria-label="{{ __('About refetch cleanup') }}"
            title="{{ __('Permanently deletes all refetch run records and all downloaded refetch images. Already applied info is not changed. Cleanup is unavailable while a refetch run is running or cancelling.') }}"></i>
    </div>

    @if ($notice !== '')
        <p class="saved-notice">{{ __($notice) }}</p>
    @endif

    @error('cleanup')
        <p class="text-error">{{ $message }}</p>
    @enderror

    @include('livewire.partials.options-reset-confirmation-modal', [
        'open' => $confirmingCleanup,
        'modalId' => 'refetch-cleanup-modal',
        'message' =>
            'Permanently delete all refetch run records and all downloaded refetch images? Already applied info is not changed.',
        'confirmLabel' => 'Clean up refetch data',
        'confirmAction' => 'cleanup',
        'cancelAction' => 'cancelCleanup',
    ])
</div>
