@props(['label', 'value' => null, 'rows' => 2, 'long' => true])

<tr>
    <td width="130" class="form-table-cell">{{ $label }}</td>
    <td class="form-table-cell">
        <div @class([
            'form-textarea',
            'readonly-text-field',
            'form-field-long' => $long,
        ])>{{ filled($value) ? $value : '-' }}</div>
    </td>
    @if ($long)
        <td class="form-table-cell form-table-cell--long-spacer" aria-hidden="true"></td>
    @endif
</tr>
