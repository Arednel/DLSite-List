<form wire:submit.prevent="save" class="option-form option-form--wide">
    <div>
        <textarea id="custom-css" class="option-control custom-css-input" wire:model="css" spellcheck="false"
            aria-labelledby="custom-css-heading"></textarea>
    </div>

    @error('css')
        <div class="notice notice--error">{{ $message }}</div>
    @enderror

    <div class="option-actions">
        <button type="submit" class="tag tag--soft tag--lg is-clickable">
            {{ __('Save custom CSS') }}
        </button>
        @session('custom_css_notice')
            <span class="saved-notice">{{ __($value) }}</span>
        @endsession
        <button type="button" class="tag tag--soft tag--lg is-clickable option-reset-button"
            wire:click="askResetToDefault">
            {{ __('Reset to default') }}
        </button>
    </div>

    @include('livewire.partials.options-reset-confirmation-modal', [
        'open' => $confirmingResetToDefault,
        'modalId' => 'custom-css-reset-modal',
        'message' => 'Reset Custom CSS to default?',
        'confirmLabel' => 'Reset to default',
        'confirmAction' => 'resetToDefault',
        'cancelAction' => 'cancelResetToDefault',
    ])
</form>
