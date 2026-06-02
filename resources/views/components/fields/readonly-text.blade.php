@props(['label', 'value' => null, 'rows' => 2])

<tr>
    <td width="130" class="form-table-cell">{{ $label }}</td>
    <td class="form-table-cell">
        <textarea class="form-textarea" rows="{{ $rows }}" cols="65" readonly>{{ filled($value) ? $value : '-' }}</textarea>
    </td>
</tr>
