<?php

namespace App\Support\DLSite;

final readonly class DLSiteFetchResult
{
    /**
     * @param  list<string>  $failedImages
     */
    public function __construct(
        public DLSiteWorkData $workData,
        public array $failedImages,
    ) {}

    public function hasImageFailures(): bool
    {
        return $this->failedImages !== [];
    }

    public function imageFailureMessage(): ?string
    {
        if (! $this->hasImageFailures()) {
            return null;
        }

        return __('DLSite work data was fetched, but these images could not be downloaded: :images', [
            'images' => implode(', ', $this->failedImages),
        ]);
    }
}
