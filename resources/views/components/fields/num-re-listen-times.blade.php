@props(['value' => ''])

<tr>
    <td class="borderClass">Total Times<br>Re-listened</td>
    <td class="borderClass">
        <input type="text" id="add_num_re_listen_times" name="add[num_re_listen_times]" class="inputtext" size="4"
            value="{{ old('add.num_re_listen_times', $value) }}">
    </td>
</tr>
