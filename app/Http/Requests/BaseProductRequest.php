<?php

namespace App\Http\Requests;

use App\Enums\ProductPriority;
use App\Enums\ProductProgress;
use App\Enums\ProductReListenValue;
use App\Enums\ProductScore;
use App\Enums\ProductAgeCategory;
use App\Enums\ProductContributorRole;
use App\Support\TagInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class BaseProductRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    protected array $originalInputKeys = [];

    protected array $originalInput = [];

    protected function normalizeRjIdInput(): void
    {
        $id = $this->input('id');

        if (is_string($id) && preg_match('/RJ\d+/i', $id, $matches)) {
            $this->merge([
                'id' => strtoupper($matches[0]),
            ]);
        }
    }

    protected function commonRules(): array
    {
        return [
            'progress' => ['nullable', Rule::enum(ProductProgress::class)],
            'score' => ['nullable', Rule::enum(ProductScore::class)],
            'series' => ['nullable', 'string'],
            'age_category' => ['nullable', Rule::enum(ProductAgeCategory::class)],
            'circle' => ['nullable', 'string'],
            'maker_id' => ['nullable', 'string'],
            ProductContributorRole::Scenario->value => ['nullable', 'array'],
            ProductContributorRole::VoiceActor->value => ['nullable', 'array'],
            ProductContributorRole::Illustration->value => ['nullable', 'array'],
            ProductContributorRole::Author->value => ['nullable', 'array'],
            'genre_custom' => ['nullable', 'array'],
            'work_name_english' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'description_english' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'start_date' => ['nullable', 'array'],
            'end_date' => ['nullable', 'array'],
            'num_re_listen_times' => ['nullable', 'integer', 'min:0'],
            're_listen_value' => ['nullable', Rule::enum(ProductReListenValue::class)],
            'priority' => ['nullable', Rule::enum(ProductPriority::class)],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->originalInput = $this->all();
        $this->originalInputKeys = array_keys($this->all());

        $this->merge([
            'genre_custom' => $this->normalizeGenreList($this->input('genre_custom')),
            ProductContributorRole::Scenario->value => $this->normalizeGenreList($this->input(ProductContributorRole::Scenario->value)),
            ProductContributorRole::VoiceActor->value => $this->normalizeGenreList($this->input(ProductContributorRole::VoiceActor->value)),
            ProductContributorRole::Illustration->value => $this->normalizeGenreList($this->input(ProductContributorRole::Illustration->value)),
            ProductContributorRole::Author->value => $this->normalizeGenreList($this->input(ProductContributorRole::Author->value)),
            'start_date' => $this->normalizeDateParts($this->input('add.start_date')),
            'end_date' => $this->normalizeDateParts($this->input('add.finish_date')),
            'num_re_listen_times' => $this->normalizeNullableInt($this->input('add.num_re_listen_times')),
            're_listen_value' => $this->normalizeNullableInt($this->input('add.re_listen_value')),
            'priority' => $this->normalizeNullableInt($this->input('add.priority')),
        ]);
    }

    public function wasSubmitted(string $key): bool
    {
        if (in_array($key, $this->originalInputKeys, true) || Arr::has($this->originalInput, $key)) {
            return true;
        }

        $prefix = $key . '.';

        return collect(Arr::dot($this->originalInput))
            ->keys()
            ->contains(fn(string $inputKey): bool => str_starts_with($inputKey, $prefix));
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateDateParts($validator, 'start_date', 'add.start_date', 'Start date is invalid.');
                $this->validateDateParts($validator, 'end_date', 'add.finish_date', 'Finish date is invalid.');
                $this->validateDateOrder($validator);
            },
        ];
    }

    /**
     * Validate date parts for a given key (month/day/year).
     */
    protected function validateDateParts(Validator $validator, string $sourceKey, string $errorKey, string $message): void
    {
        $date = $this->input($sourceKey);

        if (!is_array($date)) {
            return;
        }

        $month = $date['month'] ?? null;
        $day = $date['day'] ?? null;
        $year = $date['year'] ?? null;

        $hasMonth = filled($month);
        $hasDay = filled($day);
        $hasYear = filled($year);

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

    protected function validateDateOrder(Validator $validator): void
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

        if (! filled($month) || ! filled($day) || ! filled($year)) {
            return null;
        }

        if (!checkdate((int) $month, (int) $day, (int) $year)) {
            return null;
        }

        return Carbon::create((int) $year, (int) $month, (int) $day)->startOfDay();
    }

    protected function normalizeGenreList(?string $value): array
    {
        return TagInput::parse($value);
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

    public function wasAnySubmitted(string|array $keys): bool
    {
        return collect((array) $keys)
            ->contains(fn(string $key): bool => $this->wasSubmitted($key));
    }
}
