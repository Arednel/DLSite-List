@props([
    'id' => '',
    'workName' => '',
])

<tr>
    <td width="130" class="borderClass" valign="top">RJ Code + Title</td>
    <td class="borderClass">
        <strong>
            {{ $id }} - {{ $workName }}
        </strong>
    </td>
</tr>
