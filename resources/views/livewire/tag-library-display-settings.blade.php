<div>
    <form wire:submit.prevent="save" class="option-form">
        <x-options.switch wire:model.live="expandedByDefault" :help="__('When enabled, Tag Library opens with the All Tags list expanded instead of collapsed.')">
            {{ __('Open Tag Library with all tags shown') }}
        </x-options.switch>

        <x-options.switch wire:model.live="indexGroupOrderingEnabled" :help="__(
            'When enabled, Index tag chips use saved group order, saved tag order inside groups, then ungrouped tags alphabetically.',
        )">
            {{ __('Enable group ordering on Index') }}
        </x-options.switch>

        <fieldset class="option-fieldset">
            <legend>
                {{ __('Tag color surfaces') }}
                <i class="fa-solid fa-circle-question"
                    title="{{ __('Choose where saved tag and tag group colors are shown.') }}"></i>
            </legend>

            @foreach ($colorSurfaceLabels as $surface => $label)
                <x-options.switch wire:model.live="colorSurfaces.{{ $surface }}">
                    {{ $label }}
                </x-options.switch>
            @endforeach
        </fieldset>

        <div class="option-actions option-actions--inline">
            <button type="submit"
                class="tag tag--soft tag--lg is-clickable">{{ __('Save Tag Library settings') }}</button>
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
            'modalId' => 'tag-library-display-reset-modal',
            'message' => 'Reset these Tag Library settings to their defaults?',
            'confirmLabel' => 'Reset to default',
            'confirmAction' => 'resetToDefault',
            'cancelAction' => 'cancelResetToDefault',
        ])
    </form>
</div>
