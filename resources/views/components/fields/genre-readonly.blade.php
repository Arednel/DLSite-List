@props(['label', 'genres' => [], 'help' => null, 'empty' => 'No fetched genres.', 'showColorChips' => false])

<tr>
    <td width="130" class="form-table-cell">{{ $label }}</td>
    <td class="form-table-cell">
        @if ($showColorChips)
            <div class="form-textarea form-field-long readonly-tag-text-field" aria-label="{{ $label }}">
                @forelse ($genres as $genre)
                    <span @class([
                        'readonly-tag-text',
                        'readonly-tag-text--background-colored' => data_get(
                            $genre,
                            'has_background_color'),
                        'readonly-tag-text--font-colored' => data_get($genre, 'has_font_color'),
                    ])
                        @if (filled(data_get($genre, 'color_style'))) style="{{ data_get($genre, 'color_style') }}" @endif>{{ data_get($genre, 'title') }}</span>
                    @unless ($loop->last)
                        <span class="readonly-tag-separator">, </span>
                    @endunless
                @empty
                    <span class="readonly-tag-empty">{{ __($empty) }}</span>
                @endforelse
            </div>
        @else
            <textarea class="form-textarea form-field-long" rows="3" cols="65" readonly>{{ collect($genres)->pluck('title')->implode(', ') ?: __($empty) }}</textarea>
        @endif

        @if ($help)
            <div class="field-helper">{{ __($help) }}</div>
        @endif
    </td>
    <td class="form-table-cell form-table-cell--long-spacer" aria-hidden="true"></td>
</tr>
