<?php

namespace App\Support\DLSite;

use App\Enums\ProductContributorRole;
use App\Enums\ProductField;
use App\Http\Requests\BaseProductRequest;
use App\Models\Option;
use App\Support\ProductFieldLayout;
use Illuminate\Support\Arr;

final readonly class DLSiteProductImportInput
{
    /**
     * @param array<string, mixed> $values
     * @param list<string> $visibleFields
     * @param array<string, bool> $submitted
     */
    public function __construct(
        public array $values,
        public array $visibleFields,
        public array $submitted,
        public bool $autoSeriesFromTitleName,
    ) {}

    public static function fromRequest(
        BaseProductRequest $request,
        array $validated,
        array $layout,
    ): self {
        $submissionKeys = [
            'work_name',
            'work_name_english',
            'age_category',
            'circle',
            'maker_id',
            'description',
            'description_english',
            'notes',
            'series',
            'score',
            'progress',
            'genre_custom',
            'add.start_date',
            'add.finish_date',
            'add.num_re_listen_times',
            'add.re_listen_value',
            'add.priority',
            ...collect(ProductContributorRole::cases())
                ->reject(fn(ProductContributorRole $role): bool => $role === ProductContributorRole::Circle)
                ->map(fn(ProductContributorRole $role): string => $role->value)
                ->all(),
        ];

        $submitted = [];
        foreach ($submissionKeys as $key) {
            $submitted[$key] = $request->wasSubmitted($key);
        }

        return new self(
            values: Arr::except($validated, ['id', 'rj_list', 'rj_codes']),
            visibleFields: ProductFieldLayout::visibleFields($layout),
            submitted: $submitted,
            autoSeriesFromTitleName: Option::autoSeriesFromTitleName(),
        );
    }

    /**
     * @return array{
     *     values: array<string, mixed>,
     *     visible_fields: list<string>,
     *     submitted: array<string, bool>,
     *     auto_series_from_title_name: bool
     * }
     */
    public function toSnapshot(): array
    {
        return [
            'values' => $this->values,
            'visible_fields' => $this->visibleFields,
            'submitted' => $this->submitted,
            'auto_series_from_title_name' => $this->autoSeriesFromTitleName,
        ];
    }

    public static function fromSnapshot(array $snapshot): self
    {
        return new self(
            values: is_array($snapshot['values'] ?? null) ? $snapshot['values'] : [],
            visibleFields: array_values(array_filter(
                is_array($snapshot['visible_fields'] ?? null) ? $snapshot['visible_fields'] : [],
                'is_string',
            )),
            submitted: is_array($snapshot['submitted'] ?? null) ? $snapshot['submitted'] : [],
            autoSeriesFromTitleName: (bool) ($snapshot['auto_series_from_title_name'] ?? false),
        );
    }

    public function value(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    private function wasSubmitted(string $key): bool
    {
        return (bool) ($this->submitted[$key] ?? false);
    }

    private function wasAnySubmitted(string|array $keys): bool
    {
        foreach ((array) $keys as $key) {
            if ($this->wasSubmitted($key)) {
                return true;
            }
        }

        return false;
    }

    public function fieldVisible(ProductField $field): bool
    {
        return in_array($field->value, $this->visibleFields, true);
    }

    public function fieldSubmitted(ProductField $field, string|array $keys): bool
    {
        return $this->fieldVisible($field) && $this->wasAnySubmitted($keys);
    }
}
