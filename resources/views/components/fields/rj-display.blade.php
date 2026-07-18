@props([
    'id' => '',
    'workName' => '',
])

<tr>
    <td width="130" class="form-table-cell" valign="top">{{ __('RJ Code + Title') }}</td>
    <td class="form-table-cell">
        <strong>
            {{ $id }} - {{ $workName }}
        </strong>
    </td>
    <td class="form-table-cell form-table-cell--long-spacer" aria-hidden="true"></td>
</tr>
