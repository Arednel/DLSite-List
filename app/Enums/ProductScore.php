<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;
use App\Support\ContentTerminology;

enum ProductScore: string
{
    use ProvidesOptions;

        // Explicit ordering from 10 to 1
    case Ten = '10';
    case Nine = '9';
    case Eight = '8';
    case Seven = '7';
    case Six = '6';
    case Five = '5';
    case Four = '4';
    case Three = '3';
    case Two = '2';
    case One = '1';

    public function label(): string
    {
        return app(ContentTerminology::class)->score($this);
    }
}
