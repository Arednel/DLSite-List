<tr>
    <td width="130" class="form-table-cell" valign="top">{{ __('RJ Codes or Links') }}</td>
    <td class="form-table-cell">
        <textarea id="rj_list" name="rj_list" class="form-control form-field-long bulk-rj-code-input" rows="5" cols="65"
            required placeholder="RJ01234567&#10;https://www.dlsite.com/.../RJ07654321.html">{{ old('rj_list') }}</textarea>
        @if ($errors->has('rj_list'))
            <div class="text-error">{{ $errors->first('rj_list') }}</div>
        @elseif ($errors->has('rj_codes'))
            <div class="text-error">{{ $errors->first('rj_codes') }}</div>
        @endif
        @foreach ($errors->get('rj_codes.*') as $messages)
            <div class="text-error">{{ implode(' ', $messages) }}</div>
        @endforeach
    </td>
    <td class="form-table-cell form-table-cell--help-icon">
        <i class="fa-solid fa-circle-question" tabindex="0" aria-label="{{ __('About RJ code extraction') }}"
            title="{{ __('Paste RJ codes or links. All occurrences of RJ followed by numbers are imported, e.g. RJ123456.') }}"></i>
    </td>
</tr>
