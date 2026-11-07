<?php

namespace App\Support;

use Illuminate\Support\Facades\Lang;

final class ProductImageMessage
{
    public static function display(?string $message): ?string
    {
        if ($message === null || $message === '') {
            return $message;
        }

        $recoveryError = 'image.recovery.failed_with_detail|';

        if (str_starts_with($message, $recoveryError)) {
            $detail = trim(substr($message, strlen($recoveryError)));
            if ($detail === '') {
                return __('errors.image.recovery.failed');
            }

            $detailKey = 'errors.' . $detail;

            return __('errors.image.recovery.failed_with_detail', [
                'detail' => Lang::has($detailKey) ? __($detailKey) : $detail,
            ]);
        }

        return __('errors.' . $message);
    }
}
