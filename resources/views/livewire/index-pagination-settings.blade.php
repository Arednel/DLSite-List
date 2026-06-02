<form wire:submit.prevent="save" class="stack">
    <div>
        <label class="field-label" for="index-per-page">Index page size</label>
        <select id="index-per-page" class="option-control option-control-select" wire:model.change.live="mode">
            @foreach ($fixedOptions as $value => $label)
                <option value="{{ $value }}">{{ $label }} works per page</option>
            @endforeach
            <option value="custom">Custom value</option>
            <option value="{{ $unlimitedValue }}">Unlimited</option>
        </select>
    </div>

    @if ($mode === 'custom')
        <label class="field-label" for="index-custom-per-page">Custom works per page</label>
        <input id="index-custom-per-page" class="option-control-input" type="number" min="1" step="1"
            wire:model="customValue">
        @error('customValue')
            <div class="text-error">{{ $message }}</div>
        @enderror
    @endif

    @error('mode')
        <div class="notice notice--error">{{ $message }}</div>
    @enderror

    @if ($saved)
        <div class="notice" wire:dirty.remove wire:target="mode,customValue">{{ $notice }}</div>
    @endif

    <div class="option-actions">
        <button type="submit" class="tag tag--soft tag--lg is-clickable">
            Save pagination
        </button>
        <button type="button" class="tag tag--soft tag--lg is-clickable option-reset-button"
            wire:click="askResetToDefault">
            Reset to default
        </button>
    </div>

    @include('livewire.partials.options-reset-confirmation-modal', [
        'open' => $confirmingResetToDefault,
        'modalId' => 'index-pagination-reset-modal',
        'message' => 'Reset this setting to its default?',
        'confirmLabel' => 'Reset to default',
        'confirmAction' => 'resetToDefault',
        'cancelAction' => 'cancelResetToDefault',
    ])
</form>
