<?php

namespace App\Support;

use App\Enums\DlsiteLanguage;
use InvalidArgumentException;

final class LocalizedMetadataField
{
    public const TITLE = 'title';

    public const DESCRIPTION = 'description';

    /**
     * @return array{language: DlsiteLanguage, attribute: string}|null
     */
    public static function parse(string $field): ?array
    {
        if ($field === 'work_name') {
            return ['language' => DlsiteLanguage::Japanese, 'attribute' => self::TITLE];
        }

        if ($field === 'description') {
            return ['language' => DlsiteLanguage::Japanese, 'attribute' => self::DESCRIPTION];
        }

        if (preg_match('/\Alocalization:([^:]+):(title|description)\z/', $field, $matches) !== 1) {
            return null;
        }

        $language = DlsiteLanguage::tryFrom($matches[1]);
        if ($language === null || $language === DlsiteLanguage::Japanese) {
            return null;
        }

        return ['language' => $language, 'attribute' => $matches[2]];
    }

    public static function format(DlsiteLanguage $language, string $attribute): string
    {
        if (! in_array($attribute, [self::TITLE, self::DESCRIPTION], true)) {
            throw new InvalidArgumentException('Unsupported localized metadata attribute.');
        }

        if ($language === DlsiteLanguage::Japanese) {
            return $attribute === self::TITLE ? 'work_name' : 'description';
        }

        return "localization:{$language->value}:{$attribute}";
    }
}
