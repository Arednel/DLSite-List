@props(['value' => ''])

<tr>
    <td width="130" class="form-table-cell">Fetched EN Genres</td>
    <td class="form-table-cell">
        <textarea id="genre_fetched_english" name="genre_fetched_english" class="form-textarea"
            placeholder="Comma-separated. Use double quotes for tags that contain commas." rows="3"
            cols="65">{{ old('genre_fetched_english', $value) }}</textarea>
        <i class="fa-solid fa-circle-question"
            title='Comma-separated. Use double quotes for tags that contain commas, e.g. "Junior / Senior (at work, school, etc)", Office Lady'></i>
    </td>
</tr>
