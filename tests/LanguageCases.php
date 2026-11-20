<?php

namespace Tests;

use App\Enums\DlsiteLanguage;
use App\Enums\UiLanguage;

final class LanguageCases
{
    public static function ui(): iterable
    {
        foreach (UiLanguage::cases() as $language) {
            yield $language->value => [$language];
        }
    }

    public static function metadata(): iterable
    {
        foreach (DlsiteLanguage::cases() as $language) {
            yield $language->value => [$language];
        }
    }
}
