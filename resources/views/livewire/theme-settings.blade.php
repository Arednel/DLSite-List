<form wire:submit.prevent="save" class="option-form">
    <fieldset class="theme-settings-fieldset">
        <legend><span role="heading" aria-level="3">{{ __('Authentication Page Theme') }}</span></legend>

        <p class="option-description">
            {{ __('Choose the visual theme for authentication pages.') }}
        </p>

        <div class="option-radio-grid">
            @foreach ($authenticationThemeOptions as $value => $label)
                <label class="option-radio">
                    <input type="radio" wire:model.live="authenticationTheme" value="{{ $value }}">
                    <span>{{ __($label) }}</span>
                </label>
            @endforeach
        </div>

        @error('authenticationTheme')
            <div class="text-error">{{ $message }}</div>
        @enderror
    </fieldset>

    <fieldset class="theme-settings-fieldset">
        <legend><span role="heading" aria-level="3">{{ __('Form Page Theme') }}</span></legend>

        <p class="option-description">
            {{ __('Choose the visual theme for add and edit pages.') }}
        </p>

        <div class="option-radio-grid">
            @foreach ($formThemeOptions as $value => $label)
                <label class="option-radio">
                    <input type="radio" wire:model.live="formTheme" value="{{ $value }}">
                    <span>{{ __($label) }}</span>
                </label>
            @endforeach
        </div>

        @error('formTheme')
            <div class="text-error">{{ $message }}</div>
        @enderror
    </fieldset>

    <div class="option-actions option-actions--inline">
        <button type="submit" class="tag tag--soft tag--lg is-clickable">
            {{ __('Save theme') }}
        </button>
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
        'modalId' => 'theme-settings-reset-modal',
        'message' => 'Reset all theme settings to their defaults?',
        'confirmLabel' => 'Reset to default',
        'confirmAction' => 'resetToDefault',
        'cancelAction' => 'cancelResetToDefault',
    ])
</form>
