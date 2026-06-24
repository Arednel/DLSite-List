<div>
    <form wire:submit.prevent="save" class="option-form">
        <label class="option-toggle">
            <input type="checkbox" wire:model.live="expandedByDefault">
            <span>Open Tag Library with all tags shown</span>
            <i class="fa-solid fa-circle-question"
                title="When enabled, Tag Library opens with the All Tags list expanded instead of collapsed."></i>
        </label>

        <label class="option-toggle">
            <input type="checkbox" wire:model.live="indexGroupOrderingEnabled">
            <span>Enable group ordering on Index</span>
            <i class="fa-solid fa-circle-question"
                title="When enabled, Index tag chips use saved group and membership order instead of plain tag order and title."></i>
        </label>

        <div class="option-actions option-actions--inline">
            <button type="submit" class="tag tag--soft tag--lg is-clickable">Save Tag Library settings</button>
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
            'modalId' => 'tag-library-display-reset-modal',
            'message' => 'Reset these Tag Library settings to their defaults?',
            'confirmLabel' => 'Reset to default',
            'confirmAction' => 'resetToDefault',
            'cancelAction' => 'cancelResetToDefault',
        ])
    </form>
</div>
