<div class="option-global-reset">
    <div class="option-actions option-actions--inline">
        <button type="button" class="tag tag--soft tag--lg is-clickable option-reset-button"
            wire:click="askResetToDefault">
            {{ __('Reset All Options') }}
        </button>

        @session('options_reset_notice')
            <span class="saved-notice">{{ __($value) }}</span>
        @endsession
    </div>

    @include('livewire.partials.options-reset-confirmation-modal', [
        'open' => $confirmingResetToDefault,
        'modalId' => 'all-options-reset-modal',
        'message' => 'Reset all General and Field Layouts options to their defaults?',
        'confirmLabel' => 'Reset All Options',
        'confirmAction' => 'resetAll',
        'cancelAction' => 'cancelResetToDefault',
    ])
</div>
