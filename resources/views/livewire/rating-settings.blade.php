<form wire:submit.prevent="save" class="stack">
    <div>
        <label class="field-label" for="rating-scale">
            {{ __('Rating Scale') }}
            <i class="fa-solid fa-circle-question" tabindex="0" aria-label="{{ __('About rating scale') }}"
                title="{{ __('Personal scores are always entered from 1 to 10. Example: personal score 9 is displayed as 4.5 on the 5-point scale. A DLsite rating of 4.97/5 is displayed as 4.97 when its 5-point value was calculated independently from fetched votes.') }}"></i>
        </label>
        <select id="rating-scale" class="option-control option-control-select" wire:model.change.live="scale">
            @foreach ($scaleOptions as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="field-label" for="rating-visual-style">
            {{ __('Rating Visual Style') }}
            <i class="fa-solid fa-circle-question" tabindex="0" aria-label="{{ __('About rating visual style') }}"
                title="{{ __('\'Simple\' shows the number only. \'DLsite\' adds stars display. \'Extended DLsite\' also shows the DLsite vote count.') }}"></i>
        </label>
        <select id="rating-visual-style" class="option-control option-control-select"
            wire:model.change.live="visualStyle">
            @foreach ($visualStyleOptions as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    @error('scale')
        <div class="notice notice--error">{{ $message }}</div>
    @enderror
    @error('visualStyle')
        <div class="notice notice--error">{{ $message }}</div>
    @enderror

    <div class="option-actions">
        <button type="submit" class="tag tag--soft tag--lg is-clickable">{{ __('Save rating settings') }}</button>
        @if ($saved)
            <span class="saved-notice">{{ __($notice) }}</span>
        @endif
        <button type="button" class="tag tag--soft tag--lg is-clickable option-reset-button"
            wire:click="askResetToDefault">{{ __('Reset to default') }}</button>
    </div>

    @include('livewire.partials.options-reset-confirmation-modal', [
        'open' => $confirmingResetToDefault,
        'modalId' => 'rating-settings-reset-modal',
        'message' => 'Reset rating settings to their defaults?',
        'confirmLabel' => 'Reset to default',
        'confirmAction' => 'resetToDefault',
        'cancelAction' => 'cancelResetToDefault',
    ])
</form>
