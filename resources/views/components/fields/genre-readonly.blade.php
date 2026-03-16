@props(['label', 'genres' => [], 'help' => null])

@php
    $genreText = collect($genres)->pluck('title')->implode(', ');
@endphp

<tr>
    <td width="130" class="borderClass">{{ $label }}</td>
    <td class="borderClass">
        <textarea class="textarea" rows="3" cols="65" readonly>{{ $genreText !== '' ? $genreText : 'No fetched genres.' }}</textarea>

        @if ($help)
            <div class="field-helper">{{ $help }}</div>
        @endif
    </td>
</tr>
