@props([
    'monthLabels' => [],
    'days' => [],
    'years' => [],
    'monthValue' => '',
    'dayValue' => '',
    'yearValue' => '',
])

<tr>
    <td class="borderClass">Start Date</td>
    <td class="borderClass">
        Month:
        <select id="add_start_date_month" name="add[start_date][month]" class="inputtext">
            <option value=""></option>
            @foreach ($monthLabels as $value => $label)
                <option value="{{ $value }}" @selected((string) old('add.start_date.month', $monthValue) === (string) $value)>
                    {{ $label }}</option>
            @endforeach
        </select>
        Day:
        <select id="add_start_date_day" name="add[start_date][day]" class="inputtext">
            <option value=""></option>
            @foreach ($days as $day)
                <option value="{{ $day }}" @selected((string) old('add.start_date.day', $dayValue) === (string) $day)>
                    {{ $day }}</option>
            @endforeach
        </select>
        Year:
        <select id="add_start_date_year" name="add[start_date][year]" class="inputtext">
            <option value=""></option>
            @foreach ($years as $year)
                <option value="{{ $year }}" @selected((string) old('add.start_date.year', $yearValue) === (string) $year)>
                    {{ $year }}</option>
            @endforeach
        </select>
        <small>
            <a href="javascript:void(0);" id="start_date_insert_today">Insert Today</a>
        </small>
        @if ($errors->has('add.start_date'))
            <div class="text-error">
                {{ $errors->first('add.start_date') }}</div>
        @endif
    </td>
</tr>
