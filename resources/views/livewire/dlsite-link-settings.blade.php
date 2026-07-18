<div>
    <form wire:submit.prevent="save" class="option-form">
        <x-options.switch wire:model.live="enabled" :help="__(
            'When enabled, All Ages works open on DLSite Home; R15 and R18 use Maniax. When disabled, all works use Maniax.',
        )">
            {{ __('Use age-appropriate DLSite work links') }}
        </x-options.switch>

        @error('enabled')
            <div class="text-error">{{ $message }}</div>
        @enderror

        <div class="option-actions option-actions--inline">
            <button type="submit"
                class="tag tag--soft tag--lg is-clickable">{{ __('Save DLSite link setting') }}</button>
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
            'modalId' => 'dlsite-link-reset-modal',
            'message' => 'Reset the DLSite link setting to its default?',
            'confirmLabel' => 'Reset to default',
            'confirmAction' => 'resetToDefault',
            'cancelAction' => 'cancelResetToDefault',
        ])
    </form>
</div>
