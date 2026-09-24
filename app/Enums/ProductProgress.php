<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;
use App\Support\ContentTerminology;

enum ProductProgress: string
{
    use ProvidesOptions;

    case Listening = 'Listening';
    case Completed = 'Completed';
    case OnHold = 'On Hold';
    case Dropped = 'Dropped';
    case PlanToListen = 'Plan to Listen';

    public function label(): string
    {
        return app(ContentTerminology::class)->progress($this);
    }

    /**
     * @param  array{on_hold?: bool, dropped?: bool}  $optionalStatuses
     * @return array<string, string>
     */
    public static function visibleOptions(
        array $optionalStatuses = [],
        ?string $currentProgress = null,
    ): array {
        $options = self::options();

        if (
            ! ($optionalStatuses['on_hold'] ?? false)
            && $currentProgress !== self::OnHold->value
        ) {
            unset($options[self::OnHold->value]);
        }

        if (
            ! ($optionalStatuses['dropped'] ?? false)
            && $currentProgress !== self::Dropped->value
        ) {
            unset($options[self::Dropped->value]);
        }

        return $options;
    }
}
