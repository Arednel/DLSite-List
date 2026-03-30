@props(['value' => ''])

<tr>
    <td class="borderClass" valign="top">Series</td>
    <td class="borderClass">
        <textarea id="series" name="series" class="inputtext" rows="2" cols="65">{{ old('series', $value) }}</textarea>
    </td>
</tr>
