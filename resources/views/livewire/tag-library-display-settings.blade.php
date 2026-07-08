<div>
    <form wire:submit.prevent="save" class="option-form">
        <x-options.switch wire:model.live="expandedByDefault"
            help="When enabled, Tag Library opens with the All Tags list expanded instead of collapsed.">
            Open Tag Library with all tags shown
        </x-options.switch>

        <x-options.switch wire:model.live="indexGroupOrderingEnabled"
            help="When enabled, Index tag chips use saved group order, saved tag order inside groups, then ungrouped tags alphabetically.">
            Enable group ordering on Index
        </x-options.switch>

        <fieldset class="option-fieldset">
            <legend>
                Tag color surfaces
                <i class="fa-solid fa-circle-question" title="Choose where saved tag and tag group colors are shown."></i>
            </legend>

            @foreach ($colorSurfaceLabels as $surface => $label)
                <x-options.switch wire:model.live="colorSurfaces.{{ $surface }}">
                    {{ $label }}
                </x-options.switch>
            @endforeach
        </fieldset>

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
