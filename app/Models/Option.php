<?php

namespace App\Models;

use App\Enums\AutocompleteOrder;
use App\Enums\ProductIndexSortField;
use App\Support\ProductFieldLayout;
use App\Support\ProductIndexSettings;
use Illuminate\Database\Eloquent\Model;

class Option extends Model
{
    public const INDEX_PER_PAGE = 'index_per_page';

    public const INDEX_SEARCH_HIDDEN_DESCRIPTIONS_ENABLED = 'index_search_hidden_descriptions_enabled';

    public const TAG_AUTOCOMPLETE_ORDER = 'tag_autocomplete_order';

    public const SERIES_AUTOCOMPLETE_ORDER = 'series_autocomplete_order';

    public const AUTO_SERIES_FROM_TITLE_NAME = 'auto_series_from_title_name';

    public const PRODUCT_FORM_THEME = 'product_form_theme';

    public const TAG_LIBRARY_TAGS_EXPANDED_BY_DEFAULT = 'tag_library_tags_expanded_by_default';

    public const TAG_LIBRARY_INDEX_GROUP_ORDERING_ENABLED = 'tag_library_index_group_ordering_enabled';

    public const TAG_COLOR_SURFACES = 'tag_color_surfaces';

    public const TAG_COLOR_SURFACE_INDEX = 'index';

    public const TAG_COLOR_SURFACE_TAG_LIBRARY = 'tag_library';

    public const TAG_COLOR_SURFACE_AUTOCOMPLETE = 'autocomplete';

    public const TAG_COLOR_SURFACE_EDIT_READONLY = 'edit_readonly';

    public const TAG_COLOR_SURFACE_REFETCH = 'refetch';

    public const INDEX_FIELD_LAYOUT = 'index_field_layout';

    public const EDIT_FIELD_LAYOUT = 'edit_field_layout';

    public const FILTER_FIELD_LAYOUT = 'filter_field_layout';

    public const QUICK_ADD_FIELD_LAYOUT = 'quick_add_field_layout';

    public const CUSTOM_QUICK_ADD_FIELD_LAYOUT = 'custom_quick_add_field_layout';

    public const INDEX_SORT_FIELD_LAYOUT = 'index_sort_field_layout';

    public const INDEX_TABLE_WIDTH = 'index_table_width';

    public const INDEX_PER_PAGE_UNLIMITED = 'unlimited';

    public const INDEX_TABLE_WIDTH_DEFAULT = 'default';

    public const INDEX_TABLE_WIDTH_WIDE = 'wide';

    public const INDEX_TABLE_WIDTH_FULL = 'full';

    public const INDEX_TABLE_WIDTH_CUSTOM = 'custom';

    public const PRODUCT_FORM_THEME_CHERRY = 'cherry';

    public const PRODUCT_FORM_THEME_BLACK = 'black';

    public const DEFAULT_INDEX_PER_PAGE = 100;

    public const FIXED_INDEX_PER_PAGE_OPTIONS = [
        10,
        25,
        50,
        100,
        250,
        500,
        1000,
    ];

    public const INDEX_TABLE_WIDTH_OPTIONS = [
        self::INDEX_TABLE_WIDTH_DEFAULT => 'Default',
        self::INDEX_TABLE_WIDTH_WIDE => 'Wide',
        self::INDEX_TABLE_WIDTH_FULL => 'Full',
        self::INDEX_TABLE_WIDTH_CUSTOM => 'Custom',
    ];

    public const PRODUCT_FORM_THEME_OPTIONS = [
        self::PRODUCT_FORM_THEME_CHERRY => 'Cherry',
        self::PRODUCT_FORM_THEME_BLACK => 'Black',
    ];

    public const DEFAULT_TAG_COLOR_SURFACES = [
        self::TAG_COLOR_SURFACE_INDEX => true,
        self::TAG_COLOR_SURFACE_TAG_LIBRARY => true,
        self::TAG_COLOR_SURFACE_AUTOCOMPLETE => false,
        self::TAG_COLOR_SURFACE_EDIT_READONLY => false,
        self::TAG_COLOR_SURFACE_REFETCH => false,
    ];

    private const FIELD_LAYOUT_OPTIONS = [
        self::INDEX_FIELD_LAYOUT => ProductFieldLayout::SURFACE_INDEX,
        self::EDIT_FIELD_LAYOUT => ProductFieldLayout::SURFACE_EDIT,
        self::FILTER_FIELD_LAYOUT => ProductFieldLayout::SURFACE_FILTER,
        self::QUICK_ADD_FIELD_LAYOUT => ProductFieldLayout::SURFACE_QUICK_ADD,
        self::CUSTOM_QUICK_ADD_FIELD_LAYOUT => ProductFieldLayout::SURFACE_CUSTOM_QUICK_ADD,
    ];

    protected $fillable = [
        'key',
        'value',
    ];

    public static function indexPerPage(): int|string
    {
        $option = self::query()
            ->where('key', self::INDEX_PER_PAGE)
            ->first(['value']);

        return self::normalizeIndexPerPage($option?->value ?? self::DEFAULT_INDEX_PER_PAGE);
    }

    public static function setIndexPerPage(int|string $value): void
    {
        self::query()->updateOrCreate(
            ['key' => self::INDEX_PER_PAGE],
            ['value' => (string) self::normalizeIndexPerPage($value)],
        );
    }

    public static function resetIndexPerPageToDefault(): void
    {
        self::setIndexPerPage(self::DEFAULT_INDEX_PER_PAGE);
    }

    public static function indexSearchHiddenDescriptionsEnabled(): bool
    {
        return self::booleanValueFor(self::INDEX_SEARCH_HIDDEN_DESCRIPTIONS_ENABLED, false);
    }

    public static function setIndexSearchHiddenDescriptionsEnabled(bool $enabled): void
    {
        self::setBooleanValue(self::INDEX_SEARCH_HIDDEN_DESCRIPTIONS_ENABLED, $enabled);
    }

    public static function resetIndexSearchHiddenDescriptionsEnabledToDefault(): void
    {
        self::setIndexSearchHiddenDescriptionsEnabled(false);
    }

    public static function tagAutocompleteOrder(): AutocompleteOrder
    {
        return self::autocompleteOrder(self::TAG_AUTOCOMPLETE_ORDER);
    }

    public static function setTagAutocompleteOrder(AutocompleteOrder|string $order): void
    {
        self::setAutocompleteOrder(self::TAG_AUTOCOMPLETE_ORDER, $order);
    }

    public static function seriesAutocompleteOrder(): AutocompleteOrder
    {
        return self::autocompleteOrder(self::SERIES_AUTOCOMPLETE_ORDER);
    }

    public static function setSeriesAutocompleteOrder(AutocompleteOrder|string $order): void
    {
        self::setAutocompleteOrder(self::SERIES_AUTOCOMPLETE_ORDER, $order);
    }

    public static function resetAutocompleteToDefault(): void
    {
        self::setTagAutocompleteOrder(AutocompleteOrder::Usage);
        self::setSeriesAutocompleteOrder(AutocompleteOrder::Usage);
    }

    public static function autoSeriesFromTitleName(): bool
    {
        return self::booleanValueFor(self::AUTO_SERIES_FROM_TITLE_NAME, true);
    }

    public static function setAutoSeriesFromTitleName(bool $enabled): void
    {
        self::setBooleanValue(self::AUTO_SERIES_FROM_TITLE_NAME, $enabled);
    }

    public static function resetAutoSeriesFromTitleNameToDefault(): void
    {
        self::setAutoSeriesFromTitleName(true);
    }

    public static function productFormTheme(): string
    {
        return self::normalizeProductFormTheme(self::valueFor(self::PRODUCT_FORM_THEME));
    }

    public static function setProductFormTheme(string $theme): void
    {
        self::setValue(self::PRODUCT_FORM_THEME, self::normalizeProductFormTheme($theme));
    }

    public static function resetProductFormThemeToDefault(): void
    {
        self::setProductFormTheme(self::PRODUCT_FORM_THEME_BLACK);
    }

    /**
     * @return array<string, string>
     */
    public static function productFormThemeOptions(): array
    {
        return self::PRODUCT_FORM_THEME_OPTIONS;
    }

    public static function tagLibraryTagsExpandedByDefault(): bool
    {
        return self::booleanValueFor(self::TAG_LIBRARY_TAGS_EXPANDED_BY_DEFAULT, false);
    }

    public static function setTagLibraryTagsExpandedByDefault(bool $expanded): void
    {
        self::setBooleanValue(self::TAG_LIBRARY_TAGS_EXPANDED_BY_DEFAULT, $expanded);
    }

    public static function resetTagLibraryTagsExpandedByDefaultToDefault(): void
    {
        self::setTagLibraryTagsExpandedByDefault(false);
    }

    public static function tagLibraryIndexGroupOrderingEnabled(): bool
    {
        return self::booleanValueFor(self::TAG_LIBRARY_INDEX_GROUP_ORDERING_ENABLED, false);
    }

    public static function setTagLibraryIndexGroupOrderingEnabled(bool $enabled): void
    {
        self::setBooleanValue(self::TAG_LIBRARY_INDEX_GROUP_ORDERING_ENABLED, $enabled);
    }

    public static function resetTagLibraryIndexGroupOrderingEnabledToDefault(): void
    {
        self::setTagLibraryIndexGroupOrderingEnabled(false);
    }

    /**
     * @return array{index: bool, tag_library: bool, autocomplete: bool, edit_readonly: bool, refetch: bool}
     */
    public static function tagColorSurfaces(): array
    {
        return self::normalizeTagColorSurfaces(self::jsonFromValue(self::valueFor(self::TAG_COLOR_SURFACES)));
    }

    public static function setTagColorSurfaces(array $surfaces): void
    {
        self::setValue(
            self::TAG_COLOR_SURFACES,
            json_encode(self::normalizeTagColorSurfaces($surfaces), JSON_THROW_ON_ERROR),
        );
    }

    public static function resetTagColorSurfacesToDefault(): void
    {
        self::setTagColorSurfaces(self::DEFAULT_TAG_COLOR_SURFACES);
    }

    public static function tagColorSurfaceEnabled(string $surface): bool
    {
        return self::tagColorSurfaces()[$surface] ?? false;
    }

    /**
     * @return list<array{field: string, label: string, visible: bool}>
     */
    public static function indexFieldLayout(): array
    {
        return self::fieldLayout(self::INDEX_FIELD_LAYOUT, ProductFieldLayout::SURFACE_INDEX);
    }

    /**
     * @return list<array{field: string, label: string, visible: bool, editable: bool}>
     */
    public static function editFieldLayout(): array
    {
        return self::fieldLayout(
            self::EDIT_FIELD_LAYOUT,
            ProductFieldLayout::SURFACE_EDIT,
        );
    }

    /**
     * @return list<array{field: string, label: string, visible: bool}>
     */
    public static function filterFieldLayout(): array
    {
        return self::fieldLayout(self::FILTER_FIELD_LAYOUT, ProductFieldLayout::SURFACE_FILTER);
    }

    /**
     * @return list<array{field: string, label: string, visible: bool}>
     */
    public static function quickAddFieldLayout(): array
    {
        return self::fieldLayout(self::QUICK_ADD_FIELD_LAYOUT, ProductFieldLayout::SURFACE_QUICK_ADD);
    }

    /**
     * @return list<array{field: string, label: string, visible: bool}>
     */
    public static function customQuickAddFieldLayout(): array
    {
        return self::fieldLayout(
            self::CUSTOM_QUICK_ADD_FIELD_LAYOUT,
            ProductFieldLayout::SURFACE_CUSTOM_QUICK_ADD,
        );
    }

    public static function setIndexFieldLayout(array $layout): void
    {
        self::setFieldLayout(self::INDEX_FIELD_LAYOUT, ProductFieldLayout::SURFACE_INDEX, $layout);
    }

    public static function setEditFieldLayout(array $layout): void
    {
        self::setFieldLayout(
            self::EDIT_FIELD_LAYOUT,
            ProductFieldLayout::SURFACE_EDIT,
            $layout,
        );
    }

    public static function setFilterFieldLayout(array $layout): void
    {
        self::setFieldLayout(self::FILTER_FIELD_LAYOUT, ProductFieldLayout::SURFACE_FILTER, $layout);
    }

    public static function setQuickAddFieldLayout(array $layout): void
    {
        self::setFieldLayout(self::QUICK_ADD_FIELD_LAYOUT, ProductFieldLayout::SURFACE_QUICK_ADD, $layout);
    }

    public static function setCustomQuickAddFieldLayout(array $layout): void
    {
        self::setFieldLayout(
            self::CUSTOM_QUICK_ADD_FIELD_LAYOUT,
            ProductFieldLayout::SURFACE_CUSTOM_QUICK_ADD,
            $layout,
        );
    }

    public static function resetFieldLayoutsToDefault(): void
    {
        foreach (self::FIELD_LAYOUT_OPTIONS as $key => $surface) {
            self::setFieldLayout($key, $surface, []);
        }
    }

    /**
     * @return list<array{field: string, label: string, visible: bool}>
     */
    public static function indexSortFieldLayout(): array
    {
        return self::indexSortFieldLayoutFromValue(self::valueFor(self::INDEX_SORT_FIELD_LAYOUT));
    }

    public static function setIndexSortFieldLayout(array $layout): void
    {
        self::setValue(
            self::INDEX_SORT_FIELD_LAYOUT,
            json_encode(ProductIndexSortField::storageLayout($layout), JSON_THROW_ON_ERROR),
        );
    }

    public static function resetIndexSortFieldLayoutToDefault(): void
    {
        self::setIndexSortFieldLayout([]);
    }

    /**
     * @return array{mode: string, custom: string}
     */
    public static function indexTableWidth(): array
    {
        return self::normalizeIndexTableWidth(
            self::jsonFromValue(self::valueFor(self::INDEX_TABLE_WIDTH))
        );
    }

    public static function setIndexTableWidth(array $width): void
    {
        self::setValue(
            self::INDEX_TABLE_WIDTH,
            json_encode(self::normalizeIndexTableWidth($width), JSON_THROW_ON_ERROR),
        );
    }

    public static function resetIndexTableWidthToDefault(): void
    {
        self::setIndexTableWidth([
            'mode' => self::INDEX_TABLE_WIDTH_DEFAULT,
            'custom' => '',
        ]);
    }

    public static function resetVisibleSettingsToDefault(): void
    {
        self::resetIndexPerPageToDefault();
        self::resetIndexSearchHiddenDescriptionsEnabledToDefault();
        self::resetIndexTableWidthToDefault();
        self::resetFieldLayoutsToDefault();
        self::resetIndexSortFieldLayoutToDefault();
        self::resetAutoSeriesFromTitleNameToDefault();
        self::resetProductFormThemeToDefault();
        self::resetTagLibraryTagsExpandedByDefaultToDefault();
        self::resetTagLibraryIndexGroupOrderingEnabledToDefault();
        self::resetTagColorSurfacesToDefault();
        self::resetAutocompleteToDefault();
    }

    public static function indexTableWidthCss(): string
    {
        return self::indexTableWidthCssFrom(self::indexTableWidth());
    }

    public static function productIndexSettings(): ProductIndexSettings
    {
        $values = self::query()
            ->whereIn('key', [
                self::INDEX_PER_PAGE,
                self::INDEX_SEARCH_HIDDEN_DESCRIPTIONS_ENABLED,
                self::INDEX_FIELD_LAYOUT,
                self::FILTER_FIELD_LAYOUT,
                self::INDEX_SORT_FIELD_LAYOUT,
                self::INDEX_TABLE_WIDTH,
                self::TAG_LIBRARY_INDEX_GROUP_ORDERING_ENABLED,
                self::TAG_COLOR_SURFACES,
            ])
            ->pluck('value', 'key');

        $indexFieldLayout = self::fieldLayoutFromValue(
            $values->get(self::INDEX_FIELD_LAYOUT),
            ProductFieldLayout::SURFACE_INDEX,
        );
        $indexColumns = ProductFieldLayout::indexColumns($indexFieldLayout);
        $filterFieldLayout = self::fieldLayoutFromValue(
            $values->get(self::FILTER_FIELD_LAYOUT),
            ProductFieldLayout::SURFACE_FILTER,
        );
        $indexSortFieldLayout = self::indexSortFieldLayoutFromValue(
            $values->get(self::INDEX_SORT_FIELD_LAYOUT),
        );
        $tableWidth = self::normalizeIndexTableWidth(self::jsonFromValue($values->get(self::INDEX_TABLE_WIDTH)));

        return new ProductIndexSettings(
            perPage: self::normalizeIndexPerPage($values->get(self::INDEX_PER_PAGE, self::DEFAULT_INDEX_PER_PAGE)),
            indexFieldLayout: $indexFieldLayout,
            indexColumns: $indexColumns,
            visibleIndexFields: array_column($indexColumns, 'field'),
            filterFieldLayout: $filterFieldLayout,
            filterFields: ProductFieldLayout::filterFields($filterFieldLayout),
            indexSortFieldLayout: $indexSortFieldLayout,
            indexSortFieldOptions: ProductIndexSortField::optionsFromLayout($indexSortFieldLayout),
            tableWidth: $tableWidth,
            tableWidthCss: self::indexTableWidthCssFrom($tableWidth),
            searchHiddenDescriptionsEnabled: self::normalizeBoolean(
                $values->get(self::INDEX_SEARCH_HIDDEN_DESCRIPTIONS_ENABLED),
                false,
            ),
            indexGroupOrderingEnabled: self::normalizeBoolean(
                $values->get(self::TAG_LIBRARY_INDEX_GROUP_ORDERING_ENABLED),
                false,
            ),
            tagColorSurfaces: self::normalizeTagColorSurfaces(
                self::jsonFromValue($values->get(self::TAG_COLOR_SURFACES)),
            ),
        );
    }

    public static function normalizeIndexPerPage(mixed $value): int|string
    {
        if ($value === self::INDEX_PER_PAGE_UNLIMITED) {
            return self::INDEX_PER_PAGE_UNLIMITED;
        }

        if (is_numeric($value) && (int) $value > 0) {
            return (int) $value;
        }

        return self::DEFAULT_INDEX_PER_PAGE;
    }

    /**
     * @return array<int, string>
     */
    public static function fixedIndexPerPageOptions(): array
    {
        return collect(self::FIXED_INDEX_PER_PAGE_OPTIONS)
            ->mapWithKeys(fn(int $value): array => [$value => (string) $value])
            ->all();
    }

    private static function autocompleteOrder(string $key): AutocompleteOrder
    {
        return self::normalizeAutocompleteOrder(
            self::valueFor($key)
        );
    }

    private static function setAutocompleteOrder(string $key, AutocompleteOrder|string $order): void
    {
        self::setValue($key, self::normalizeAutocompleteOrder($order)->value);
    }

    private static function booleanValueFor(string $key, bool $default): bool
    {
        return self::normalizeBoolean(self::valueFor($key), $default);
    }

    private static function setBooleanValue(string $key, bool $enabled): void
    {
        self::setValue($key, $enabled ? '1' : '0');
    }

    private static function normalizeBoolean(mixed $value, bool $default): bool
    {
        return $value === null
            ? $default
            : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array{index: bool, tag_library: bool, autocomplete: bool, edit_readonly: bool, refetch: bool}
     */
    private static function normalizeTagColorSurfaces(mixed $surfaces): array
    {
        if (! is_array($surfaces)) {
            return self::DEFAULT_TAG_COLOR_SURFACES;
        }

        return collect(self::DEFAULT_TAG_COLOR_SURFACES)
            ->mapWithKeys(fn(bool $default, string $surface): array => [
                $surface => self::normalizeBoolean($surfaces[$surface] ?? null, $default),
            ])
            ->all();
    }

    private static function normalizeAutocompleteOrder(AutocompleteOrder|string|null $order): AutocompleteOrder
    {
        if ($order instanceof AutocompleteOrder) {
            return $order;
        }

        return AutocompleteOrder::tryFrom((string) $order) ?? AutocompleteOrder::Usage;
    }

    private static function normalizeProductFormTheme(?string $theme): string
    {
        return array_key_exists((string) $theme, self::PRODUCT_FORM_THEME_OPTIONS)
            ? (string) $theme
            : self::PRODUCT_FORM_THEME_BLACK;
    }

    private static function valueFor(string $key): ?string
    {
        return self::query()->where('key', $key)->value('value');
    }

    private static function setValue(string $key, ?string $value): void
    {
        self::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }

    private static function jsonFromValue(?string $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    private static function fieldLayout(string $key, string $surface): array
    {
        return self::fieldLayoutFromValue(self::valueFor($key), $surface);
    }

    private static function fieldLayoutFromValue(?string $value, string $surface): array
    {
        return ProductFieldLayout::normalize(self::jsonFromValue($value), $surface);
    }

    private static function setFieldLayout(
        string $key,
        string $surface,
        array $layout,
    ): void {
        self::setValue(
            $key,
            json_encode(ProductFieldLayout::storageLayout($layout, $surface), JSON_THROW_ON_ERROR),
        );
    }

    private static function indexSortFieldLayoutFromValue(?string $value): array
    {
        return ProductIndexSortField::normalizeLayout(self::jsonFromValue($value));
    }

    /**
     * @return array{mode: string, custom: string}
     */
    public static function normalizeIndexTableWidth(mixed $width): array
    {
        $mode = is_array($width) ? (string) ($width['mode'] ?? self::INDEX_TABLE_WIDTH_DEFAULT) : (string) $width;
        $custom = is_array($width) ? trim((string) ($width['custom'] ?? '')) : '';

        if (! array_key_exists($mode, self::INDEX_TABLE_WIDTH_OPTIONS)) {
            $mode = self::INDEX_TABLE_WIDTH_DEFAULT;
        }

        if ($mode !== self::INDEX_TABLE_WIDTH_CUSTOM) {
            $custom = '';
        }

        if ($mode === self::INDEX_TABLE_WIDTH_CUSTOM && ! preg_match('/^\d+(\.\d+)?(px|rem|em|%|vw)$/', $custom)) {
            $mode = self::INDEX_TABLE_WIDTH_DEFAULT;
            $custom = '';
        }

        return [
            'mode' => $mode,
            'custom' => $custom,
        ];
    }

    /**
     * @param  array{mode: string, custom: string}  $width
     */
    private static function indexTableWidthCssFrom(array $width): string
    {
        return match ($width['mode']) {
            self::INDEX_TABLE_WIDTH_WIDE => '1400px',
            self::INDEX_TABLE_WIDTH_FULL => '100%',
            self::INDEX_TABLE_WIDTH_CUSTOM => $width['custom'] !== '' ? $width['custom'] : '1024px',
            default => '1024px',
        };
    }
}
