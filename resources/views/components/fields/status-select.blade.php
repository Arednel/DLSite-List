<tr>
    <td class="form-table-cell">Status</td>
    <td class="form-table-cell">
        <select id="progress" name="progress" class="form-control">
            @foreach ($options as $optionValue => $label)
                <option value="{{ $optionValue }}" @selected(old('progress', $value) === $optionValue)>
                    {{ $label }}</option>
            @endforeach
        </select>
    </td>
</tr>
