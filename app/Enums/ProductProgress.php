<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;

enum ProductProgress: string
{
    use ProvidesOptions;

    case Listening = 'Listening';
    case Completed = 'Completed';
    case PlanToListen = 'Plan to Listen';

    public function label(): string
    {
        return match ($this) {
            self::Listening => __('Listening'),
            self::Completed => __('Completed'),
            self::PlanToListen => __('Plan to Listen'),
        };
    }
}
