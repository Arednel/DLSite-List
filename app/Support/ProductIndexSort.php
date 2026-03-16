<?php

namespace App\Support;

use App\Enums\ProductIndexSortDirection;
use App\Enums\ProductIndexSortField;

final readonly class ProductIndexSort
{
    public function __construct(
        public ProductIndexSortField $field,
        public ProductIndexSortDirection $direction,
    ) {
    }

    public function toInput(string $prefix): array
    {
        return [
            "sort_{$prefix}_field" => $this->field->value,
            "sort_{$prefix}_direction" => $this->direction->value,
        ];
    }
}
