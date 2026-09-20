<div class="transfer-ui transfer-run" data-transfer-run="{{ $run->id }}">
    <section class="panel">
        @island(name: 'transfer-progress', always: true)
            <div class="transfer-progress" @if ($this->progressPolling) wire:poll.visible.3s="pollProgress" @endif>
                <h2>{{ __('Progress') }}</h2>
                <div class="progress-summary">
                    <span role="status">{{ $this->progressData['description'] }}</span>
                    <span>{{ __(ucfirst($this->run->direction->value)) }} -
                        {{ __(str_replace('_', ' ', $this->run->status->value)) }}</span>
                </div>
                <div class="progress-track" role="progressbar" aria-label="{{ __('Background progress') }}" aria-valuemin="0"
                    aria-valuemax="100" aria-valuenow="{{ $this->progressPercent }}">
                    <div class="progress-fill" style="width: {{ $this->progressPercent }}%"></div>
                </div>
                <div class="summary-grid">
                    <div>{{ __('Processed') }} <strong>{{ $this->progressData['processed'] }}</strong></div>
                    <div>{{ __('Remaining') }}
                        <strong>{{ max(0, $this->progressData['total'] - $this->progressData['processed']) }}</strong>
                    </div>
                    <div>{{ __('Total') }} <strong>{{ $this->progressData['total'] }}</strong></div>
                </div>
                @if ($this->run->direction->isImport())
                    @foreach ($this->run->warnings ?? [] as $warning)
                        <div class="notice" role="status">{{ $warning }}</div>
                    @endforeach
                @endif
            </div>
        @endisland
        @if ($errors->any())
            <details class="review-errors">
                <summary>{{ __('Errors') }} ({{ $errors->count() }})</summary>
                <ul class="review-errors__list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </details>
        @endif
        @if ($run->status->isAwaitingConfirmation())
            <p class="option-description">
                @if ($expected['images'] > 0)
                    {{ __('This export needs :count individual files (:data data, :images images), approximately :size MiB in total.', ['count' => array_sum($expected), 'data' => $expected['data'], 'images' => $expected['images'], 'size' => number_format($partCounts->sum('bytes') / 1048576, 1)]) }}
                @else
                    {{ __('This export needs :count data files, approximately :size MiB in total.', ['count' => $expected['data'], 'size' => number_format($partCounts->sum('bytes') / 1048576, 1)]) }}
                @endif
            </p>
            <div class="option-actions">
                <button class="tag tag--gradient tag--lg is-clickable" type="button"
                    wire:click="ask('continue_export')">{{ __('Continue export') }}</button>
            </div>
        @endif
        @if ($run->acceptsNewParts() && $needsParts)
            <x-transfer-upload :run="$run" :upload-max-bytes="$uploadMaxBytes ?? 0" />
            @if ($run->status->isWaitingForParts() && $dataReady && !($run->settings['without_images'] ?? false))
                <div class="option-actions">
                    <button class="tag tag--soft tag--lg is-clickable" type="button"
                        wire:click="ask('without_images')">{{ __('Continue without images') }}</button>
                </div>
            @endif
        @endif
        @if ($run->settings['without_images'] ?? false)
            <p class="notice">{{ __('All archive images are permanently excluded from this run.') }}</p>
        @endif
        @if ($partSlots->isNotEmpty() || $needsParts)
            <details id="transfer-parts" open class="result-card transfer-parts">
                <summary class="transfer-parts__heading">{{ __('Archive parts') }}</summary>
                @if ($needsParts)
                    <h3 class="transfer-parts__prompt" role="status">
                        {{ isset($run->settings['manifest'])
                            ? __('Attach the required ZIP files listed below.')
                            : __('Attach at least one valid export ZIP file to identify the archive set.') }}
                    </h3>
                @endif
                @if ($partSlots->isNotEmpty())
                    <p>
                        {{ __('Data') }}: {{ $partCounts['data']->total ?? 0 }} / {{ $expected['data'] }}
                        @if ($expected['images'] > 0)
                            · {{ __('Images') }}: {{ $partCounts['images']->total ?? 0 }} /
                            {{ $expected['images'] }}
                        @endif
                    </p>
                    @foreach ($partSlots as $slot)
                        <p wire:key="part-{{ $slot['kind'] }}-{{ $slot['number'] }}">
                            @if ($slot['part'])
                                @if ($run->direction->isExport() && $run->status->isReady())
                                    <a
                                        href="{{ route('options.transfers.download', [$run, $slot['part']]) }}">{{ $slot['part']->filename }}</a>
                                @elseif ($run->direction->isImport())
                                    {{ $slot['part']->kind === 'data'
                                        ? __('Attached data ZIP file: :file', ['file' => $slot['part']->filename])
                                        : __('Attached image ZIP file: :file', ['file' => $slot['part']->filename]) }}
                                @else
                                    {{ $slot['part']->filename }}
                                @endif
                                - {{ __($slot['part']->status->value) }}
                                ({{ number_format($slot['part']->bytes / 1048576, 2) }} MiB)
                                @if ($run->direction->isImport() && $slot['part']->status->isInvalid())
                                    -
                                    {{ $slot['part']->candidate
                                        ? __('Replacement file attached; validation is pending.')
                                        : __('Expected replacement file: :file', ['file' => $slot['filename'] ?? $slot['part']->filename]) }}
                                @endif
                                @if ($slot['part']->error)
                                    <span class="notice--error">{{ $slot['part']->error }}</span>
                                @endif
                            @else
                                {{ $slot['filename']
                                    ? ($slot['kind'] === 'data'
                                        ? __('Waiting for data ZIP file: :file', ['file' => $slot['filename']])
                                        : __('Waiting for image ZIP file: :file', ['file' => $slot['filename']]))
                                    : __('Missing :kind part :number', ['kind' => $slot['kind'], 'number' => $slot['number']]) }}
                            @endif
                        </p>
                    @endforeach
                @endif
            </details>
        @endif
        @if ($downloadParts->isNotEmpty())
            <div class="option-actions" data-transfer-downloads
                data-started="{{ __('Choose a folder for the archive files.') }}"
                data-complete="{{ trans_choice(':count archive file saved.|:count archive files saved.', $downloadParts->count(), ['count' => $downloadParts->count()]) }}"
                data-cancelled="{{ __('Download cancelled.') }}"
                data-failed="{{ __('Unable to save every archive file.') }}">
                <button class="tag tag--gradient tag--lg is-clickable" type="button" data-download-all>
                    {{ __('Download all') }}
                </button>
                @foreach ($downloadParts as $downloadPart)
                    <a hidden href="{{ route('options.transfers.download', [$run, $downloadPart]) }}"
                        data-download-part>{{ $downloadPart->filename }}</a>
                @endforeach
                <span class="option-description" role="status" aria-live="polite" data-download-status></span>
            </div>
        @endif
        @if ($run->status->isRetryable() && ($run->settings['retry_operation'] ?? null))
            <div class="option-actions">
                <button class="tag tag--soft tag--md is-clickable" type="button"
                    wire:click="ask('retry')">{{ __('Retry') }}</button>
            </div>
        @endif
        @if (!$counts?->isNotEmpty() && ($run->status->isCancellable() || !$run->busy()))
            <div class="option-actions">
                @if ($run->status->isCancellable())
                    <button class="tag tag--outline tag--md is-clickable" type="button"
                        wire:click="ask('cancel')">{{ $run->direction->isImport() ? __('Cancel Import') : __('Cancel Export') }}</button>
                @endif
                @if (!$run->busy())
                    <button class="tag tag--soft tag--md is-clickable" type="button"
                        wire:click="ask('cleanup')">{{ $run->direction->isImport() ? __('Clean up this Import') : __('Clean up this Export') }}</button>
                @endif
            </div>
        @endif
    </section>
    @if ($counts?->isNotEmpty())
        <section class="panel">
            <h2>{{ __('Review changes') }}</h2>
            <div class="refetch-review">
                @if ($run->reviewable())
                    <div class="option-actions">
                        @foreach ($decisionOptions as $decision => $label)
                            <div class="transfer-decision-option">
                                <button class="tag tag--soft tag--lg is-clickable" type="button"
                                    wire:click="decideMany('{{ $decision }}', true)">{{ __($label . ' all') }}</button>
                                <i class="fa-solid fa-circle-question" tabindex="0"
                                    aria-label="{{ __('About :decision', ['decision' => __($label)]) }}"
                                    title="{{ $decisionHelp[$decision] }}"></i>
                            </div>
                        @endforeach
                        <div class="transfer-decision-option">
                            <button class="tag tag--outline tag--lg is-clickable" type="button"
                                wire:click="ask('refresh')">{{ __('Refresh conflicts') }}</button>
                            <i class="fa-solid fa-circle-question" tabindex="0"
                                aria-label="{{ __('About Refresh conflicts') }}"
                                title="{{ __('Rechecks failed and conflicting changes against current local data, then resets them to Ignore.') }}"></i>
                        </div>
                        <button class="tag tag--gradient tag--lg is-clickable" type="button"
                            wire:click="ask('apply', true)">{{ __('Apply All') }}</button>
                    </div>
                @endif
                <nav class="refetch-tabs" role="tablist" aria-label="{{ __('Import sections') }}">
                    @foreach ($mainTabs as $mainKey => $mainTab)
                        @if (in_array($mainKey, $availableMainTabs, true))
                            <button id="transfer-main-tab-{{ $mainKey }}" type="button" role="tab"
                                aria-controls="transfer-review-panel" @class(['is-active' => $activeMainTab === $mainKey])
                                wire:click="mainTab('{{ $mainKey }}')" wire:loading.attr="disabled"
                                aria-selected="{{ $activeMainTab === $mainKey ? 'true' : 'false' }}">
                                {{ $mainTab['label'] }}
                            </button>
                        @endif
                    @endforeach
                </nav>
                @if ($category === 'new_works')
                    <nav class="refetch-tabs" role="tablist" aria-label="{{ __('New work categories') }}">
                        @foreach ($newWorkCategories as $newWorkTab)
                            <button id="new-work-tab-{{ $newWorkTab }}" type="button" role="tab"
                                aria-controls="new-work-panel" @class(['is-active' => $newWorkCategory === $newWorkTab])
                                wire:click="newWorkTab('{{ $newWorkTab }}')" wire:loading.attr="disabled"
                                aria-selected="{{ $newWorkCategory === $newWorkTab ? 'true' : 'false' }}">
                                {{ $labels[$newWorkTab] }}
                                <span>{{ $newWorkCounts[$newWorkTab] ?? 0 }}</span>
                            </button>
                        @endforeach
                    </nav>
                @elseif ($section !== 'options')
                    <nav class="refetch-tabs" role="tablist" aria-label="{{ __('Review categories') }}">
                        @foreach ($section === 'works' ? array_values(array_diff($categories[$section], ['new_works'])) : $categories[$section] as $tab)
                            @if (isset($counts[$section . '.' . $tab]))
                                <button id="transfer-category-tab-{{ $section }}-{{ $tab }}"
                                    type="button" role="tab" aria-controls="transfer-review-panel"
                                    @class(['is-active' => $category === $tab])
                                    wire:click="tab('{{ $section }}', '{{ $tab }}')"
                                    wire:loading.attr="disabled"
                                    aria-selected="{{ $category === $tab ? 'true' : 'false' }}">
                                    {{ $labels[$tab] }}
                                    <span>{{ $counts[$section . '.' . $tab]->total }}</span>
                                </button>
                            @endif
                        @endforeach
                    </nav>
                @endif
                <section id="transfer-review-panel" class="refetch-tab-panel" role="tabpanel"
                    aria-labelledby="{{ $section === 'options' ? 'transfer-main-tab-options' : ($category === 'new_works' ? 'new-work-tab-' . $newWorkCategory : 'transfer-category-tab-' . $section . '-' . $category) }}"
                    wire:key="transfer-category-{{ $section }}-{{ $category }}">
                    <header class="refetch-tab-header">
                        <div>
                            <h2>{{ $labels[$category] }}</h2>
                        </div>
                        @if ($run->reviewable())
                            <label>{{ __('Tab choice') }}
                                <select class="option-control" wire:change="decideMany($event.target.value)">
                                    @foreach ($tabDecisionOptions as $decision => $label)
                                        <option value="{{ $decision }}" @selected(($run->settings['defaults'][$section][$category] ?? 'ignore') === $decision)>
                                            {{ __($label) }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif
                    </header>
                    <div class="refetch-change-list"
                        @if ($category === 'new_works') id="new-work-panel" role="tabpanel" aria-labelledby="new-work-tab-{{ $newWorkCategory }}" @endif>
                        @forelse ($items as $item)
                            <article class="result-card"
                                wire:key="review-item-{{ $item->id }}-{{ $category === 'new_works' ? $newWorkCategory : $category }}">
                                <header>
                                    <strong>{{ $section === 'options' ? $labels[$item->entity_key] : $item->entity_key }}</strong>
                                    @if ($category === 'new_works')
                                        <span>{{ __('New work import') }}</span>
                                    @elseif ($section === 'works')
                                        <span>{{ $item->metadata['title'] ?? '' }}</span>
                                    @endif
                                </header>
                                @if ($section === 'works' && $category !== 'new_works')
                                    <h3>{{ $labels[$item->metadata['field'] ?? $category] }}</h3>
                                @endif
                                @if ($item->metadata['warning'] ?? null)
                                    <div class="notice" role="status">{{ __($item->metadata['warning']) }}</div>
                                @endif
                                @if ($category === 'new_works')
                                    @forelse ($newWorkChanges[$item->id] ?? [] as $change)
                                        <div class="stack transfer-new-work-change">
                                            <h3>{{ $change['label'] }}</h3>
                                            <div class="refetch-change-comparison">
                                                <div>
                                                    <strong>{{ __('Current') }}</strong>
                                                    <span
                                                        class="empty-state">{{ __('No existing local work') }}</span>
                                                </div>
                                                <div>
                                                    <strong>{{ __('Imported') }}</strong>
                                                    <x-options.refetch-value :value="$change['preview']['value']" :image="$change['preview']['image']" />
                                                    @if ($change['preview']['truncated'])
                                                        <p><a
                                                                href="{{ route('options.transfers.change', [$run, $item]) }}">{{ __('Preview truncated. Download complete change JSON before approving.') }}</a>
                                                        </p>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        @if (!$item->error)
                                            <p class="empty-state">{{ __('No new works include this category.') }}
                                            </p>
                                        @endif
                                    @endforelse
                                @else
                                    <div class="refetch-change-comparison">
                                        @foreach ($previews[$item->id] ?? [] as $label => $preview)
                                            <div>
                                                <strong>{{ __($label) }}</strong>
                                                <x-options.refetch-value :value="$preview['value']" :image="$preview['image']" />
                                                @if ($preview['truncated'])
                                                    <p><a
                                                            href="{{ route('options.transfers.change', [$run, $item]) }}">{{ __('Preview truncated. Download complete change JSON before approving.') }}</a>
                                                    </p>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                @if ($run->reviewable())
                                    <label
                                        class="option-field">{{ __($category === 'new_works' ? 'This work' : 'This change') }}
                                        <select class="option-control"
                                            wire:change="decide({{ $item->id }}, $event.target.value)"
                                            @disabled(!$run->reviewable() || !$item->status->isPending())>
                                            <option value="inherit" @selected(!$item->decision_override)>
                                                {{ __('Use tab choice') }}</option>
                                            @foreach ($tabDecisionOptions as $decision => $label)
                                                <option value="{{ $decision }}" @selected($item->decision_override && $item->decision->value === $decision)>
                                                    {{ __($label) }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                @endif
                            </article>
                        @empty
                            <p class="empty-state">
                                {{ __(
                                    $run->busy()
                                        ? 'Review is unavailable while background processing is running.'
                                        : ($category === 'new_works'
                                            ? 'No new works include this category.'
                                            : 'No review items in this category.'),
                                ) }}
                            </p>
                        @endforelse
                    </div>
                    {{ $items->links('livewire.index-pagination-links', ['scrollTo' => 'transfer-review-panel']) }}
                    @if ($run->reviewable())
                        <button class="tag tag--gradient tag--lg is-clickable" type="button"
                            wire:click="ask('apply')">{{ __($category === 'new_works' ? 'Apply All New Works Tabs' : 'Apply Tab') }}</button>
                    @endif
                </section>
            </div>
            @if ($run->status->isCancellable() || !$run->busy())
                <div class="option-actions">
                    @if ($run->status->isCancellable())
                        <button class="tag tag--outline tag--md is-clickable" type="button"
                            wire:click="ask('cancel')">{{ $run->direction->isImport() ? __('Cancel Import') : __('Cancel Export') }}</button>
                    @endif
                    @if (!$run->busy())
                        <button class="tag tag--soft tag--md is-clickable" type="button"
                            wire:click="ask('cleanup')">{{ $run->direction->isImport() ? __('Clean up this Import') : __('Clean up this Export') }}</button>
                    @endif
                </div>
            @endif
        </section>
    @endif
    @include('livewire.partials.options-reset-confirmation-modal', [
        'open' => $confirmation !== null,
        'modalId' => 'transfer-confirm',
        'message' => $this->confirmationMessage,
        'confirmLabel' => 'Continue',
        'confirmAction' => 'confirm',
        'cancelAction' => 'cancelConfirmation',
    ])
</div>
