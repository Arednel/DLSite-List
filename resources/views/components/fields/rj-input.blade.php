@props([
    'value' => '',
    'showDlsiteFetchStatus' => false,
])

<tr>
    <td width="130" class="form-table-cell" valign="top">{{ __('RJ Code or Link') }}</td>
    <td class="form-table-cell">
        <strong>
            <input id="id" name="id" class="form-control" size="65" required
                value="{{ old('id', $value) }}">
        </strong>
        @if ($errors->has('id'))
            <div class="text-error">{{ $errors->first('id') }}</div>
        @endif
        @if ($showDlsiteFetchStatus)
            <div class="dlsite-fetch-status" data-dlsite-fetch-status role="status" aria-live="polite" hidden>
                {{ __('Work is being fetched…') }}
            </div>
        @endif
    </td>
</tr>
