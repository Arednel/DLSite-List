<div>
    <form wire:submit.prevent="save" class="option-form">
        <x-options.switch wire:model.live="enabled" :help="__(
            'When enabled, All Ages works open on DLSite Home; R15 and R18 use Maniax. When disabled, all works use Maniax.',
        )">
            {{ __('Use age-appropriate DLSite links') }}
        </x-options.switch>

        @error('enabled')
            <div class="text-error">{{ $message }}</div>
        @enderror

        <x-options.switch wire:model.live="announceEnabled" :help="__(
            'When enabled, works that have an announcement date use DLSite announcement links (announce/). Released works will automatically redirect to their regular product pages (work/). When disabled, all links use regular product pages, which may show an error for not yet released works.',
        )">
            {{ __('Use announcement links when available') }}
        </x-options.switch>

        @error('announceEnabled')
            <div class="text-error">{{ $message }}</div>
        @enderror

        <div class="option-actions option-actions--inline">
            <button type="submit"
                class="tag tag--soft tag--lg is-clickable">{{ __('Save DLsite link settings') }}</button>
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
            'modalId' => 'dlsite-link-reset-modal',
            'message' => 'Reset the DLsite link settings to their defaults?',
            'confirmLabel' => 'Reset to default',
            'confirmAction' => 'resetToDefault',
            'cancelAction' => 'cancelResetToDefault',
        ])
    </form>
</div>
