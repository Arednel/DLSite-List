<?php

namespace App\Support\Localization;

use Illuminate\Support\Facades\Lang;

final class ErrorMessage
{
    public static function display(?string $message): ?string
    {
        if ($message === null || $message === '') {
            return $message;
        }

        $key = 'errors.' . $message;
        $translated = Lang::get($key);

        return is_string($translated) && $translated !== $key
            ? $translated
            : $message;
    }
}
