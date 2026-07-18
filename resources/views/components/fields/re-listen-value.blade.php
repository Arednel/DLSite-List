<tr>
    <td class="form-table-cell">{{ __('Re-listen Value') }}</td>
    <td class="form-table-cell">
        <select id="add_re_listen_value" name="add[re_listen_value]" class="form-control">
            <option value="" @selected(old('add.re_listen_value', $value) === null || old('add.re_listen_value', $value) === '')>
                {{ __('Select re-listen value') }}</option>
            @foreach ($options as $optionValue => $label)
                <option value="{{ $optionValue }}" @selected((string) old('add.re_listen_value', $value) === (string) $optionValue)>
                    {{ $label }}</option>
            @endforeach
        </select>
    </td>
</tr>
