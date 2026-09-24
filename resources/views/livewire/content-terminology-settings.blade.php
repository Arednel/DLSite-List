<form wire:submit.prevent="save" class="stack">
    <div>
        <label class="field-label" for="content-focus">{{ __('UI Wording') }}</label>
        <select id="content-focus" class="option-control option-control-select" wire:model.change.live="focus">
            @foreach ($focusOptions as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    @error('focus')
        <div class="notice notice--error">{{ $message }}</div>
    @enderror

    <div class="terminology-preview">
        <details class="terminology-review">
            <summary>
                {{ __('UI wording preview (:count)', ['count' => count($previewRows)]) }}
                <i class="fa-solid fa-circle-question"
                    title="{{ __('General UI wording is shown on the left, and wording for the selected profile on the right.') }}"></i>
            </summary>
            <div class="terminology-review__body">
                <ul class="terminology-review-list">
                    @foreach ($previewRows as $row)
                        <li>
                            <span class="term-before">{{ $row['general'] }}</span>
                            <span class="term-inline-arrow" aria-hidden="true">→</span>
                            <span class="term-after">{{ $row['selected'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </details>
    </div>

    <div class="option-actions">
        <button type="submit" class="tag tag--soft tag--lg is-clickable">
            {{ __('Save UI wording') }}
        </button>
        @session('content_terminology_notice')
            <span class="saved-notice">{{ __($value) }}</span>
        @endsession
        <button type="button" class="tag tag--soft tag--lg is-clickable option-reset-button"
            wire:click="askResetToDefault">
            {{ __('Reset to default') }}
        </button>
    </div>

    @include('livewire.partials.options-reset-confirmation-modal', [
        'open' => $confirmingResetToDefault,
        'modalId' => 'content-terminology-reset-modal',
        'message' => 'Reset UI wording to General?',
        'confirmLabel' => 'Reset to default',
        'confirmAction' => 'resetToDefault',
        'cancelAction' => 'cancelResetToDefault',
    ])
</form>
