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

        $prefix = 'image.recovery.failed_with_detail|';

        if (! str_starts_with($message, $prefix)) {
            return ErrorMessage::display($message);
        }

        $detail = trim(substr($message, strlen($prefix)));

        if ($detail === '') {
            return __('errors.image.recovery.failed');
        }

        return __('errors.image.recovery.failed_with_detail', [
            'detail' => ErrorMessage::display($detail),
        ]);
    }
}
