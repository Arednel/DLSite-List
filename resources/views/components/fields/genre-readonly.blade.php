@props(['label', 'genres' => [], 'help' => null, 'empty' => 'No fetched genres.'])

<tr>
    <td width="130" class="form-table-cell">{{ $label }}</td>
    <td class="form-table-cell">
        <textarea class="form-textarea" rows="3" cols="65" readonly>{{ collect($genres)->pluck('title')->implode(', ') ?: $empty }}</textarea>

        @if ($help)
            <div class="field-helper">{{ $help }}</div>
        @endif
    </td>
</tr>
