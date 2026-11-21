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

    public static function translatedUi(): iterable
    {
        foreach (UiLanguage::cases() as $language) {
            if ($language !== UiLanguage::English) {
                yield $language->value => [$language];
            }
        }
    }

    public static function uiOnly(): iterable
    {
        foreach (UiLanguage::cases() as $language) {
            if ($language->dlsiteLanguage() === null) {
                yield $language->value => [$language];
            }
        }
    }

    public static function metadata(): iterable
    {
        foreach (DlsiteLanguage::cases() as $language) {
            yield $language->value => [$language];
        }
    }
}
