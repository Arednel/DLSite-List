<?php

namespace App\Support;

final readonly class ProductIndexSettings
{
    /**
     * @param  list<array<string, mixed>>  $indexFieldLayout
     * @param  list<array{field: string, label: string, class: string, sort_field: ?string, contributor_role: ?string}>  $indexColumns
     * @param  list<string>  $visibleIndexFields
     * @param  list<array<string, mixed>>  $filterFieldLayout
     * @param  list<array{field: string, label: string, class: string}>  $filterFields
     * @param  list<array{field: string, label: string, visible: bool}>  $indexSortFieldLayout
     * @param  array<string, string>  $indexSortFieldOptions
     * @param  array{mode: string, custom: string}  $tableWidth
     * @param  array{index: bool, tag_library: bool, autocomplete: bool, edit_readonly: bool, refetch: bool}  $tagColorSurfaces
     */
    public function __construct(
        public int|string $perPage,
        public array $indexFieldLayout,
        public array $indexColumns,
        public array $visibleIndexFields,
        public array $filterFieldLayout,
        public array $filterFields,
        public array $indexSortFieldLayout,
        public array $indexSortFieldOptions,
        public array $tableWidth,
        public string $tableWidthCss,
        public bool $searchHiddenDescriptionsEnabled,
        public bool $indexGroupOrderingEnabled,
        public array $tagColorSurfaces,
    ) {}
}
