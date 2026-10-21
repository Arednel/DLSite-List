<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;
use Illuminate\Support\Facades\App;

enum UiLanguage: string
{
    use ProvidesOptions;

    case English = 'en';
    case Japanese = 'ja';
    case TraditionalChinese = 'zh_TW';

    public function label(): string
    {
        return match ($this) {
            self::English => 'English',
            self::Japanese => '日本語',
            self::TraditionalChinese => '繁體中文',
        };
    }

    public static function current(): self
    {
        return self::tryFrom(App::currentLocale()) ?? self::English;
    }
}
