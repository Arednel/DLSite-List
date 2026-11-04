@props(['ratingScale', 'value' => null, 'ratingCount' => null])

<tr>
    <td width="130" class="form-table-cell">{{ __('DLsite Rating') }}</td>
    <td class="form-table-cell">
        <div>
            <label for="dlsite_rating">{{ __('Rating') }}</label>
            <input id="dlsite_rating" name="dlsite_rating" class="form-control" type="number" min="0"
                max="{{ $ratingScale->value }}" step="0.01" value="{{ old('dlsite_rating', $value) }}">
        </div>
        <div class="margin-top-8">
            <label for="dlsite_rating_count">{{ __('Vote count') }}</label>
            <input id="dlsite_rating_count" name="dlsite_rating_count" class="form-control" type="number"
                min="0" step="1" value="{{ old('dlsite_rating_count', $ratingCount) }}">
        </div>
    </td>
    <td class="form-table-cell form-table-cell--help-icon">
        <i class="fa-solid fa-circle-question"
            title="{{ __('DLsite rating follows your chosen rating scale (up to 5 or 10) and allows up to two digits after the decimal point.') }}"></i>
    </td>
</tr>
