<tr>
    <td class="form-table-cell">{{ $label }}</td>
    <td class="form-table-cell">
        <select id="add_re_listen_value" name="add[re_listen_value]" class="form-control">
            <option value="" @selected(old('add.re_listen_value', $value) === null || old('add.re_listen_value', $value) === '')>
                {{ $placeholder }}</option>
            @foreach ($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected((string) old('add.re_listen_value', $value) === (string) $optionValue)>
                    {{ $optionLabel }}</option>
            @endforeach
        </select>
    </td>
</tr>
