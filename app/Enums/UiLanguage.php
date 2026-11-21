<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;
use Illuminate\Support\Facades\App;

enum UiLanguage: string
{
    use ProvidesOptions;

    case English = 'en';
    case Japanese = 'ja';
    case SimplifiedChinese = 'zh_CN';
    case TraditionalChinese = 'zh_TW';
    case Korean = 'ko';
    case German = 'de';
    case French = 'fr';
    case Spanish = 'es';
    case Italian = 'it';
    case BrazilianPortuguese = 'pt_BR';
    case Swedish = 'sv';
    case Indonesian = 'id';
    case Thai = 'th';
    case Vietnamese = 'vi';
    case Belarusian = 'be';
    case Dutch = 'nl';
    case Filipino = 'fil';
    case Polish = 'pl';
    case Romanian = 'ro';
    case Russian = 'ru';
    case Turkish = 'tr';
    case Ukrainian = 'uk';

    public function label(): string
    {
        return match ($this) {
            self::English => 'English',
            self::Japanese => '日本語',
            self::SimplifiedChinese => '简体中文',
            self::TraditionalChinese => '繁體中文',
            self::Korean => '한국어',
            self::German => 'Deutsch',
            self::French => 'Français',
            self::Spanish => 'Español',
            self::Italian => 'Italiano',
            self::BrazilianPortuguese => 'Português',
            self::Swedish => 'Svenska',
            self::Indonesian => 'Bahasa Indonesia',
            self::Thai => 'ภาษาไทย',
            self::Vietnamese => 'Tiếng Việt',
            self::Belarusian => 'Беларуская',
            self::Dutch => 'Nederlands',
            self::Filipino => 'Filipino',
            self::Polish => 'Polski',
            self::Romanian => 'Română',
            self::Russian => 'Русский',
            self::Turkish => 'Türkçe',
            self::Ukrainian => 'Українська',
        };
    }

    public function dlsiteLanguage(): ?DlsiteLanguage
    {
        return DlsiteLanguage::tryFrom($this->value);
    }

    public static function current(): self
    {
        return self::tryFrom(App::currentLocale()) ?? self::English;
    }
}
