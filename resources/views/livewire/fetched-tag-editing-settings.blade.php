<form wire:submit.prevent="save" class="stack">
    <label class="option-check">
        <input type="checkbox" wire:model.change.live="enabled">
        <span>Enable editing fetched English tags</span>
    </label>

    @error('enabled')
        <div class="notice notice--error">{{ $message }}</div>
    @enderror

    @if ($saved)
        <div class="notice" wire:dirty.remove wire:target="enabled">Fetched tag editing setting saved.</div>
    @endif

    <div class="option-actions">
        <button type="submit" class="tag tag--soft tag--lg is-clickable">
            Save tag editing
        </button>
    </div>
</form>
