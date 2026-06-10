<div>
    <form wire:submit.prevent="save" class="option-form">
        <label class="option-toggle">
            <input type="checkbox" wire:model.live="expandedByDefault">
            <span>Open Tag Library with all tags shown</span>
        </label>

        <div class="option-actions option-actions--inline">
            <button type="submit" class="tag tag--soft tag--lg is-clickable">Save Tag Library display</button>
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
            'message' => 'Reset this setting to its default?',
            'confirmLabel' => 'Reset to default',
            'confirmAction' => 'resetToDefault',
            'cancelAction' => 'cancelResetToDefault',
        ])
    </form>
</div>
