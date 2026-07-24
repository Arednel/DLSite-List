<?php

namespace App\Support;

use Illuminate\Support\Collection;

final readonly class ProductIndexRow
{
    /**
     * @param  Collection<string, Collection<int, ProductIndexContributorRow>>  $contributors
     */
    public function __construct(
        public string $id,
        public string $workName,
        public ?string $workNameEnglish,
        public ?string $notes,
        public ?string $progress,
        public ?string $workImage,
        public ?int $score,
        public ?string $series,
        public ?string $ageCategory,
        public ?string $circle,
        public ?string $makerId,
        public ?string $description,
        public ?string $descriptionEnglish,
        public Collection $contributors,
        public string $dlsiteWorkUrl,
        public string $editUrl,
        public ?string $seriesUrl,
        public ?string $circleUrl,
    ) {}
}
