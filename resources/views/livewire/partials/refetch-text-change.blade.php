@if ($showHeading ?? true)
    <h3>{{ $item['label'] }}</h3>
@endif
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
<label class="refetch-locale-choice">
    {{ __('This change') }}
    <select wire:model="actions.{{ $activeReview['value'] }}.{{ $card['result_id'] }}.{{ $item['field'] }}"
        @disabled(!$canApply || $activeReview['resolved'])>
        @foreach ($changeActionOptions as $option)
            <option value="{{ $option['value'] }}">{{ __($option['label']) }}</option>
        @endforeach
    </select>
</label>
