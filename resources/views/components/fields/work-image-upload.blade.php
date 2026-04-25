<tr>
    <td class="borderClass">Work Image</td>
    <td class="borderClass">
        <input id="work_image" name="work_image" class="inputtext file-upload-input" type="file" accept="image/*" required>
        @if ($errors->has('work_image'))
            <div class="text-error">{{ $errors->first('work_image') }}</div>
        @endif
    </td>
</tr>
