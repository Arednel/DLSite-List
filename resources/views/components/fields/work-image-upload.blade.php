<tr>
    <td class="form-table-cell">Work Image</td>
    <td class="form-table-cell">
        <input id="work_image" name="work_image" class="form-control file-upload-input" type="file" accept="image/*" required>
        @if ($errors->has('work_image'))
            <div class="text-error">{{ $errors->first('work_image') }}</div>
        @endif
    </td>
</tr>
