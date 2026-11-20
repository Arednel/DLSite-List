<?php

namespace Tests\Unit\Support\Transfers;

use App\Enums\DlsiteLanguage;
use App\Support\Transfers\LibraryWorkValidator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LibraryWorkValidatorTest extends TestCase
{
    #[DataProvider('localizedTextRecords')]
    public function test_localized_text_preserves_required_optional_and_size_rules(array $metadata, ?string $errorField): void
    {
        $record = ['product_code' => 'RJ123456', ...$metadata];

        try {
            $validated = app(LibraryWorkValidator::class)->validate($record);
        } catch (ValidationException $exception) {
            $this->assertNotNull($errorField);
            $this->assertArrayHasKey($errorField, $exception->errors());

            return;
        }

        $this->assertNull($errorField, 'Expected validation to reject the supplied metadata.');
        $this->assertSame($record, $validated);
    }

    public static function localizedTextRecords(): array
    {
        return [
            'missing titles' => [[], 'titles'],
            'missing Japanese title' => [['titles' => ['en' => 'English title']], 'titles.ja'],
            'null Japanese title' => [['titles' => ['ja' => null]], 'titles.ja'],
            'empty Japanese title' => [['titles' => ['ja' => '']], 'titles.ja'],
            'Japanese title only' => [['titles' => ['ja' => 'Title']], null],
            'null English title' => [['titles' => ['ja' => 'Title', 'en' => null]], null],
            'empty English title' => [['titles' => ['ja' => 'Title', 'en' => '']], null],
            'non-string English title' => [['titles' => ['ja' => 'Title', 'en' => []]], 'titles.en'],
            'unsupported title locale' => [['titles' => ['ja' => 'Title', 'x-test' => 'Title']], 'titles'],
            'maximum Japanese title' => [['titles' => ['ja' => str_repeat('あ', 1000)]], null],
            'oversized Japanese title' => [['titles' => ['ja' => str_repeat('あ', 1001)]], 'titles.ja'],
            'maximum English title' => [['titles' => ['ja' => 'Title', 'en' => str_repeat('x', 1000)]], null],
            'oversized English title' => [['titles' => ['ja' => 'Title', 'en' => str_repeat('x', 1001)]], 'titles.en'],
            'empty descriptions' => [['titles' => ['ja' => 'Title'], 'descriptions' => []], null],
            'null descriptions' => [['titles' => ['ja' => 'Title'], 'descriptions' => ['ja' => null, 'en' => null]], null],
            'empty description values' => [['titles' => ['ja' => 'Title'], 'descriptions' => ['ja' => '', 'en' => '']], null],
            'non-string description' => [['titles' => ['ja' => 'Title'], 'descriptions' => ['en' => []]], 'descriptions.en'],
            'unsupported description locale' => [['titles' => ['ja' => 'Title'], 'descriptions' => ['x-test' => 'Description']], 'descriptions'],
            'maximum ASCII description' => [['titles' => ['ja' => 'Title'], 'descriptions' => ['en' => str_repeat('x', 65535)]], null],
            'oversized ASCII description' => [['titles' => ['ja' => 'Title'], 'descriptions' => ['en' => str_repeat('x', 65536)]], 'descriptions.en'],
            'maximum multibyte description' => [['titles' => ['ja' => 'Title'], 'descriptions' => ['ja' => str_repeat('あ', 21845)]], null],
            'oversized multibyte description' => [['titles' => ['ja' => 'Title'], 'descriptions' => ['ja' => str_repeat('あ', 21846)]], 'descriptions.ja'],
        ];
    }

    #[DataProvider('ratingPairs')]
    public function test_dlsite_rating_scales_are_validated_as_a_pair(array $details, bool $valid): void
    {
        if (! $valid) {
            $this->expectException(ValidationException::class);
        }

        app(LibraryWorkValidator::class)->validate([
            'product_code' => 'RJ123456',
            'titles' => ['ja' => 'Test work'],
            'details' => $details,
        ]);

        if ($valid) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_normalized_transfer_records_preserve_metadata_for_every_supported_language(): void
    {
        $record = ['product_code' => 'RJ123456', 'tags' => ['custom' => ['Custom']]];
        foreach (DlsiteLanguage::cases() as $language) {
            $code = $language->value;
            $record['titles'][$code] = "Title [{$code}]";
            $record['descriptions'][$code] = "Description [{$code}]";
            $record['tags'][$code] = ["Tag [{$code}]"];
        }

        $this->assertSame($record, app(LibraryWorkValidator::class)->validate($record));
    }

    public function test_tags_reject_unsupported_locale_keys(): void
    {
        $this->expectException(ValidationException::class);

        app(LibraryWorkValidator::class)->validate([
            'product_code' => 'RJ123456',
            'titles' => ['ja' => 'Test work'],
            'tags' => ['x-test' => ['Unsupported']],
        ]);
    }

    public static function ratingPairs(): array
    {
        return [
            'no rating fields' => [[], true],
            'count only' => [['dlsite_rating_count' => 100], true],
            'both ratings null' => [[
                'dlsite_rating_5' => null,
                'dlsite_rating_10' => null,
            ], true],
            'both ratings populated' => [[
                'dlsite_rating_5' => '4.97',
                'dlsite_rating_10' => '9.93',
                'dlsite_rating_count' => 100,
            ], true],
            'five only' => [['dlsite_rating_5' => '4.97'], false],
            'ten only' => [['dlsite_rating_10' => '9.93'], false],
            'five null and ten populated' => [[
                'dlsite_rating_5' => null,
                'dlsite_rating_10' => '9.93',
            ], false],
            'five populated and ten null' => [[
                'dlsite_rating_5' => '4.97',
                'dlsite_rating_10' => null,
            ], false],
        ];
    }
}
