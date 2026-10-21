<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;

enum FetchedTagLanguage: string
{
    use ProvidesOptions;

    case English = 'en';
    case Japanese = 'jp';

    public function label(): string
    {
        return match ($this) {
            self::English => 'English',
            self::Japanese => '日本語',
        };
    }

    public static function forUiLanguage(UiLanguage $language): self
    {
        return match ($language) {
            UiLanguage::English, UiLanguage::TraditionalChinese => self::English,
            UiLanguage::Japanese => self::Japanese,
        };
    }
}
