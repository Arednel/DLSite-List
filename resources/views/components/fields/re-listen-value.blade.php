<tr>
    <td class="borderClass">Re-listen Value</td>
    <td class="borderClass">
        <select id="add_re_listen_value" name="add[re_listen_value]" class="inputtext">
            <option value="" @selected(old('add.re_listen_value', $value) === null || old('add.re_listen_value', $value) === '')>
                Select re-listen value</option>
            @foreach ($options as $optionValue => $label)
                <option value="{{ $optionValue }}" @selected((string) old('add.re_listen_value', $value) === (string) $optionValue)>
                    {{ $label }}</option>
            @endforeach
        </select>
    </td>
</tr>
