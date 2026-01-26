@props(['value' => null])

<tr>
    <td class="borderClass">Priority</td>
    <td class="borderClass">
        <select id="add_priority" name="add[priority]" class="inputtext">
            <option value="" @selected(old('add.priority', $value) === null || old('add.priority', $value) === '')>
                Select priority</option>
            <option value="0" @selected((string) old('add.priority', $value) === '0')>Low</option>
            <option value="1" @selected((string) old('add.priority', $value) === '1')>Medium</option>
            <option value="2" @selected((string) old('add.priority', $value) === '2')>High</option>
        </select>
    </td>
</tr>
