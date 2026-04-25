<tr>
    <td class="borderClass">Sample Images</td>
    <td class="borderClass">
        <input id="sample_images" name="sample_images[]" class="inputtext file-upload-input" type="file" accept="image/*" multiple>
        @if ($errors->has('sample_images'))
            <div class="text-error">{{ $errors->first('sample_images') }}</div>
        @endif
        @foreach ($errors->get('sample_images.*') as $messages)
            <div class="text-error">{{ $messages[0] }}</div>
        @endforeach
    </td>
</tr>
