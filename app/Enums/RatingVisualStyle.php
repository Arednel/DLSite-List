<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;

enum RatingVisualStyle: string
{
    use ProvidesOptions;

    case Simple = 'simple';
    case DLSite = 'dlsite';
    case ExtendedDLSite = 'extended_dlsite';

    public function label(): string
    {
        return match ($this) {
            self::Simple => __('Simple'),
            self::DLSite => __('DLsite'),
            self::ExtendedDLSite => __('Extended DLsite'),
        };
    }

    public function showsStars(): bool
    {
        return $this !== self::Simple;
    }

    public function showsVoteCount(): bool
    {
        return $this === self::ExtendedDLSite;
    }
}
