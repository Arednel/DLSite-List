<form wire:submit.prevent="save" class="stack">
    <div>
        <label class="field-label" for="ui-language">{{ __('UI language') }}</label>
        <select id="ui-language" class="option-control option-control-select" wire:model.change.live="language">
            @foreach ($languageOptions as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <x-options.switch wire:model.live="followFetchedTagsLanguage" :help="__(
        'When enabled, Fetched Tags use the same language as the UI. Turn this off to choose the Fetched Tags language manually.',
    )">
        {{ __('Follow UI language automatically') }}
    </x-options.switch>

    <p class="option-description">
        {{ __('Fetched Tags support English, Japanese, and Traditional Chinese. Works added before Traditional Chinese tags were supported show English tags until they are refetched.') }}</p>

    <fieldset class="option-fieldset">
        <legend>{{ __('Fetched Tags language') }}</legend>

        @if ($followFetchedTagsLanguage)
            <select id="fetched-tag-language" class="option-control option-control-select"
                aria-label="{{ __('Fetched Tags language') }}" disabled>
                @foreach ($fetchedTagLanguageOptions as $value => $label)
                    <option value="{{ $value }}" @selected($followedFetchedTagLanguage === $value)>{{ $label }}</option>
                @endforeach
            </select>
        @else
            <select id="fetched-tag-language" class="option-control option-control-select"
                aria-label="{{ __('Fetched Tags language') }}" wire:model.change.live="fetchedTagLanguage">
                @foreach ($fetchedTagLanguageOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        @endif
    </fieldset>

    @error('language')
        <div class="notice notice--error">{{ $message }}</div>
    @enderror
    @error('followFetchedTagsLanguage')
        <div class="notice notice--error">{{ $message }}</div>
    @enderror
    @error('fetchedTagLanguage')
        <div class="notice notice--error">{{ $message }}</div>
    @enderror

    <div class="option-actions">
        <button type="submit" class="tag tag--soft tag--lg is-clickable">
            {{ __('Save language settings') }}
        </button>
        @session('language_settings_notice')
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
        'message' => 'Reset language settings to their defaults?',
        'confirmLabel' => 'Reset to default',
        'confirmAction' => 'resetToDefault',
        'cancelAction' => 'cancelResetToDefault',
    ])
</form>
