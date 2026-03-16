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
        return $this->value;
    }
}
