<?php

namespace App\Support;

final readonly class ProductIndexContributorRow
{
    public function __construct(
        public string $name,
        public ?string $makerId,
        public string $indexUrl,
    ) {}
}
