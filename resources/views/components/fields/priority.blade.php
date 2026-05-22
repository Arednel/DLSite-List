<tr>
    <td class="form-table-cell">Priority</td>
    <td class="form-table-cell">
        <select id="add_priority" name="add[priority]" class="form-control">
            <option value="" @selected(old('add.priority', $value) === null || old('add.priority', $value) === '')>
                Select priority</option>
            @foreach ($options as $optionValue => $label)
                <option value="{{ $optionValue }}" @selected((string) old('add.priority', $value) === (string) $optionValue)>
                    {{ $label }}</option>
            @endforeach
        </select>
    </td>
</tr>
