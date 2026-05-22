@props([
    'id' => '',
    'workName' => '',
])

<tr>
    <td width="130" class="form-table-cell" valign="top">RJ Code + Title</td>
    <td class="form-table-cell">
        <strong>
            {{ $id }} - {{ $workName }}
        </strong>
    </td>
</tr>
