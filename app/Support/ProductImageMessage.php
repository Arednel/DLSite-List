<?php

namespace App\Support;

use App\Support\Localization\ErrorMessage;

final class ProductImageMessage
{
    public static function display(?string $message): ?string
    {
        if ($message === null || $message === '') {
            return $message;
        }

        $prefix = 'Image recovery failed; backups were retained. ';

        if (str_starts_with($message, $prefix)) {
            return ErrorMessage::format($prefix . ':detail', [
                'detail' => ErrorMessage::display(substr($message, strlen($prefix))),
            ]);
        }

        return ErrorMessage::display($message);
    }
}
