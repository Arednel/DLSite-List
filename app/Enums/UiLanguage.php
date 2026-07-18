<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;
use App\Models\Genre;
use Illuminate\Support\Facades\App;

enum UiLanguage: string
{
    use ProvidesOptions;

    case English = 'en';
    case Japanese = 'ja';

    public function label(): string
    {
        return match ($this) {
            self::English => 'English',
            self::Japanese => '日本語',
        };
    }

    public static function current(): self
    {
        return self::tryFrom(App::currentLocale()) ?? self::English;
    }

    public function fetchedTagLanguage(): string
    {
        return match ($this) {
            self::English => Genre::LANGUAGE_ENGLISH,
            self::Japanese => Genre::LANGUAGE_JAPANESE,
        };
    }

    public function fetchedTagCode(): string
    {
        return match ($this) {
            self::English => 'EN',
            self::Japanese => 'JP',
        };
    }
}
