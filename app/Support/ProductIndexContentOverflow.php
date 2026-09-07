<?php

namespace App\Support;

final class ProductIndexContentOverflow
{
    public const DEFAULT_HEIGHT = '80px';

    public const HEIGHT_PATTERN = '/^(?=.*[1-9])(?:\d+(?:\.\d+)?|\.\d+)(?:px|rem|em|%|vw|vh|vmin|vmax|svh|lvh|dvh)$/';

    public const DEFAULTS = [
        'inline_notes' => ['enabled' => false, 'height' => self::DEFAULT_HEIGHT],
        'notes_column' => ['enabled' => false, 'height' => self::DEFAULT_HEIGHT],
        'tags' => ['enabled' => false, 'height' => self::DEFAULT_HEIGHT],
    ];

    /**
     * @return array{
     *     inline_notes: array{enabled: bool, height: string},
     *     notes_column: array{enabled: bool, height: string},
     *     tags: array{enabled: bool, height: string}
     * }
     */
    public static function normalize(mixed $settings): array
    {
        $settings = is_array($settings) ? $settings : [];
        $normalized = self::DEFAULTS;

        foreach ($normalized as $target => $defaults) {
            $targetSettings = is_array($settings[$target] ?? null)
                ? $settings[$target]
                : [];
            $height = is_string($targetSettings['height'] ?? null)
                ? strtolower(trim($targetSettings['height']))
                : '';
            $enabled = filter_var(
                $targetSettings['enabled'] ?? null,
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE,
            ) ?? false;

            $normalized[$target] = [
                'enabled' => $enabled,
                'height' => $enabled && preg_match(self::HEIGHT_PATTERN, $height) === 1
                    ? $height
                    : $defaults['height'],
            ];
        }

        return $normalized;
    }
}
