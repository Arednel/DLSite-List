@props(['label', 'genres' => [], 'help' => null])

<tr>
    <td width="130" class="borderClass">{{ $label }}</td>
    <td class="borderClass">
        <textarea class="textarea" rows="3" cols="65" readonly>{{ collect($genres)->pluck('title')->implode(', ') ?: 'No fetched genres.' }}</textarea>

        @if ($help)
            <div class="field-helper">{{ $help }}</div>
        @endif
    </td>
</tr>
