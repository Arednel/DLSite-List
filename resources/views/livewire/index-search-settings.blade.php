<div>
    <form wire:submit.prevent="save" class="option-form">
        <x-options.switch wire:model.live="searchHiddenDescriptions"
            help="When enabled, general Index search can match Japanese and English description text even when both description columns are hidden.">
            Search hidden descriptions
        </x-options.switch>

        <div class="option-actions option-actions--inline">
            <button type="submit" class="tag tag--soft tag--lg is-clickable">Save Index search setting</button>
            <button type="button" class="tag tag--soft tag--lg is-clickable option-reset-button"
                wire:click="askResetToDefault">
                Reset to default
            </button>
            @if ($saved)
                <span class="saved-notice">{{ $notice }}</span>
            @endif
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
