<div>
    <form wire:submit.prevent="save" class="option-form">
        <div class="option-radio-grid">
            @foreach ($widthOptions as $value => $label)
                <label class="option-radio">
                    <input type="radio" wire:model.live="mode" value="{{ $value }}">
                    <span>{{ __($label) }}</span>
                </label>
            @endforeach
        </div>

        @if ($mode === $customMode)
            <div class="option-field">
                <label for="index_table_width_custom">{{ __('Custom width') }}</label>
                <input id="index_table_width_custom" type="text" wire:model.live="custom"
                    placeholder="{{ __('Example: 1600px') }}">
                @error('custom')
                    <div class="text-error">{{ $message }}</div>
                @enderror
            </div>
        @endif

        <div class="option-actions option-actions--inline">
            <button type="submit" class="tag tag--soft tag--lg is-clickable">{{ __('Save table width') }}</button>
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
            'modalId' => 'index-table-width-reset-modal',
            'message' => 'Reset this setting to its default?',
            'confirmLabel' => 'Reset to default',
            'confirmAction' => 'resetToDefault',
            'cancelAction' => 'cancelResetToDefault',
        ])
    </form>
</div>
