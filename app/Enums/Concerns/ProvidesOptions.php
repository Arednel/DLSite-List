<?php

namespace App\Enums\Concerns;

trait ProvidesOptions
{
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn($case): array => [(string) $case->value => $case->label()])
            ->all();
    }
}
