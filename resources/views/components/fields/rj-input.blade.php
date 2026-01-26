@props(['value' => ''])

<tr>
    <td width="130" class="borderClass" valign="top">RJ Code or Link</td>
    <td class="borderClass">
        <strong>
            <input id="id" name="id" class="inputtext" size="65" required
                value="{{ old('id', $value) }}">
        </strong>
        @if ($errors->has('id'))
            <div class="text-error">{{ $errors->first('id') }}</div>
        @endif
    </td>
</tr>
