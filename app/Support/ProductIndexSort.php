<?php

namespace App\Support;

use App\Enums\ProductIndexSortDirection;
use App\Enums\ProductIndexSortField;

final readonly class ProductIndexSort
{
    public function __construct(
        public ProductIndexSortField $field,
        public ProductIndexSortDirection $direction,
    ) {}
}
