<form wire:submit.prevent="save" class="stack">
    <div>
        <label class="field-label" for="tag-autocomplete-order">
            Tag suggestions
            <i class="fa-solid fa-circle-question"
                title="Most used first orders all matching tags by attached work count. First word first shows tags that start with your text before later-word matches, then uses work count."></i>
        </label>
        <select id="tag-autocomplete-order" class="option-control option-control-select" wire:model.change.live="tagOrder">
            @foreach ($orderOptions as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="field-label" for="series-autocomplete-order">
            Series suggestions
            <i class="fa-solid fa-circle-question"
                title="Most used first orders all matching series by work count. First word first shows series that start with your text before later-word matches, then uses work count."></i>
        </label>
        <select id="series-autocomplete-order" class="option-control option-control-select"
            wire:model.change.live="seriesOrder">
            @foreach ($orderOptions as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    @error('tagOrder')
        <div class="notice notice--error">{{ $message }}</div>
    @enderror

    @error('seriesOrder')
        <div class="notice notice--error">{{ $message }}</div>
    @enderror

    @if ($saved)
        <div class="notice" wire:dirty.remove wire:target="tagOrder,seriesOrder">Autocomplete settings saved.</div>
    @endif

    <div class="option-actions">
        <button type="submit" class="tag tag--soft tag--lg is-clickable">
            Save autocomplete
        </button>
    </div>
</form>
