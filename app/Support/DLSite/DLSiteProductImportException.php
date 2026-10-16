<?php

namespace App\Support\DLSite;

use App\Support\Localization\ErrorMessage;
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
            $message = 'DLSite import failed.';
        }

        return ErrorMessage::display($message);
    }
}
