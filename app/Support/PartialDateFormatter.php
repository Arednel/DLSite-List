<?php

namespace App\Support;

final class PartialDateFormatter
{
    public static function format(?array $date): ?string
    {
        return collect([
            'Year' => data_get($date, 'year'),
            'Month' => data_get($date, 'month'),
            'Day' => data_get($date, 'day'),
        ])
            ->filter(fn(mixed $value): bool => filled($value))
            ->map(fn(mixed $value, string $label): string => "{$label}: {$value}")
            ->join(', ') ?: null;
    }
}
