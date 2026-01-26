@props(['value' => null])

<tr>
    <td class="borderClass">Re-listen Value</td>
    <td class="borderClass">
        <select id="add_re_listen_value" name="add[re_listen_value]" class="inputtext">
            <option value="" @selected(old('add.re_listen_value', $value) === null || old('add.re_listen_value', $value) === '')>
                Select re-listen value</option>
            <option value="1" @selected((string) old('add.re_listen_value', $value) === '1')>Very Low</option>
            <option value="2" @selected((string) old('add.re_listen_value', $value) === '2')>Low</option>
            <option value="3" @selected((string) old('add.re_listen_value', $value) === '3')>Medium</option>
            <option value="4" @selected((string) old('add.re_listen_value', $value) === '4')>High</option>
            <option value="5" @selected((string) old('add.re_listen_value', $value) === '5')>Very High</option>
        </select>
    </td>
</tr>
