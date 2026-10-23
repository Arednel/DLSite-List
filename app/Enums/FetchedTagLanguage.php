<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;

enum FetchedTagLanguage: string
{
    use ProvidesOptions;

    case English = 'en';
    case Japanese = 'jp';
    case TraditionalChinese = 'zh_TW';

    public function label(): string
    {
        return match ($this) {
            self::English => 'English',
            self::Japanese => '日本語',
            self::TraditionalChinese => '繁體中文',
        };
    }

    /**
     * Works imported before Traditional Chinese tags were fetched only have Japanese and English rows,
     * so Traditional Chinese falls back per work instead of hiding every fetched tag.
     *
     * @return list<self>
     */
    public function fallbackOrder(): array
    {
        return match ($this) {
            self::TraditionalChinese => [self::TraditionalChinese, self::English, self::Japanese],
            default => [$this],
        };
    }

    public static function forUiLanguage(UiLanguage $language): self
    {
        return match ($language) {
            UiLanguage::English => self::English,
            UiLanguage::Japanese => self::Japanese,
            UiLanguage::TraditionalChinese => self::TraditionalChinese,
        };
    }
}
