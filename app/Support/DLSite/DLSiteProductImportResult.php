<?php

namespace App\Support\DLSite;

use App\Models\Product;

final readonly class DLSiteProductImportResult
{
    public function __construct(
        public Product $product,
        public ?string $warning = null,
    ) {}
}
