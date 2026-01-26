@props(['value' => ''])

<tr>
    <td width="130" class="borderClass">Custom Tags</td>
    <td class="borderClass">
        <textarea id="genre_custom" name="genre_custom" class="textarea" rows="5" cols="65">{{ old('genre_custom', $value) }}</textarea>
    </td>
</tr>
