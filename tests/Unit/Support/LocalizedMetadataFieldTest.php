<?php

namespace Tests\Unit\Support;

use App\Enums\DlsiteLanguage;
use App\Support\LocalizedMetadataField;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LocalizedMetadataFieldTest extends TestCase
{
    #[DataProvider('registeredFields')]
    public function test_registered_fields_round_trip(DlsiteLanguage $language, string $attribute, string $field): void
    {
        $this->assertSame($field, LocalizedMetadataField::format($language, $attribute));

        $parsed = LocalizedMetadataField::parse($field);
        $this->assertNotNull($parsed);
        $this->assertSame($language, $parsed['language']);
        $this->assertSame($attribute, $parsed['attribute']);
    }

    public static function registeredFields(): array
    {
        $cases = [];
        foreach (DlsiteLanguage::cases() as $language) {
            foreach ([LocalizedMetadataField::TITLE, LocalizedMetadataField::DESCRIPTION] as $attribute) {
                $field = $language === DlsiteLanguage::Japanese
                    ? ($attribute === LocalizedMetadataField::TITLE ? 'work_name' : 'description')
                    : "localization:{$language->value}:{$attribute}";
                $cases["{$language->value}-{$attribute}"] = [$language, $attribute, $field];
            }
        }

        return $cases;
    }

    public function test_malformed_or_noncanonical_fields_are_rejected(): void
    {
        foreach (
            [
                '',
                'localization:en',
                'localization:en:other',
                'localization:unknown:title',
                'localization:ja:title',
                'localization:ja:description',
            ] as $field
        ) {
            $this->assertNull(LocalizedMetadataField::parse($field), $field);
        }
    }

    public function test_format_rejects_unknown_attributes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LocalizedMetadataField::format(DlsiteLanguage::English, 'other');
    }
}
