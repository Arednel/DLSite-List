<div>
    <form wire:submit.prevent="save" class="option-form">
        <x-options.switch wire:model.live="onHoldEnabled" :help="__('Disabling a status does not change works that already use it.')">
            {{ __('Enable On Hold status') }}
        </x-options.switch>

        <x-options.switch wire:model.live="droppedEnabled" :help="__('Disabling a status does not change works that already use it.')">
            {{ __('Enable Dropped status') }}
        </x-options.switch>

        <div class="option-actions option-actions--inline">
            <button type="submit" class="tag tag--soft tag--lg is-clickable">{{ __('Save optional statuses') }}</button>
            @if ($saved)
                <span class="saved-notice">{{ __($notice) }}</span>
            @endif
            <button type="button" class="tag tag--soft tag--lg is-clickable option-reset-button"
                wire:click="askResetToDefault">
                {{ __('Reset to default') }}
            </button>
        </div>

        @include('livewire.partials.options-reset-confirmation-modal', [
            'open' => $confirmingResetToDefault,
            'modalId' => 'optional-product-statuses-reset-modal',
            'message' => 'Reset optional statuses to their defaults?',
            'confirmLabel' => 'Reset to default',
            'confirmAction' => 'resetToDefault',
            'cancelAction' => 'cancelResetToDefault',
        ])
    </form>
</div>
