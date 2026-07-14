<div>
    <form wire:submit.prevent="save" class="option-form">
        <x-options.switch wire:model.live="enabled"
            help="When enabled, an ordinary left-click opens Quick Add or Edit Work in a modal. Middle-click and modified clicks continue to open the normal page.">
            Open Quick Add and Edit Work in modal windows
        </x-options.switch>

        <fieldset class="option-subsetting">
            <legend>After a successful Quick Add, Edit, or Delete</legend>
            <div class="option-radio-grid">
                @foreach ($completionOptions as $value => $label)
                    <label class="option-radio">
                        <input type="radio" wire:model.live="completionAction" value="{{ $value }}">
                        <span>{{ $label }}</span>
                        <i class="fa-solid fa-circle-question" title="{{ $completionHelp[$value] }}"></i>
                    </label>
                @endforeach
            </div>
        </fieldset>

        @error('enabled')
            <div class="text-error">{{ $message }}</div>
        @enderror
        @error('completionAction')
            <div class="text-error">{{ $message }}</div>
        @enderror

        <div class="option-actions option-actions--inline">
            <button type="submit" class="tag tag--soft tag--lg is-clickable">Save modal settings</button>
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
            'modalId' => 'product-form-modal-reset-modal',
            'message' => 'Reset the work form modal settings to their defaults?',
            'confirmLabel' => 'Reset to default',
            'confirmAction' => 'resetToDefault',
            'cancelAction' => 'cancelResetToDefault',
        ])
    </form>
</div>
