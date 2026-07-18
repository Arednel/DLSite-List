<form wire:submit.prevent="save" class="stack">
    <div>
        <label class="field-label" for="ui-language">{{ __('UI language') }}</label>
        <select id="ui-language" class="option-control option-control-select" wire:model.change.live="language">
            @foreach ($languageOptions as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    @error('language')
        <div class="notice notice--error">{{ $message }}</div>
    @enderror

    <div class="option-actions">
        <button type="submit" class="tag tag--soft tag--lg is-clickable">
            {{ __('Save UI language') }}
        </button>
        @session('ui_language_notice')
            <span class="saved-notice">{{ __($value) }}</span>
        @endsession
        <button type="button" class="tag tag--soft tag--lg is-clickable option-reset-button"
            wire:click="askResetToDefault">
            {{ __('Reset to default') }}
        </button>
    </div>

    @include('livewire.partials.options-reset-confirmation-modal', [
        'open' => $confirmingResetToDefault,
        'modalId' => 'ui-language-reset-modal',
        'message' => 'Reset the UI language to English?',
        'confirmLabel' => 'Reset to default',
        'confirmAction' => 'resetToDefault',
        'cancelAction' => 'cancelResetToDefault',
    ])
</form>
