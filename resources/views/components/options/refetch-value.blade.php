@props(['value', 'image' => false])

@if ($image && is_string($value) && $value !== '')
    <img class="refetch-preview-image" src="{{ asset($value) }}" alt="">
@elseif ($image && is_array($value))
    @if ($value === [])
        <span class="empty-state">{{ __('None') }}</span>
    @else
        <div class="refetch-preview-images">
            @foreach ($value as $path)
                <img class="refetch-preview-image" src="{{ asset($path) }}" alt="">
            @endforeach
        </div>
    @endif
@elseif (is_array($value))
    @if ($value === [])
        <span class="empty-state">{{ __('None') }}</span>
    @elseif (array_is_list($value))
        <div class="tag-row">
            @foreach ($value as $item)
                <span @class([
                    'tag',
                    'tag--soft',
                    'tag--sm',
                    'tag--background-colored' =>
                        $item['colors']['has_background_color'] ?? false,
                    'tag--text-colored' => $item['colors']['has_font_color'] ?? false,
                ])
                    @if (filled($item['colors']['color_style'] ?? null)) style="{{ $item['colors']['color_style'] }}" @endif>{{ $item['value'] }}</span>
            @endforeach
        </div>
    @else
        <div class="refetch-language-values">
            @foreach ($value as $language => $items)
                <div>
                    <strong>{{ __(ucfirst((string) $language)) }}</strong>
                    <x-options.refetch-value :value="$items" />
                </div>
            @endforeach
        </div>
    @endif
@elseif ($value === null || $value === '')
    <span class="empty-state">{{ __('None') }}</span>
@else
    <span>{{ $value }}</span>
@endif
