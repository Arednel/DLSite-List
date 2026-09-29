<?php

namespace Tests\Unit\Enums;

use App\Enums\ProductFormat;
use Tests\TestCase;

class ProductFormatTest extends TestCase
{
    public function test_edit_input_round_trips_every_canonical_format_in_supported_locales(): void
    {
        foreach (['en', 'ja'] as $locale) {
            app()->setLocale($locale);

            foreach (ProductFormat::cases() as $format) {
                $this->assertRoundTrip([$format->value], $locale);

                $prefix = $format === ProductFormat::Adventure
                    ? ProductFormat::RolePlaying->value
                    : ProductFormat::Adventure->value;

                $this->assertRoundTrip([$prefix, $format->value], $locale);
            }
        }
    }

    public function test_edit_input_uses_labels_when_safe_and_codes_only_when_needed(): void
    {
        app()->setLocale('en');

        $this->assertSame(
            'Adventure, Voice, Music, Animation',
            ProductFormat::inputValue(['ADV', 'SND', 'MS2', 'MV2']),
        );
        $this->assertSame('MS2', ProductFormat::inputValue(['MS2']));
        $this->assertSame('Adventure, MUS', ProductFormat::inputValue(['ADV', 'MUS']));

        app()->setLocale('ja');
        $this->assertSame('MV2', ProductFormat::inputValue(['MV2']));
    }

    public function test_main_and_additional_formats_can_coexist_and_round_trip_through_input(): void
    {
        $formats = ['SOU', 'SND', 'MUS', 'MS2', 'MOV', 'MV2'];

        $this->assertSame(
            $formats,
            ProductFormat::normalizeStoredValues($formats),
        );

        $this->assertSame(
            $formats,
            ProductFormat::normalizeInput(ProductFormat::inputValue($formats)),
        );
    }

    public function test_dlsite_values_suppress_only_additional_formats_with_mirrored_mains(): void
    {
        $this->assertSame(
            ['SOU', 'MUS', 'MOV', 'ADV'],
            ProductFormat::normalizeDlsiteValues(['SOU', 'SND', 'MUS', 'MS2', 'MOV', 'MV2', 'ADV']),
        );

        $this->assertSame(
            ['ADV', 'SND', 'MS2', 'MV2'],
            ProductFormat::normalizeDlsiteValues(['ADV', 'SND', 'MS2', 'MV2']),
        );
    }

    public function test_custom_values_are_separate_from_dlsite_managed_values(): void
    {
        $values = ['SOU', 'SND', 'MS2', 'custom:Audiobook'];

        $this->assertSame(['SOU', 'MS2'], ProductFormat::normalizeDlsiteValues($values));
        $this->assertSame(['custom:Audiobook'], ProductFormat::customStoredValues($values));
    }

    public function test_search_matches_exact_codes_but_not_partial_code_fragments(): void
    {
        foreach (ProductFormat::cases() as $format) {
            $this->assertSame(
                [$format->value],
                ProductFormat::matchingCodesForSearch(mb_strtolower($format->value)),
            );
        }

        $this->assertSame([], ProductFormat::matchingCodesForSearch('sn'));
    }

    public function test_search_partially_matches_reconstructed_labels(): void
    {
        $this->assertContains('SLN', ProductFormat::matchingCodesForSearch('Sim'));
        $this->assertContains('SOU', ProductFormat::matchingCodesForSearch('Voice'));
        $this->assertContains('SND', ProductFormat::matchingCodesForSearch('Voice'));
    }

    /** @param list<string> $values */
    private function assertRoundTrip(array $values, string $locale): void
    {
        $this->assertSame(
            ProductFormat::normalizeStoredValues($values),
            ProductFormat::normalizeInput(ProductFormat::inputValue($values)),
            "Failed Product Format round-trip for {$locale}: " . json_encode($values),
        );
    }
}
