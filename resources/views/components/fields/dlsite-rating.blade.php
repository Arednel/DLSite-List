@props([
    'rating' => null,
    'count' => null,
])

{{-- Read-only: the rating comes from DLsite and is refreshed by Refetch. --}}
<tr class="dlsite-rating-row">
    <td width="130" class="form-table-cell" valign="top">{{ __('DLsite rating') }}</td>
    <td class="form-table-cell">
        <span class="dlsite-rating">{{ trans_choice('★:rating (:count rating)|★:rating (:count ratings)', $count, [
            'rating' => number_format((float) $rating, 2),
            'count' => number_format((int) $count),
        ]) }}</span>
    </td>
    <td class="form-table-cell form-table-cell--long-spacer" aria-hidden="true"></td>
</tr>
