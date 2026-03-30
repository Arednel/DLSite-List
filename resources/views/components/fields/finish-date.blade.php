@props([
    'monthLabels' => [],
    'days' => [],
    'years' => [],
    'monthValue' => '',
    'dayValue' => '',
    'yearValue' => '',
])

<tr>
    <td class="borderClass">Finish Date</td>
    <td class="borderClass">
        Month:
        <select id="add_finish_date_month" name="add[finish_date][month]" class="inputtext">
            <option value=""></option>
            @foreach ($monthLabels as $value => $label)
                <option value="{{ $value }}" @selected((string) old('add.finish_date.month', $monthValue) === (string) $value)>
                    {{ $label }}</option>
            @endforeach
        </select>
        Day:
        <select id="add_finish_date_day" name="add[finish_date][day]" class="inputtext">
            <option value=""></option>
            @foreach ($days as $day)
                <option value="{{ $day }}" @selected((string) old('add.finish_date.day', $dayValue) === (string) $day)>
                    {{ $day }}</option>
            @endforeach
        </select>
        Year:
        <select id="add_finish_date_year" name="add[finish_date][year]" class="inputtext">
            <option value=""></option>
            @foreach ($years as $year)
                <option value="{{ $year }}" @selected((string) old('add.finish_date.year', $yearValue) === (string) $year)>
                    {{ $year }}</option>
            @endforeach
        </select>
        <small>
            <a href="javascript:void(0);" id="end_date_insert_today">Insert Today</a>
        </small>
        @if ($errors->has('add.finish_date'))
            <div class="text-error">
                {{ $errors->first('add.finish_date') }}</div>
        @endif
    </td>
</tr>
