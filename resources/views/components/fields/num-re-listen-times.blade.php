@props(['value' => ''])

<tr>
    <td class="form-table-cell">{{ __('Total Times Re-listened') }}</td>
    <td class="form-table-cell">
        <input type="text" id="add_num_re_listen_times" name="add[num_re_listen_times]" class="form-control"
            size="4" value="{{ old('add.num_re_listen_times', $value) }}">
    </td>
</tr>
