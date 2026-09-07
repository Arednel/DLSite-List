<div>
    <form wire:submit="save" class="option-form">
        <p class="helper-text">
            {{ __('Accepted units: px, rem, em, %, vw, vh, vmin, vmax, svh, lvh, and dvh.') }}
        </p>

        @foreach ($overflowTargets as $target => $label)
            <fieldset class="option-fieldset">
                <legend>{{ __($label) }}</legend>

                <x-options.switch wire:model.live="overflow.{{ $target }}.enabled">
                    {{ __('Limit :field height', ['field' => __($label)]) }}
                </x-options.switch>

                @if ($overflow[$target]['enabled'])
                    <div class="option-field">
                        <label for="{{ $target }}_overflow_height">{{ __('Maximum height') }}</label>
                        <input id="{{ $target }}_overflow_height" type="text"
                            wire:model.blur="overflow.{{ $target }}.height">
                        @error('overflow.' . $target . '.height')
                            <div class="text-error">{{ $message }}</div>
                        @enderror
                    </div>
                @endif
            </fieldset>
        @endforeach

        <div class="option-actions option-actions--inline">
            <button type="submit"
                class="tag tag--soft tag--lg is-clickable">{{ __('Save overflow settings') }}</button>
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
            'modalId' => 'index-content-overflow-reset-modal',
            'message' => 'Reset these overflow settings to their defaults?',
            'confirmLabel' => 'Reset to default',
            'confirmAction' => 'resetToDefault',
            'cancelAction' => 'cancelResetToDefault',
        ])
    </form>
</div>
