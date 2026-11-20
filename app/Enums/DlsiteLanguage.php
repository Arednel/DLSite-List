<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;

enum DlsiteLanguage: string
{
    use ProvidesOptions;

    case Japanese = 'ja';
    case English = 'en';
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
            self::SimplifiedChinese => 'zh_CN',
            self::TraditionalChinese => 'zh_TW',
            self::Korean => 'ko_KR',
            self::German => 'de_DE',
            self::French => 'fr_FR',
            self::Spanish => 'es_ES',
            self::Italian => 'it_IT',
            self::BrazilianPortuguese => 'pt_BR',
            self::Swedish => 'sv_SE',
            self::Indonesian => 'id_ID',
            self::Thai => 'th_TH',
            self::Vietnamese => 'vi_VN',
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
