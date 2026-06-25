<div>
    <form wire:submit.prevent="save" class="option-form">
        <label class="option-toggle">
            <input type="checkbox" wire:model.live="searchHiddenDescriptions">
            <span>Search hidden descriptions</span>
            <i class="fa-solid fa-circle-question"
                title="When enabled, general Index search can match description text even when the Description column is hidden."></i>
        </label>

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
