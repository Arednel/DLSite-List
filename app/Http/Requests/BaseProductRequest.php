<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

abstract class BaseProductRequest extends FormRequest
{
    protected function commonRules(): array
    {
        return [
            'progress' => ['nullable', 'string'],
            'score' => ['nullable', 'integer'],
            'series' => ['nullable', 'string'],
            'genre_custom' => ['nullable', 'array'],
            'work_name_english' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'start_date' => ['nullable', 'array'],
            'end_date' => ['nullable', 'array'],
            'num_re_listen_times' => ['nullable', 'integer', 'min:0'],
            're_listen_value' => ['nullable', 'integer', 'between:1,5'],
            'priority' => ['nullable', 'integer', 'between:0,2'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'genre_custom' => $this->normalizeGenreCustom($this->input('genre_custom')),
            'start_date' => $this->normalizeDateParts($this->input('add.start_date')),
            'end_date' => $this->normalizeDateParts($this->input('add.finish_date')),
            'num_re_listen_times' => $this->normalizeNullableInt($this->input('add.num_re_listen_times')),
            're_listen_value' => $this->normalizeNullableInt($this->input('add.re_listen_value')),
            'priority' => $this->normalizeNullableInt($this->input('add.priority')),
        ]);
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateDateParts($validator, 'start_date', 'add.start_date', 'Start date is invalid.');
            $this->validateDateParts($validator, 'end_date', 'add.finish_date', 'Finish date is invalid.');
            $this->validateDateOrder($validator);
        });
    }

    /**
     * Validate date parts for a given key (month/day/year).
     */
    protected function validateDateParts($validator, string $sourceKey, string $errorKey, string $message): void
    {
        $date = $this->input($sourceKey);

        if (!is_array($date)) {
            return;
        }

        $month = $date['month'] ?? null;
        $day = $date['day'] ?? null;
        $year = $date['year'] ?? null;

        $hasMonth = $this->hasValue($month);
        $hasDay = $this->hasValue($day);
        $hasYear = $this->hasValue($year);

        if (!($hasMonth || $hasDay || $hasYear)) {
            return;
        }

        if ($hasMonth && ((int) $month < 1 || (int) $month > 12)) {
            $validator->errors()->add($errorKey, $message);
            return;
        }

        if ($hasDay && ((int) $day < 1 || (int) $day > 31)) {
            $validator->errors()->add($errorKey, $message);
            return;
        }

        if ($hasYear && ((int) $year < 1900 || (int) $year > 2100)) {
            $validator->errors()->add($errorKey, $message);
            return;
        }

        if ($hasMonth && $hasDay && $hasYear && !checkdate((int) $month, (int) $day, (int) $year)) {
            $validator->errors()->add($errorKey, $message);
        }
    }

    protected function validateDateOrder($validator): void
    {
        $startDate = $this->fullDateFromInput('start_date');
        $endDate = $this->fullDateFromInput('end_date');

        if ($startDate === null || $endDate === null) {
            return;
        }

        if ($startDate->gt($endDate)) {
            $validator->errors()->add('add.finish_date', 'Finish date must be on or after start date.');
        }
    }

    protected function fullDateFromInput(string $key): ?Carbon
    {
        $date = $this->input($key);

        if (!is_array($date)) {
            return null;
        }

        $month = $date['month'] ?? null;
        $day = $date['day'] ?? null;
        $year = $date['year'] ?? null;

        if (!$this->hasValue($month) || !$this->hasValue($day) || !$this->hasValue($year)) {
            return null;
        }

        if (!checkdate((int) $month, (int) $day, (int) $year)) {
            return null;
        }

        return Carbon::create((int) $year, (int) $month, (int) $day)->startOfDay();
    }

    protected function hasValue($value): bool
    {
        return $value !== null && $value !== '';
    }

    protected function normalizeGenreCustom(?string $value): array
    {
        if ($value === null) {
            return [];
        }

        $value = trim($value);
        if ($value === '') {
            return [];
        }

        // Use CSV parsing so quoted tags can safely contain commas.
        $parts = array_map('trim', str_getcsv($value, ',', '"', '\\'));

        return array_values(array_filter($parts, fn($part) => $part !== ''));
    }

    protected function normalizeDateParts($date): ?array
    {
        if (!is_array($date)) {
            return null;
        }

        $parts = [
            'month' => $date['month'] ?? null,
            'day' => $date['day'] ?? null,
            'year' => $date['year'] ?? null,
        ];

        foreach ($parts as $value) {
            if ($value !== null && $value !== '') {
                return $parts;
            }
        }

        return null;
    }

    protected function normalizeNullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
