<tr>
    <td width="130" class="form-table-cell">{{ $label }}</td>
    <td class="form-table-cell">
        <textarea id="product_format" name="product_format" class="form-control form-field-long" rows="2" cols="65">{{ $value }}</textarea>
        @if ($errors->has('product_format') || $errors->has('product_format.*'))
            <div class="text-error">
                {{ $errors->first('product_format') ?: $errors->first('product_format.*') }}
            </div>
        @endif
    </td>
    <td class="form-table-cell form-table-cell--help-icon">
        <i class="fa-solid fa-circle-question"
            title="{{ __('Comma-separated. You can enter a DLsite Product Format name or code, or type new name to add a custom format. Use double quotes if a format contains a comma, e.g. "Audio, Visual", Audiobook.') }}"></i>
    </td>
</tr>
