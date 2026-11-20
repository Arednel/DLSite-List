<form wire:submit.prevent="save" class="stack">
    <div>
        <label class="field-label" for="ui-language">{{ __('UI Language') }}</label>
        <select id="ui-language" class="option-control option-control-select" wire:model.change.live="language">
            @foreach ($languageOptions as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <x-options.switch wire:model.live="followUiLanguage" :help="__(
        'When enabled, the supported DLsite metadata language matching the UI language is fetched automatically and selected as the preferred DLsite metadata language. If the UI language has no supported DLsite metadata language, English is used. Japanese is always fetched and stored.',
    )">
        {{ __('Follow UI language automatically') }}
    </x-options.switch>

    <fieldset class="metadata-language-block">
        <legend class="metadata-language-block__heading">{{ __('DLsite metadata languages') }}</legend>

        <div>
            <label class="field-label" for="metadata-language">{{ __('Preferred metadata language') }}</label>

            @if ($followUiLanguage)
                <select id="metadata-language" class="option-control option-control-select metadata-language-select"
                    aria-label="{{ __('Preferred metadata language') }}" disabled>
                    @foreach ($metadataLanguageOptions as $value => $label)
                        <option value="{{ $value }}" @selected($displayedMetadataLanguage === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            @else
                <select id="metadata-language" class="option-control option-control-select metadata-language-select"
                    aria-label="{{ __('Preferred metadata language') }}" wire:model.change.live="metadataLanguage">
                    @foreach ($metadataLanguageOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            @endif
        </div>

        <details class="field-layout-section field-layout-collapsible metadata-language-nested" wire:ignore.self>
            <summary class="field-layout-summary">
                <span class="field-layout-summary-main">
                    <span class="field-layout-summary-title">{{ __('Fetched languages') }}</span>
                    <span class="field-layout-summary-help">
                        {{ __('Fetched for Title, Description, and Tags.') }}
                    </span>
                </span>
                <span class="field-layout-summary-chevron" aria-hidden="true"></span>
            </summary>

            <div class="field-layout-collapsible-body">
                <x-options.switch :disabled="true" :checked="true">
                    {{ $alwaysFetchedLanguageLabel }}
                    <span class="field-layout-lock-note">{{ __('Always fetched') }}</span>
                </x-options.switch>

                @foreach ($optionalFetchLanguageOptions as $value => $label)
                    @if ($displayedMetadataLanguage === $value)
                        <x-options.switch wire:key="metadata-fetch-{{ $value }}-locked" :disabled="true"
                            :checked="true">
                            {{ $label }}
                            <span
                                class="field-layout-lock-note">{{ $followUiLanguage ? __('Follows UI language') : __('Preferred language') }}</span>
                        </x-options.switch>
                    @else
                        <x-options.switch wire:key="metadata-fetch-{{ $value }}-editable"
                            wire:model.live="fetchLanguages" value="{{ $value }}">
                            {{ $label }}
                        </x-options.switch>
                    @endif
                @endforeach
            </div>
        </details>
    </fieldset>

    @error('language')
        <div class="notice notice--error">{{ $message }}</div>
    @enderror
    @error('followUiLanguage')
        <div class="notice notice--error">{{ $message }}</div>
    @enderror
    @error('metadataLanguage')
        <div class="notice notice--error">{{ $message }}</div>
    @enderror
    @error('fetchLanguages')
        <div class="notice notice--error">{{ $message }}</div>
    @enderror
    @error('fetchLanguages.*')
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
