<?php

namespace App\Support;

use App\Enums\ProductField;
use Illuminate\Support\Arr;

final class ProductFieldLayout
{
    public const SURFACE_INDEX = 'index';

    public const SURFACE_EDIT = 'edit';

    public const SURFACE_FILTER = 'filter';

    public const SURFACE_QUICK_ADD = 'quick_add';

    public const SURFACE_CUSTOM_QUICK_ADD = 'custom_quick_add';

    public const SURFACES = [
        self::SURFACE_INDEX,
        self::SURFACE_EDIT,
        self::SURFACE_FILTER,
        self::SURFACE_QUICK_ADD,
        self::SURFACE_CUSTOM_QUICK_ADD,
    ];

    /**
     * @return list<array{field: string, label: string, visible: bool, editable?: bool, custom_visible?: bool, fetched_visible?: bool}>
     */
    public static function normalize(mixed $layout, string $surface): array
    {
        $surface = in_array($surface, self::SURFACES, true) ? $surface : self::SURFACE_INDEX;
        $submittedRows = is_array($layout) ? $layout : [];
        $allowedFields = self::fieldsForSurface($surface);
        $rowsByField = [];
        $submittedOrder = [];

        foreach ($submittedRows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $field = ProductField::tryFrom((string) ($row['field'] ?? ''));

            if (! $field || ! $field->isAvailableOn($surface) || isset($rowsByField[$field->value])) {
                continue;
            }

            $visible = self::normalizedVisibility($field, $surface, $row);
            $normalized = [
                'field' => $field->value,
                'label' => self::label($field, $surface),
                'visible' => $visible,
            ];
            $normalized += self::lockMetadata($field, $surface);
            $normalized += self::noteMetadata($field, $surface);
            $normalized += self::tagBucketMetadata($field, $surface, $row);

            if ($surface === self::SURFACE_EDIT) {
                $normalized['editable'] = $visible
                    && filter_var($row['editable'] ?? false, FILTER_VALIDATE_BOOL);
            }

            $rowsByField[$field->value] = $normalized;
            $submittedOrder[] = $field->value;
        }

        $orderedRows = [];

        foreach (self::prefixMissingFields($surface) as $field) {
            if (! isset($rowsByField[$field->value])) {
                $orderedRows[$field->value] = self::defaultRow($field, $surface);
            }
        }

        foreach ($submittedOrder as $field) {
            $orderedRows[$field] = $rowsByField[$field];
        }

        foreach ($allowedFields as $field) {
            if (isset($orderedRows[$field->value])) {
                continue;
            }

            $orderedRows[$field->value] = self::defaultRow($field, $surface);
        }

        return array_values($orderedRows);
    }

    public static function storageLayout(mixed $layout, string $surface): array
    {
        return collect(self::normalize($layout, $surface))
            ->map(fn(array $row): array => Arr::except($row, ['label', 'note']))
            ->values()
            ->all();
    }

    private static function defaultRow(ProductField $field, string $surface): array
    {
        $visible = self::defaultVisibility($field, $surface);

        $row = [
            'field' => $field->value,
            'label' => self::label($field, $surface),
            'visible' => $visible,
        ];
        $row += self::lockMetadata($field, $surface);
        $row += self::noteMetadata($field, $surface);
        $row += self::defaultTagBucketMetadata($field, $surface);

        if ($surface === self::SURFACE_EDIT) {
            $row['editable'] = $visible && $field->isEditableByDefault($surface);
        }

        return $row;
    }

    private static function label(ProductField $field, string $surface): string
    {
        if (
            $field === ProductField::Tags
            && in_array($surface, [self::SURFACE_EDIT, self::SURFACE_QUICK_ADD, self::SURFACE_CUSTOM_QUICK_ADD], true)
        ) {
            return __('Custom Tags');
        }

        return $field->label();
    }

    private static function defaultVisibility(ProductField $field, string $surface): bool
    {
        if ($surface === self::SURFACE_INDEX && $field === ProductField::Tags) {
            return true;
        }

        return $field->isVisibilityLocked($surface) || ! $field->isHiddenByDefault($surface);
    }

    private static function normalizedVisibility(ProductField $field, string $surface, array $row): bool
    {
        if ($surface === self::SURFACE_INDEX && $field === ProductField::Tags) {
            return self::normalizeBoolean($row['custom_visible'] ?? null, true)
                || self::normalizeBoolean($row['fetched_visible'] ?? null, true);
        }

        return $field->isVisibilityLocked($surface)
            || filter_var($row['visible'] ?? false, FILTER_VALIDATE_BOOL);
    }

    private static function defaultTagBucketMetadata(ProductField $field, string $surface): array
    {
        if ($surface !== self::SURFACE_INDEX || $field !== ProductField::Tags) {
            return [];
        }

        return [
            'custom_visible' => true,
            'fetched_visible' => true,
        ];
    }

    private static function tagBucketMetadata(ProductField $field, string $surface, array $row): array
    {
        if ($surface !== self::SURFACE_INDEX || $field !== ProductField::Tags) {
            return [];
        }

        return [
            'custom_visible' => self::normalizeBoolean($row['custom_visible'] ?? null, true),
            'fetched_visible' => self::normalizeBoolean($row['fetched_visible'] ?? null, true),
        ];
    }

    private static function normalizeBoolean(mixed $value, bool $default): bool
    {
        return $value === null
            ? $default
            : filter_var($value, FILTER_VALIDATE_BOOL);
    }

    /**
     * @return list<ProductField>
     */
    private static function fieldsForSurface(string $surface): array
    {
        return ProductField::forSurface($surface);
    }

    /**
     * @return list<ProductField>
     */
    private static function prefixMissingFields(string $surface): array
    {
        return ProductField::prefixedWhenMissing($surface);
    }

    private static function lockMetadata(ProductField $field, string $surface): array
    {
        return $field->isVisibilityLocked($surface)
            ? ['visibility_locked' => true]
            : [];
    }

    private static function noteMetadata(ProductField $field, string $surface): array
    {
        $note = $field->layoutNote($surface);

        return $note === null ? [] : ['note' => $note];
    }

    /**
     * @return list<string>
     */
    public static function visibleFields(array $layout): array
    {
        return collect(self::visibleRows($layout))
            ->map(fn(array $visibleRow): string => $visibleRow['field']->value)
            ->values()
            ->all();
    }

    /**
     * @return list<array{field: string, label: string, class: string, sort_field: ?string, contributor_role: ?string}>
     */
    public static function indexColumns(array $layout): array
    {
        return collect(self::visibleRows($layout, self::SURFACE_INDEX))
            ->map(fn(array $visibleRow): array => self::fieldMeta(
                $visibleRow['field'],
                self::SURFACE_INDEX,
            ))
            ->values()
            ->all();
    }

    /**
     * @return list<array{field: string, label: string, editable: bool, contributor_role: ?string}>
     */
    public static function editFields(array $layout): array
    {
        return collect(self::visibleRows($layout, self::SURFACE_EDIT))
            ->map(function (array $visibleRow): array {
                $field = $visibleRow['field'];
                $row = $visibleRow['row'];
                $meta = self::fieldMeta($field, self::SURFACE_EDIT);

                return [
                    'field' => $meta['field'],
                    'label' => $meta['label'],
                    'editable' => (bool) ($row['editable'] ?? false),
                    'contributor_role' => $meta['contributor_role'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{field: string, label: string, class: string}>
     */
    public static function filterFields(array $layout): array
    {
        return collect(self::visibleRows($layout, self::SURFACE_FILTER))
            ->map(fn(array $visibleRow): array => Arr::only(
                self::fieldMeta($visibleRow['field'], self::SURFACE_FILTER),
                ['field', 'label', 'class'],
            ))
            ->values()
            ->all();
    }

    /**
     * @return list<array{field: string, label: string, class: string, contributor_role: ?string}>
     */
    public static function quickAddFields(array $layout): array
    {
        return self::createFields($layout, self::SURFACE_QUICK_ADD);
    }

    /**
     * @return list<array{field: string, label: string, class: string, contributor_role: ?string}>
     */
    public static function customQuickAddFields(array $layout): array
    {
        return self::createFields($layout, self::SURFACE_CUSTOM_QUICK_ADD);
    }

    /**
     * @return list<array{field: string, label: string, class: string}>
     */
    private static function createFields(array $layout, string $surface): array
    {
        return collect(self::visibleRows($layout, $surface))
            ->map(fn(array $visibleRow): array => Arr::only(
                self::fieldMeta($visibleRow['field'], $surface),
                ['field', 'label', 'class', 'contributor_role'],
            ))
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function editableFields(array $layout): array
    {
        return collect(self::visibleRows($layout, self::SURFACE_EDIT))
            ->filter(fn(array $visibleRow): bool => (bool) ($visibleRow['row']['editable'] ?? false))
            ->map(fn(array $visibleRow): string => $visibleRow['field']->value)
            ->values()
            ->all();
    }

    public static function visible(array $layout, ProductField|string $field): bool
    {
        $field = $field instanceof ProductField ? $field->value : $field;

        return (bool) data_get(Arr::first($layout, fn(array $row): bool => $row['field'] === $field), 'visible', false);
    }

    public static function editable(array $layout, ProductField|string $field): bool
    {
        $field = $field instanceof ProductField ? $field->value : $field;

        return (bool) data_get(Arr::first($layout, fn(array $row): bool => $row['field'] === $field), 'editable', false);
    }

    public static function fetchedTagsEditable(array $layout): bool
    {
        return self::editable($layout, ProductField::FetchedTags);
    }

    public static function visibleIndexTagBuckets(array $layout): array
    {
        $row = Arr::first($layout, fn(array $row): bool => $row['field'] === ProductField::Tags->value);

        return [
            'custom' => (bool) data_get($row, 'visible', false)
                && (bool) data_get($row, 'custom_visible', false),
            'fetched' => (bool) data_get($row, 'visible', false)
                && (bool) data_get($row, 'fetched_visible', false),
        ];
    }

    /**
     * @return list<array{field: ProductField, row: array<string, mixed>}>
     */
    private static function visibleRows(array $layout, ?string $surface = null): array
    {
        $allowedFieldValues = $surface === null
            ? null
            : array_map(fn(ProductField $field): string => $field->value, self::fieldsForSurface($surface));

        return collect($layout)
            ->map(function (mixed $row) use ($allowedFieldValues): ?array {
                if (! is_array($row) || ! (bool) ($row['visible'] ?? false)) {
                    return null;
                }

                $field = ProductField::tryFrom((string) ($row['field'] ?? ''));

                if (! $field || ($allowedFieldValues !== null && ! in_array($field->value, $allowedFieldValues, true))) {
                    return null;
                }

                return ['field' => $field, 'row' => $row];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array{field: string, label: string, class: string, sort_field: ?string, contributor_role: ?string}
     */
    private static function fieldMeta(ProductField $field, string $surface): array
    {
        return [
            'field' => $field->value,
            'label' => self::label($field, $surface),
            'class' => str_replace('_', '-', $field->value),
            'sort_field' => $field->sortField()?->value,
            'contributor_role' => $field->contributorRole()?->value,
        ];
    }
}
