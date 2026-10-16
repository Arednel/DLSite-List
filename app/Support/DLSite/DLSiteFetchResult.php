<?php

namespace App\Support\DLSite;

use App\Support\Localization\ErrorMessage;

final readonly class DLSiteFetchResult
{
    public const IMAGE_FAILURE_MESSAGE = 'DLSite data was fetched, but these images could not be downloaded: :images';

    /**
     * @param  list<string>  $failedImages
     */
    public function __construct(
        public DLSiteWorkData $workData,
        public array $failedImages,
    ) {}

    public static function displayImageFailureMessage(?string $message): ?string
    {
        if ($message === null || $message === '') {
            return $message;
        }

        $prefix = str_replace(':images', '', self::IMAGE_FAILURE_MESSAGE);

        if (! str_starts_with($message, $prefix)) {
            return $message;
        }

        return ErrorMessage::format(self::IMAGE_FAILURE_MESSAGE, [
            'images' => substr($message, strlen($prefix)),
        ]);
    }

    public function hasImageFailures(): bool
    {
        return $this->failedImages !== [];
    }

    public function imageFailureMessage(): ?string
    {
        if (! $this->hasImageFailures()) {
            return null;
        }

        return str_replace(':images', implode(', ', $this->failedImages), self::IMAGE_FAILURE_MESSAGE);
    }
}
