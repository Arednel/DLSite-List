<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;

enum DlsiteLanguage: string
{
    use ProvidesOptions;

    case Japanese = 'ja';
    case English = 'en';

    public function label(): string
    {
        return __($this->name);
    }

    public function titleLabel(): string
    {
        return __(':language Title', ['language' => $this->label()]);
    }

    public function descriptionLabel(): string
    {
        return __(':language Description', ['language' => $this->label()]);
    }

    public function apiLocale(): ?string
    {
        return match ($this) {
            self::Japanese => null,
            self::English => 'en_US',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @template T
     * @param array<string, T> $rowsByLocale
     * @return list<T>
     */
    public static function orderedRows(array $rowsByLocale): array
    {
        $rows = [];
        foreach (self::values() as $locale) {
            if (array_key_exists($locale, $rowsByLocale)) {
                $rows[] = $rowsByLocale[$locale];
            }
        }

        return $rows;
    }
}
