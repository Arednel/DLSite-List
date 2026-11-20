<div class="refetch-tag-locale">
    <div class="refetch-locale-header">
        <h4>{{ $item['language_label'] }}</h4>
        <span class="refetch-tag-counts">
            <span class="refetch-tag-count refetch-tag-count--added">+{{ count($item['added']) }}</span>
            <span class="refetch-tag-count refetch-tag-count--stale">−{{ count($item['stale']) }}</span>
        </span>
    </div>

    <div class="refetch-change-comparison">
        <div>
            <strong>{{ __('Current') }}</strong>
            <x-options.refetch-value :value="$item['current']" />
        </div>
        <div>
            <strong>{{ __('Refetched') }}</strong>
            <x-options.refetch-value :value="$item['refetched']" />
        </div>
    </div>

    <div class="refetch-tag-delta-grid">
        <div class="refetch-tag-delta refetch-tag-delta--added">
            <strong>{{ __('Added') }}</strong>
            <x-options.refetch-value :value="$item['added']" />
        </div>
        <div class="refetch-tag-delta refetch-tag-delta--stale">
            <strong>{{ __('No longer fetched') }}</strong>
            <x-options.refetch-value :value="$item['stale']" />
        </div>
    </div>

    <label class="refetch-locale-choice">
        {{ __('This change') }}
        <select wire:model.change.live="actions.tags.{{ $card['result_id'] }}.{{ $item['locale'] }}"
            @disabled(!$canApply || $activeReview['resolved'])>
            @foreach ($tagChangeActionOptions as $option)
                <option value="{{ $option['value'] }}">{{ __($option['label']) }}</option>
            @endforeach
        </select>
    </label>

    @if ($item['show_detailed_actions'])
        <div class="refetch-tag-detailed-actions">
            <label>
                {{ __('New tags') }}
                <select wire:model="tagActions.{{ $card['result_id'] }}.locales.{{ $item['locale'] }}.added"
                    @disabled(!$canApply || $activeReview['resolved'])>
                    @foreach ($tagAddedActionOptions as $option)
                        <option value="{{ $option['value'] }}">{{ __($option['label']) }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                {{ __('No longer fetched tags') }}
                <select wire:model="tagActions.{{ $card['result_id'] }}.locales.{{ $item['locale'] }}.stale"
                    @disabled(!$canApply || $activeReview['resolved'])>
                    @foreach ($tagStaleActionOptions as $option)
                        <option value="{{ $option['value'] }}">{{ __($option['label']) }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    @endif
</div>
