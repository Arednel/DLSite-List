<div class="transfer-ui">
    @error('transfer')
        <p class="notice notice--error" role="alert">{{ $message }}</p>
    @enderror
    <div class="refetch-cleanup">
        <div class="option-actions option-actions--primary">
            @if ($latestExportRunId)
                <a class="tag tag--soft tag--lg is-clickable"
                    href="{{ route('options.transfers.show', $latestExportRunId) }}">
                    {{ __('Latest export') }}
                </a>
            @endif
            @if ($latestImportRunId)
                <a class="tag tag--soft tag--lg is-clickable"
                    href="{{ route('options.transfers.show', $latestImportRunId) }}">
                    {{ __('Latest import') }}
                </a>
            @endif
            <button type="button" class="tag tag--soft tag--lg is-clickable refetch-cleanup-button"
                wire:click="askCleanup" wire:loading.attr="disabled"
                data-transfer-cleanup-unavailable="{{ $cleanupUnavailable ? 'true' : 'false' }}"
                @disabled($cleanupUnavailable)>
                {{ __('Clean up transfer history') }}
            </button>
            <i class="fa-solid fa-circle-question" tabindex="0" aria-label="{{ __('About transfer cleanup') }}"
                title="{{ __('Permanently deletes all transfer history, archives, and staged files. Already applied library data is not changed. Cleanup is unavailable while an import or export is in progress.') }}"></i>
        </div>

        @if ($cleanupNotice !== '')
            <p class="saved-notice">{{ __($cleanupNotice) }}</p>
        @endif

        @error('cleanup')
            <p class="text-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="refetch-scope-grid">
        <section class="refetch-scope-card" aria-labelledby="transfer-export-heading">
            <header>
                <h3 id="transfer-export-heading">
                    <i class="fa-solid fa-file-export fa-fw" aria-hidden="true"></i>
                    {{ __('Export') }}
                </h3>
                <p>{{ __('Choose what to include and create downloadable ZIP files in the background.') }}</p>
                <p>{{ __('Starting a new export cancels any unfinished previous export.') }}</p>
            </header>

            <div class="stack">
                <label class="option-field">{{ __('Maximum size per ZIP') }}
                    <select class="option-control" wire:model.live="sizeChoice">
                        @foreach ($this::presets() as $size)
                            <option value="{{ $size }}">{{ $size }} MiB</option>
                        @endforeach
                        <option value="unlimited">{{ __('Unlimited') }}</option>
                        <option value="custom">{{ __('Custom') }}</option>
                    </select>
                </label>
                @if ($sizeChoice === 'custom')
                    <label class="option-field">{{ __('Whole MiB') }}
                        <input class="option-control" type="number" min="1" step="1"
                            wire:model="customMib">
                    </label>
                @endif
                @error('sizeChoice')
                    <p class="text-error">{{ $message }}</p>
                @enderror
                @error('customMib')
                    <p class="text-error">{{ $message }}</p>
                @enderror
                <div class="option-actions">
                    <button class="tag tag--soft is-clickable" type="button"
                        wire:click="saveSize">{{ __('Save Export size') }}</button>
                </div>
                @if ($sizeNotice !== '')
                    <p class="saved-notice">{{ __($sizeNotice) }}</p>
                @endif
            </div>

            <fieldset class="option-fieldset">
                <legend>{{ __('Include') }}</legend>
                <div class="stack">
                    <x-options.switch value="works" wire:model.live="scopes">{{ __('Works') }}</x-options.switch>
                    <x-options.switch value="images" wire:model.live="scopes" :disabled="!in_array('works', $scopes, true)">
                        {{ __('Images') }}
                    </x-options.switch>
                    <x-options.switch value="tag-library"
                        wire:model.live="scopes">{{ __('Tag Library') }}</x-options.switch>
                    <x-options.switch value="options" wire:model.live="scopes">{{ __('Options') }}</x-options.switch>
                </div>
                @error('scopes')
                    <p class="text-error">{{ $message }}</p>
                @enderror
            </fieldset>

            @if (in_array('works', $scopes, true))
                <label class="option-field">{{ __('Works') }}
                    <select class="option-control" wire:model.live="mode">
                        <option value="all">{{ __('All Works') }}</option>
                        <option value="selected">{{ __('Selected Works') }}</option>
                    </select>
                </label>

                @if ($mode === 'selected')
                    <div>
                        <label class="field-label" for="export-work-search">{{ __('Select works') }}</label>
                        <input id="export-work-search" class="option-control" type="search"
                            placeholder="{{ __('Search by RJ ID or title...') }}"
                            wire:model.live.debounce.250ms="search">
                    </div>

                    <div class="work-checklist">
                        @forelse ($products as $product)
                            <label class="work-checklist__item" wire:key="export-work-{{ $product->id }}">
                                <input type="checkbox" value="{{ $product->id }}" wire:model="selectedIds">
                                <span>
                                    <strong>{{ $product->id }} - </strong>
                                    {{ $product->work_name }}
                                    @if ($product->work_name_english)
                                        <span class="work-checklist__muted">{{ $product->work_name_english }}</span>
                                    @endif
                                </span>
                            </label>
                        @empty
                            <p class="empty-state">
                                {{ trim($search) === '' ? __('No works available for export.') : __('No works match this search.') }}
                            </p>
                        @endforelse
                    </div>
                    @error('ids')
                        <p class="text-error">{{ $message }}</p>
                    @enderror
                    @error('ids.*')
                        <p class="text-error">{{ $message }}</p>
                    @enderror
                @endif
            @endif

            @if (!in_array('works', $scopes, true) || $mode === 'all' || $hasAnyProducts)
                <div class="option-actions">
                    <button type="button" class="tag tag--gradient tag--lg is-clickable" wire:click="export"
                        wire:loading.attr="disabled">{{ __('Export') }}</button>
                </div>
            @endif
        </section>

        <section class="refetch-scope-card" aria-labelledby="transfer-import-heading">
            <header>
                <h3 id="transfer-import-heading">
                    <i class="fa-solid fa-file-import fa-fw" aria-hidden="true"></i>
                    {{ __('Import') }}
                </h3>
                <p>{{ __('Upload exported ZIP files and review every proposed change before applying it.') }}</p>
                <p>{{ __('Starting a new import cancels any unfinished previous import.') }}</p>
            </header>

            <x-transfer-upload :initial="true" :upload-max-bytes="$uploadMaxBytes ?? 0" />
        </section>
    </div>

    @include('livewire.partials.options-reset-confirmation-modal', [
        'open' => $confirmingCleanup,
        'modalId' => 'transfer-history-cleanup-modal',
        'message' =>
            'Permanently delete all transfer history, archives, and staged files? Already applied library data is not changed.',
        'confirmLabel' => 'Clean up transfer history',
        'confirmAction' => 'cleanup',
        'cancelAction' => 'cancelCleanup',
        'loadingMessage' => 'Cleaning up transfer history...',
    ])
</div>
