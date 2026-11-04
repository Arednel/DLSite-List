<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;
use Illuminate\Support\Str;

enum RatingScale: string
{
    use ProvidesOptions;

    case Ten = '10';
    case Five = '5';

    public function label(): string
    {
        return match ($this) {
            self::Ten => __('Up to 10'),
            self::Five => __('Up to 5'),
        };
    }

    public function dlsiteColumn(): string
    {
        return "dlsite_rating_{$this->value}";
    }

    public function scoreLabel(ProductScore $score): string
    {
        $label = $score->label();

        if ($this === self::Ten) {
            return $label;
        }

        return Str::replaceStart(
            "({$score->value})",
            '(' . $this->displayScore((int) $score->value) . ')',
            $label,
        );
    }

    public function displayScore(?int $score): ?string
    {
        if ($score === null) {
            return null;
        }

        return (string) ($this === self::Five ? $score / 2 : $score);
    }
}
