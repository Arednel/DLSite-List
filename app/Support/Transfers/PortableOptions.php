<?php

namespace App\Support\Transfers;

use App\Enums\AutocompleteOrder;
use App\Enums\ProductIndexSortField;
use App\Enums\UiLanguage;
use App\Models\Option;
use App\Support\ProductFieldLayout;
use App\Support\ProductIndexContentOverflow;
use BackedEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final class PortableOptions
{
    private const PORTABLE_KEYS = [
        Option::UI_LANGUAGE,
        Option::INDEX_PER_PAGE,
        Option::INDEX_SEARCH_HIDDEN_DESCRIPTIONS_ENABLED,
        Option::INDEX_IMAGE_VIEWER_ENABLED,
        Option::OPTIONAL_PRODUCT_STATUSES,
        Option::TAG_AUTOCOMPLETE_ORDER,
        Option::SERIES_AUTOCOMPLETE_ORDER,
        Option::AUTO_SERIES_FROM_TITLE_NAME,
        Option::DLSITE_AGE_APPROPRIATE_LINKS_ENABLED,
        Option::PRODUCT_FORM_THEME,
        Option::AUTHENTICATION_PAGE_THEME,
        Option::PRODUCT_FORM_MODAL_ENABLED,
        Option::PRODUCT_FORM_MODAL_COMPLETION_ACTION,
        Option::TAG_LIBRARY_TAGS_EXPANDED_BY_DEFAULT,
        Option::TAG_LIBRARY_INDEX_GROUP_ORDERING_ENABLED,
        Option::TAG_COLOR_SURFACES,
        Option::INDEX_TABLE_WIDTH,
        Option::INDEX_CONTENT_OVERFLOW,
        Option::INDEX_SORT_FIELD_LAYOUT,
        Option::INDEX_FIELD_LAYOUT,
        Option::EDIT_FIELD_LAYOUT,
        Option::FILTER_FIELD_LAYOUT,
        Option::QUICK_ADD_FIELD_LAYOUT,
        Option::BULK_IMPORT_FIELD_LAYOUT,
        Option::CUSTOM_QUICK_ADD_FIELD_LAYOUT,
    ];

    public function defaults(): array
    {
        return Arr::only(Option::defaults(), self::PORTABLE_KEYS);
    }

    public function layouts(): array
    {
        return [
            Option::INDEX_FIELD_LAYOUT => 'index',
            Option::EDIT_FIELD_LAYOUT => 'edit',
            Option::FILTER_FIELD_LAYOUT => 'filter',
            Option::QUICK_ADD_FIELD_LAYOUT => 'quick_add',
            Option::BULK_IMPORT_FIELD_LAYOUT => 'bulk_import',
            Option::CUSTOM_QUICK_ADD_FIELD_LAYOUT => 'custom_quick_add',
        ];
    }

    public function all(): array
    {
        $values = [];
        foreach ($this->defaults() as $key => $default) {
            $values[$key] = $this->current($key);
        }

        return $values;
    }

    public function current(string $key, bool $lock = false): mixed
    {
        if ($lock) {
            return Option::withLockedValue($key, fn() => $this->current($key));
        }
        if (! array_key_exists($key, $this->defaults())) {
            throw new InvalidArgumentException('Unknown or non-portable option: ' . $key);
        }
        $value = Option::{Str::camel($key)}();
        if ($value instanceof BackedEnum) {
            return $value->value;
        }
        if (isset($this->layouts()[$key])) {
            return ProductFieldLayout::storageLayout($value, $this->layouts()[$key]);
        }

        return $key === Option::INDEX_SORT_FIELD_LAYOUT ? ProductIndexSortField::storageLayout($value) : $value;
    }

    public function validate(string $key, mixed $value): void
    {
        $defaults = $this->defaults();
        if (! array_key_exists($key, $defaults)) {
            throw new InvalidArgumentException('Unknown or non-portable option: ' . $key);
        }
        Validator::make(['value' => $value], $this->rules($key, $value))->validate();

        if ($key === Option::INDEX_TABLE_WIDTH && Option::normalizeIndexTableWidth(array_replace($defaults[$key], $value)) !== array_replace($defaults[$key], $value)) {
            throw new InvalidArgumentException('Invalid table width.');
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function rules(string $key, mixed $value): array
    {
        return match ($key) {
            Option::UI_LANGUAGE => ['value' => ['required', Rule::enum(UiLanguage::class)]],
            Option::INDEX_PER_PAGE => [
                'value' => [
                    'required',
                    Rule::when(
                        $value === Option::INDEX_PER_PAGE_UNLIMITED,
                        Rule::in([Option::INDEX_PER_PAGE_UNLIMITED]),
                        ['integer:strict', 'min:1'],
                    ),
                ],
            ],
            Option::INDEX_SEARCH_HIDDEN_DESCRIPTIONS_ENABLED,
            Option::INDEX_IMAGE_VIEWER_ENABLED,
            Option::AUTO_SERIES_FROM_TITLE_NAME,
            Option::DLSITE_AGE_APPROPRIATE_LINKS_ENABLED,
            Option::PRODUCT_FORM_MODAL_ENABLED,
            Option::TAG_LIBRARY_TAGS_EXPANDED_BY_DEFAULT,
            Option::TAG_LIBRARY_INDEX_GROUP_ORDERING_ENABLED => ['value' => ['required', 'boolean:strict']],
            Option::TAG_AUTOCOMPLETE_ORDER,
            Option::SERIES_AUTOCOMPLETE_ORDER => ['value' => ['required', Rule::enum(AutocompleteOrder::class)]],
            Option::PRODUCT_FORM_THEME => ['value' => ['required', Rule::in(array_keys(Option::PRODUCT_FORM_THEME_OPTIONS))]],
            Option::AUTHENTICATION_PAGE_THEME => ['value' => ['required', Rule::in(array_keys(Option::AUTHENTICATION_PAGE_THEME_OPTIONS))]],
            Option::PRODUCT_FORM_MODAL_COMPLETION_ACTION => ['value' => ['required', Rule::in(array_keys(Option::PRODUCT_FORM_MODAL_COMPLETION_OPTIONS))]],
            Option::OPTIONAL_PRODUCT_STATUSES => [
                'value' => ['required', 'array:' . implode(',', array_keys(Option::DEFAULT_OPTIONAL_PRODUCT_STATUSES))],
                'value.*' => ['sometimes', 'boolean:strict'],
            ],
            Option::TAG_COLOR_SURFACES => [
                'value' => ['required', 'array:' . implode(',', array_keys(Option::DEFAULT_TAG_COLOR_SURFACES))],
                'value.*' => ['sometimes', 'boolean:strict'],
            ],
            Option::INDEX_TABLE_WIDTH => [
                'value' => ['required', 'array:mode,custom'],
                'value.mode' => ['sometimes', 'string', Rule::in(array_keys(Option::INDEX_TABLE_WIDTH_OPTIONS))],
                'value.custom' => ['sometimes', 'string'],
            ],
            Option::INDEX_CONTENT_OVERFLOW => [
                'value' => ['required', 'array:' . implode(',', array_keys(ProductIndexContentOverflow::DEFAULTS))],
                'value.*' => ['sometimes', 'array:enabled,height'],
                'value.*.enabled' => ['sometimes', 'boolean:strict'],
                'value.*.height' => ['sometimes', 'string', 'regex:' . ProductIndexContentOverflow::HEIGHT_PATTERN],
            ],
            default => $this->layoutRules($key),
        };
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function layoutRules(string $key): array
    {
        $allowed = array_column(Option::defaultFor($key), null, 'field');

        return [
            'value' => ['required', 'array'],
            'value.*' => [
                'required',
                Rule::forEach(static function (mixed $row) use ($allowed): array {
                    $field = is_array($row) ? $row['field'] ?? null : null;
                    $shape = is_string($field) ? ($allowed[$field] ?? null) : null;

                    return $shape === null ? ['array'] : ['array:' . implode(',', array_keys($shape))];
                }),
            ],
            'value.*.field' => ['required', 'string', Rule::in(array_keys($allowed)), 'distinct:strict'],
            'value.*.visible' => ['sometimes', 'boolean:strict'],
            'value.*.visibility_locked' => ['sometimes', 'boolean:strict'],
            'value.*.editable' => ['sometimes', 'boolean:strict'],
            'value.*.notes_visible' => ['sometimes', 'boolean:strict'],
            'value.*.custom_visible' => ['sometimes', 'boolean:strict'],
            'value.*.fetched_visible' => ['sometimes', 'boolean:strict'],
        ];
    }

    public function combine(string $key, mixed $value, string $decision, mixed $current = null): mixed
    {
        $base = $decision === 'overwrite' ? Option::defaultFor($key) : ($current ?? $this->current($key));
        if (! is_array($value)) {
            return $value;
        }
        if (isset($this->layouts()[$key]) || $key === Option::INDEX_SORT_FIELD_LAYOUT) {
            $byField = array_column($base, null, 'field');
            $rows = [];
            foreach ($value as $row) {
                $rows[] = array_replace($byField[$row['field']], $row);
                unset($byField[$row['field']]);
            }

            return [...$rows, ...array_values($byField)];
        }

        return array_replace_recursive($base, $value);
    }

    public function apply(string $key, mixed $value): void
    {
        $this->validate($key, $value);
        Option::{'set' . Str::studly($key)}($value);
    }
}
