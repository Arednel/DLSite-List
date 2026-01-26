@props(['value' => ''])

@php
    $seriesValue = old('series', $value);
@endphp

<tr>
    <td class="borderClass" valign="top">Series</td>
    <td class="borderClass">
        <textarea id="series" name="series" class="inputtext" rows="2" cols="65">{{ $seriesValue }}</textarea>
    </td>
</tr>
