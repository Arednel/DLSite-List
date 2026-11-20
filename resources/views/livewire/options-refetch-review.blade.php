<section class="panel options-panel">
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

    @if (!$canApply && $run->isReview())
        <div class="notice">{{ __('A newer refetch run exists. This run is read-only.') }}</div>
    @elseif ($run->isApplied())
        <div class="notice">{{ __('This refetch run was applied.') }}</div>
    @elseif ($run->isRejected())
        <div class="notice">{{ __('This refetch run was rejected.') }}</div>
    @endif

    <div class="refetch-review">
        <nav class="refetch-tabs" role="tablist" aria-label="{{ __('Refetch change categories') }}">
            @foreach ($categoryReviews as $review)
                <button id="refetch-tab-{{ $run->getKey() }}-{{ $review['value'] }}" type="button" role="tab"
                    aria-selected="{{ $activeCategory === $review['value'] ? 'true' : 'false' }}"
                    aria-controls="refetch-panel-{{ $run->getKey() }}-{{ $review['value'] }}"
                    wire:click="showCategory('{{ $review['value'] }}')" wire:loading.attr="disabled"
                    wire:key="refetch-tab-{{ $review['value'] }}" @class([
                        'is-active' => $activeCategory === $review['value'],
                        'is-resolved' => $review['resolved'],
                    ])>
                    {{ $review['label'] }}
                    <span>{{ $review['count'] }}</span>
                </button>
            @endforeach
        </nav>

        <div class="stack">
            @if ($canApply)
                <div class="option-actions option-actions--primary">
                    <button type="button" class="tag tag--soft tag--lg is-clickable"
                        wire:click.preserve-scroll="overwriteAll" wire:loading.attr="disabled">
                        {{ __('Set Overwrite for All') }}
                    </button>
                    <i class="fa-solid fa-circle-question" tabindex="0"
                        aria-label="{{ __('About Set Overwrite for All') }}"
                        title="{{ __('Sets each unresolved tab that contains changes to Overwrite. Explicit per-change choices remain unchanged. Resolved tabs are not changed, and nothing is applied until you confirm Apply All Tabs or apply a tab separately.') }}"></i>
                    <button type="button" class="tag tag--gradient tag--lg is-clickable" wire:click="askApplyAll"
                        wire:loading.attr="disabled">
                        {{ __('Apply All Tabs') }}
                    </button>
                </div>
            @endif

            @foreach ($categoryReviews as $tabReview)
                <section id="refetch-panel-{{ $run->getKey() }}-{{ $tabReview['value'] }}" class="refetch-tab-panel"
                    role="tabpanel" aria-labelledby="refetch-tab-{{ $run->getKey() }}-{{ $tabReview['value'] }}"
                    wire:key="refetch-panel-{{ $tabReview['value'] }}" @if ($activeCategory !== $tabReview['value']) hidden @endif>
                    @if ($activeCategory === $tabReview['value'])
                        <header class="refetch-tab-header">
                            <div>
                                <h2>{{ $activeReview['label'] }}</h2>
                                @if ($activeReview['resolved'])
                                    <span class="tag tag--soft tag--sm">{{ __('Resolved') }}</span>
                                @endif
                            </div>

                            @if ($activeReview['has_changes'])
                                <label>
                                    {{ __('Global choice') }}
                                    <select wire:model="globalActions.{{ $activeReview['value'] }}"
                                        @disabled(!$canApply || $activeReview['resolved'])>
                                        @foreach ($globalActionOptions as $option)
                                            <option value="{{ $option['value'] }}">
                                                {{ __($option['label']) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif
                        </header>

                        @if (!$activeReview['has_changes'])
                            <p class="empty-state">
                                {{ $activeReview['is_image'] && !$run->check_images
                                    ? __('Images were not requested for this run.')
                                    : __('No changes detected.') }}
                            </p>
                        @else
                            <div class="refetch-change-list">
                                @foreach ($activeReview['cards'] as $card)
                                    <article class="result-card refetch-review-card"
                                        wire:key="refetch-change-{{ $activeReview['value'] }}-{{ $card['result_id'] }}-{{ $card['kind'] }}{{ $card['field'] ?? '' }}">
                                        <header>
                                            <strong>{{ $card['product_id'] }}</strong>
                                            <span>{{ $card['work_name'] }}</span>
                                        </header>

                                        @if ($card['kind'] === 'localized_text')
                                            @if ($card['japanese'])
                                                @include('livewire.partials.refetch-text-change', [
                                                    'item' => $card['japanese'],
                                                ])
                                            @endif

                                            @if ($card['localized'] !== [])
                                                <section class="refetch-localized-block">
                                                    <div class="refetch-localized-header">
                                                        <strong>{{ $card['localized_label'] }}</strong>
                                                        <span>{{ trans_choice(':count change|:count changes', count($card['localized']), ['count' => count($card['localized'])]) }}</span>
                                                    </div>
                                                    <div class="refetch-localized-list">
                                                        @foreach ($card['localized'] as $item)
                                                            <div class="refetch-localized-row">
                                                                <div class="refetch-locale-header">
                                                                    <h4>{{ $item['language_label'] }}</h4>
                                                                </div>
                                                                @include(
                                                                    'livewire.partials.refetch-text-change',
                                                                    ['item' => $item, 'showHeading' => false]
                                                                )
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </section>
                                            @endif
                                        @elseif ($card['kind'] === 'tags')
                                            @if ($card['japanese'])
                                                @include('livewire.partials.refetch-tag-locale', [
                                                    'item' => $card['japanese'],
                                                ])
                                            @endif

                                            @if ($card['localized'] !== [])
                                                <section class="refetch-localized-block">
                                                    <div class="refetch-localized-header">
                                                        <strong>{{ $card['localized_label'] }}</strong>
                                                        <span>{{ trans_choice(':count language|:count languages', count($card['localized']), ['count' => count($card['localized'])]) }}</span>
                                                    </div>
                                                    <div class="refetch-localized-list">
                                                        @foreach ($card['localized'] as $item)
                                                            @include(
                                                                'livewire.partials.refetch-tag-locale',
                                                                ['item' => $item]
                                                            )
                                                        @endforeach
                                                    </div>
                                                </section>
                                            @endif

                                            @if ($card['custom_to_fetched'] !== [])
                                                <section class="refetch-custom-to-fetched">
                                                    <div>
                                                        <strong>{{ __('Custom tags now returned as fetched') }}</strong>
                                                        <x-options.refetch-value :value="$card['custom_to_fetched']" />
                                                    </div>
                                                    <label>
                                                        {{ __('This change') }}
                                                        <select
                                                            wire:model="tagActions.{{ $card['result_id'] }}.custom_to_fetched"
                                                            @disabled(!$canApply || $activeReview['resolved'])>
                                                            @foreach ($tagCustomActionOptions as $option)
                                                                <option value="{{ $option['value'] }}">
                                                                    {{ __($option['label']) }}</option>
                                                            @endforeach
                                                        </select>
                                                    </label>
                                                </section>
                                            @endif
                                        @else
                                            <h3>{{ __($card['label']) }}</h3>
                                            <div class="refetch-change-comparison">
                                                <div>
                                                    <strong>{{ __('Current') }}</strong>
                                                    <x-options.refetch-value :value="$card['current']" :image="$activeReview['is_image']" />
                                                </div>
                                                <div>
                                                    <strong>{{ __('Refetched') }}</strong>
                                                    <x-options.refetch-value :value="$card['refetched']" :image="$activeReview['is_image']" />
                                                </div>
                                            </div>
                                            <label>
                                                {{ __('This change') }}
                                                <select
                                                    wire:model="actions.{{ $activeReview['value'] }}.{{ $card['result_id'] }}.{{ $card['field'] }}"
                                                    @disabled(!$canApply || $activeReview['resolved'])>
                                                    @foreach ($changeActionOptions as $option)
                                                        <option value="{{ $option['value'] }}">
                                                            {{ __($option['label']) }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                            {{ $activeReview['cards']->links('livewire.index-pagination-links', ['scrollTo' => 'refetch-panel-' . $run->getKey() . '-' . $activeReview['value']]) }}
                        @endif

                        @if ($canApply && !$activeReview['resolved'] && $activeReview['has_changes'])
                            <button type="button" class="tag tag--gradient tag--lg is-clickable"
                                wire:click="askApplyTab('{{ $activeReview['value'] }}')" wire:loading.attr="disabled">
                                {{ __('Apply Tab') }}
                            </button>
                        @endif
                    @endif
                </section>
            @endforeach
        </div>

    </div>

    @if ($canApply)
        <div class="option-actions">
            <button type="button" class="tag tag--outline tag--lg is-clickable" wire:click="askRejectOrFinish"
                wire:loading.attr="disabled">
                {{ __($finishAction['label']) }}
            </button>
        </div>
    @endif

    @include('livewire.partials.options-reset-confirmation-modal', [
        'open' => $confirmingApplyAll,
        'modalId' => 'refetch-apply-all-modal',
        'message' => 'Apply choices for every unresolved tab?',
        'confirmLabel' => 'Apply All Tabs',
        'confirmAction' => 'applyAll',
        'cancelAction' => 'cancelConfirmation',
        'loadingMessage' => 'Applying all tabs...',
    ])

    @include('livewire.partials.options-reset-confirmation-modal', [
        'open' => $confirmingApplyCategory !== null,
        'modalId' => 'refetch-apply-tab-modal',
        'message' => 'Apply and resolve this tab?',
        'confirmLabel' => 'Apply Tab',
        'confirmAction' => 'applyTab',
        'cancelAction' => 'cancelConfirmation',
        'loadingMessage' => 'Applying tab...',
    ])

    @include('livewire.partials.options-reset-confirmation-modal', [
        'open' => $confirmingRejectOrFinish,
        'modalId' => 'refetch-reject-or-finish-modal',
        'message' => $finishAction['confirmation'],
        'confirmLabel' => $finishAction['label'],
        'confirmAction' => 'rejectOrFinish',
        'cancelAction' => 'cancelConfirmation',
        'loadingMessage' => $finishAction['loading'],
    ])
</section>
