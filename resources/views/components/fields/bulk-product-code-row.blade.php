<tr>
    <td width="130" class="form-table-cell" valign="top">{{ __('Product Codes or Links') }}</td>
    <td class="form-table-cell">
        <textarea id="product_code_list" name="product_code_list" class="form-control form-field-long bulk-product-code-input"
            rows="5" cols="65" required placeholder="RJ01234567&#10;BJ0123456&#10;VJ0123456">{{ old('product_code_list') }}</textarea>
        @if ($errors->has('product_code_list'))
            <div class="text-error">{{ $errors->first('product_code_list') }}</div>
        @elseif ($errors->has('product_codes'))
            <div class="text-error">{{ $errors->first('product_codes') }}</div>
        @endif
        @foreach ($errors->get('product_codes.*') as $messages)
            <div class="text-error">{{ implode(' ', $messages) }}</div>
        @endforeach
    </td>
    <td class="form-table-cell form-table-cell--help-icon">
        <i class="fa-solid fa-circle-question" tabindex="0" aria-label="{{ __('About product code extraction') }}"
            title="{{ __('Paste product codes or links. All occurrences of RJ, BJ or VJ followed by numbers are imported, e.g. RJ123456, BJ123456 or VJ123456.') }}"></i>
    </td>
</tr>
