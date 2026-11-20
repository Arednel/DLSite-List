@if ($showFieldHeading ?? true)
    <h3>{{ $row['field_label'] }}</h3>
@endif

@if ($row['warning'])
    <div class="notice" role="status">{{ $row['warning'] }}</div>
@endif

<div class="refetch-change-comparison">
    @foreach ($row['previews'] as $label => $preview)
        <div>
            <strong>{{ __($label) }}</strong>
            <x-options.refetch-value :value="$preview['value']" :image="$preview['image']" />
        </div>
    @endforeach
</div>

@if ($category === 'tags')
    <div class="refetch-tag-delta-grid">
        <div class="refetch-tag-delta refetch-tag-delta--added">
            <strong>{{ __('Added from archive') }}</strong>
            <x-options.refetch-value :value="$row['added']" />
        </div>
        <div class="refetch-tag-delta refetch-tag-delta--stale">
            <strong>{{ __('Current-only') }}</strong>
            <x-options.refetch-value :value="$row['current_only']" />
        </div>
    </div>
@endif

@if ($reviewable)
    <label class="option-field refetch-locale-choice">
        {{ __('This change') }}
        <select class="option-control" wire:change="decide({{ $row['item_id'] }}, $event.target.value)"
            @disabled(!$row['pending'])>
            <option value="inherit" @selected(!$row['decision_override'])>{{ __('Use tab choice') }}</option>
            @foreach ($tabDecisionOptions as $decision => $label)
                <option value="{{ $decision }}" @selected($row['decision_override'] && $row['decision'] === $decision)>
                    {{ __($label) }}
                </option>
            @endforeach
        </select>
    </label>
@endif
