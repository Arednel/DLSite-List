<?php

namespace App\Support;

final class PartialDateFormatter
{
    public static function format(?array $date): ?string
    {
        return collect([
            __('Year') => data_get($date, 'year'),
            __('Month') => data_get($date, 'month'),
            __('Day') => data_get($date, 'day'),
        ])
            ->filter(fn(mixed $value): bool => filled($value))
            ->map(fn(mixed $value, string $label): string => "{$label}: {$value}")
            ->join(', ') ?: null;
    }
}
