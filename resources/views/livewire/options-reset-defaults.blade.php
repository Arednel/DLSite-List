<div class="option-global-reset">
    <div class="option-actions option-actions--inline">
        <button type="button" class="tag tag--soft tag--lg is-clickable option-reset-button"
            wire:click="askResetToDefault">
            Reset All Options
        </button>

        @if ($saved)
            <span class="saved-notice">All Options settings reset to defaults.</span>
        @endif
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
