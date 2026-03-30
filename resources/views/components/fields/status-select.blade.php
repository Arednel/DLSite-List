<tr>
    <td class="borderClass">Status</td>
    <td class="borderClass">
        <select id="progress" name="progress" class="inputtext">
            @foreach ($options as $optionValue => $label)
                <option value="{{ $optionValue }}" @selected(old('progress', $value) === $optionValue)>
                    {{ $label }}</option>
            @endforeach
        </select>
    </td>
</tr>
