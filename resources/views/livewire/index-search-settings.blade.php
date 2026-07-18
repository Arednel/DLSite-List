<div>
    <form wire:submit.prevent="save" class="option-form">
        <x-options.switch wire:model.live="searchHiddenDescriptions" :help="__(
            'When enabled, general Index search can match Japanese and English description text even when both description columns are hidden.',
        )">
            {{ __('Search hidden descriptions') }}
        </x-options.switch>

        <div class="option-actions option-actions--inline">
            <button type="submit"
                class="tag tag--soft tag--lg is-clickable">{{ __('Save Index search setting') }}</button>
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
            'modalId' => 'index-search-reset-modal',
            'message' => 'Reset this Index search setting to its default?',
            'confirmLabel' => 'Reset to default',
            'confirmAction' => 'resetToDefault',
            'cancelAction' => 'cancelResetToDefault',
        ])
    </form>
</div>
