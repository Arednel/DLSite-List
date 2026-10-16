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

        $catalog = Lang::get('errors');

        return is_array($catalog) && is_string($catalog[$message] ?? null)
            ? $catalog[$message]
            : $message;
    }

    /** @param array<string, scalar|null> $parameters */
    public static function format(string $template, array $parameters): string
    {
        $message = self::display($template);
        $replacements = [];

        foreach ($parameters as $name => $value) {
            $replacements[':' . $name] = (string) ($value ?? '');
        }

        return strtr($message, $replacements);
    }
}
