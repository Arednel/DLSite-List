<div>
    <form wire:submit.prevent="save" class="option-form">
        <div class="option-radio-grid">
            @foreach ($themeOptions as $value => $label)
                <label class="option-radio">
                    <input type="radio" wire:model.live="theme" value="{{ $value }}">
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>

        @error('theme')
            <div class="text-error">{{ $message }}</div>
        @enderror

        <div class="option-actions option-actions--inline">
            <button type="submit" class="tag tag--soft tag--lg is-clickable">Save form theme</button>
            @if ($saved)
                <span class="saved-notice">{{ $notice }}</span>
            @endif
            <button type="button" class="tag tag--soft tag--lg is-clickable option-reset-button"
                wire:click="askResetToDefault">
                Reset to default
            </button>
        </div>

        @include('livewire.partials.options-reset-confirmation-modal', [
            'open' => $confirmingResetToDefault,
            'modalId' => 'product-form-theme-reset-modal',
            'message' => 'Reset the Add/Edit form theme to its default?',
            'confirmLabel' => 'Reset to default',
            'confirmAction' => 'resetToDefault',
            'cancelAction' => 'cancelResetToDefault',
        ])
    </form>
</div>
