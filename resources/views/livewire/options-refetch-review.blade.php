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
                                    <article class="result-card"
                                        wire:key="refetch-change-{{ $activeReview['value'] }}-{{ $card['result_id'] }}-{{ $card['field'] }}">
                                        <header>
                                            <strong>{{ $card['product_id'] }}</strong>
                                            <span>{{ $card['work_name'] }}</span>
                                        </header>
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

                                        @if ($activeReview['is_tags'])
                                            <div class="refetch-tag-details">
                                                @foreach ($card['tag_details'] as $detail)
                                                    <div>
                                                        <strong>{{ __($detail['label']) }}</strong>
                                                        <x-options.refetch-value :value="$detail['tags']" />
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif

                                        <label>
                                            {{ __('This change') }}
                                            <select
                                                wire:model="actions.{{ $activeReview['value'] }}.{{ $card['result_id'] }}.{{ $card['field'] }}"
                                                @disabled(!$canApply || $activeReview['resolved'])>
                                                @foreach ($activeReview['is_tags'] ? $tagChangeActionOptions : $changeActionOptions as $option)
                                                    <option value="{{ $option['value'] }}">
                                                        {{ __($option['label']) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </label>

                                        @if ($activeReview['is_tags'])
                                            <div class="review-actions review-actions--compact">
                                                @foreach ($tagActionFields as $tagAction)
                                                    <label>
                                                        {{ __($tagAction['label']) }}
                                                        <select
                                                            wire:model="tagActions.{{ $card['result_id'] }}.{{ $tagAction['key'] }}"
                                                            @disabled(!$canApply || $activeReview['resolved'])>
                                                            @foreach ($tagAction['options'] as $option)
                                                                <option value="{{ $option['value'] }}">
                                                                    {{ __($option['label']) }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </label>
                                                @endforeach
                                            </div>
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
    ])

    @include('livewire.partials.options-reset-confirmation-modal', [
        'open' => $confirmingApplyCategory !== null,
        'modalId' => 'refetch-apply-tab-modal',
        'message' => 'Apply and resolve this tab?',
        'confirmLabel' => 'Apply Tab',
        'confirmAction' => 'applyTab',
        'cancelAction' => 'cancelConfirmation',
    ])

    @include('livewire.partials.options-reset-confirmation-modal', [
        'open' => $confirmingRejectOrFinish,
        'modalId' => 'refetch-reject-or-finish-modal',
        'message' => $finishAction['confirmation'],
        'confirmLabel' => $finishAction['label'],
        'confirmAction' => 'rejectOrFinish',
        'cancelAction' => 'cancelConfirmation',
    ])
</section>
