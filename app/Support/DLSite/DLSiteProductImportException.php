<?php

namespace App\Support\DLSite;

use RuntimeException;

class DLSiteProductImportException extends RuntimeException
{
    public static function fromFetchFailure(RuntimeException $exception): self
    {
        return new self(trim($exception->getMessage()), (int) $exception->getCode(), $exception);
    }

    public function isUnavailableWork(): bool
    {
        return $this->getPrevious() instanceof DLSiteWorkUnavailableException;
    }

    public function displayMessage(): string
    {
        $message = trim($this->getMessage());

        if ($message === '') {
            return __('DLSite import failed.');
        }

        return match ($message) {
            'GeoBlocked DLSite work',
            'This work was deleted or could not be found on DLSite',
            'This work could not be found on DLSite' => __($message),
            default => $message,
        };
    }
}
