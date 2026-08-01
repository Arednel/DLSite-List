<section class="panel options-panel">
    @if ($errors->any())
        <div class="notice notice--error">{{ $errors->first() }}</div>
    @endif

    @foreach ($failedResults as $result)
        <div class="notice notice--error">
            <strong>{{ $result->product_id }}</strong>: {{ $result->displayError() }}
        </div>
    @endforeach

    @foreach ($run->results as $result)
        @foreach ($result->warnings ?? [] as $warning)
            <div class="notice">
                <strong>{{ $result->product_id }}</strong>:
                {{ __($warning['key'], $warning['replace'] ?? []) }}
            </div>
        @endforeach
    @endforeach

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
                    wire:click.preserve-scroll="showCategory('{{ $review['value'] }}')" wire:loading.attr="disabled"
                    wire:key="refetch-tab-{{ $review['value'] }}" @class([
                        'is-active' => $activeCategory === $review['value'],
                        'is-resolved' => $review['resolved'],
                    ])>
                    {{ $review['label'] }}
                    <span>{{ $review['count'] }}</span>
                </button>
            @endforeach
        </nav>

        <form wire:submit="applyAll" class="stack">
            @if ($canApply)
                <div class="option-actions option-actions--primary">
                    <button type="button" class="tag tag--soft tag--lg is-clickable"
                        wire:click.preserve-scroll="overwriteAll" wire:loading.attr="disabled">
                        {{ __('Set Overwrite for All') }}
                    </button>
                    <i class="fa-solid fa-circle-question" tabindex="0"
                        aria-label="{{ __('About Set Overwrite for All') }}"
                        title="{{ __('Sets each unresolved tab that contains changes to Overwrite. Changes still set to Use global choice will inherit Overwrite; explicit per-change choices remain unchanged. Resolved tabs are not changed, and nothing is applied until you confirm Apply All Tabs or apply a tab separately.') }}"></i>
                    <button type="submit" class="tag tag--gradient tag--lg is-clickable"
                        wire:confirm="{{ __('Apply choices for every unresolved tab?') }}">
                        {{ __('Apply All Tabs') }}
                    </button>
                </div>
            @endif

            @foreach ($categoryReviews as $review)
                <section id="refetch-panel-{{ $run->getKey() }}-{{ $review['value'] }}" class="refetch-tab-panel"
                    role="tabpanel" aria-labelledby="refetch-tab-{{ $run->getKey() }}-{{ $review['value'] }}"
                    wire:show="activeCategory === '{{ $review['value'] }}'" wire:cloak
                    wire:key="refetch-panel-{{ $review['value'] }}">
                    <header class="refetch-tab-header">
                        <div>
                            <h2>{{ $review['label'] }}</h2>
                            @if ($review['resolved'])
                                <span class="tag tag--soft tag--sm">{{ __('Resolved') }}</span>
                            @endif
                        </div>

                        @if ($review['has_changes'])
                            <label>
                                {{ __('Global choice') }}
                                <select wire:model="globalActions.{{ $review['value'] }}" @disabled(!$canApply || $review['resolved'])>
                                    @foreach ($globalActionOptions as $option)
                                        <option value="{{ $option['value'] }}">
                                            {{ __($option['label']) }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        @endif
                    </header>

                    @if (!$review['has_changes'])
                        <p class="empty-state">
                            {{ $review['is_image'] && !$run->check_images
                                ? __('Images were not requested for this run.')
                                : __('No changes detected.') }}
                        </p>
                    @endif

                    <div class="refetch-change-list">
                        @foreach ($review['results'] as $result)
                            @foreach ($result['changes'] as $change)
                                <article class="result-card"
                                    wire:key="refetch-change-{{ $review['value'] }}-{{ $result['id'] }}-{{ $change['field'] }}">
                                    <header>
                                        <strong>{{ $result['product_id'] }}</strong>
                                        <span>{{ $result['work_name'] }}</span>
                                    </header>
                                    <h3>{{ __($change['label']) }}</h3>
                                    <div class="refetch-change-comparison">
                                        <div>
                                            <strong>{{ __('Current') }}</strong>
                                            <x-options.refetch-value :value="$change['old']" :image="$review['is_image']" />
                                        </div>
                                        <div>
                                            <strong>{{ __('Refetched') }}</strong>
                                            <x-options.refetch-value :value="$change['new']" :image="$review['is_image']" />
                                        </div>
                                    </div>

                                    @if ($review['is_tags'])
                                        <div class="refetch-tag-details">
                                            @foreach ($change['tag_details'] as $detail)
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
                                            wire:model="actions.{{ $review['value'] }}.{{ $result['id'] }}.{{ $change['field'] }}"
                                            @disabled(!$canApply || $review['resolved'])>
                                            @foreach ($review['is_tags'] ? $tagChangeActionOptions : $changeActionOptions as $option)
                                                <option value="{{ $option['value'] }}">
                                                    {{ __($option['label']) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </label>

                                    @if ($review['is_tags'])
                                        <div class="review-actions review-actions--compact">
                                            @foreach ($tagActionFields as $tagAction)
                                                <label>
                                                    {{ __($tagAction['label']) }}
                                                    <select
                                                        wire:model="tagActions.{{ $result['id'] }}.{{ $tagAction['key'] }}"
                                                        @disabled(!$canApply || $review['resolved'])>
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
                        @endforeach
                    </div>

                    @if ($canApply && !$review['resolved'] && $review['has_changes'])
                        <button type="button" class="tag tag--gradient tag--lg is-clickable"
                            wire:click="applyTab('{{ $review['value'] }}')"
                            wire:confirm="{{ __('Apply and resolve this tab?') }}" wire:loading.attr="disabled">
                            {{ __('Apply Tab') }}
                        </button>
                    @endif
                </section>
            @endforeach
        </form>

        @if ($canApply)
            <div class="option-actions">
                <button type="button" class="tag tag--outline tag--lg is-clickable" wire:click="rejectOrFinish"
                    wire:confirm="{{ __($finishAction['confirmation']) }}" wire:loading.attr="disabled">
                    {{ __($finishAction['label']) }}
                </button>
            </div>
        @endif
    </div>
</section>
