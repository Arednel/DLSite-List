<?php

namespace Tests\Unit\Support\Transfers;

use App\Enums\DlsiteLanguage;
use App\Enums\ProductField;
use App\Models\Option;
use App\Support\Transfers\PortableOptions;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PortableOptionsTest extends TestCase
{
    public function test_dlsite_metadata_language_options_accept_canonical_codes_and_normalize_legacy_japanese(): void
    {
        $options = app(PortableOptions::class);

        $options->validate(Option::DLSITE_METADATA_LANGUAGE, 'ja');
        $options->validate(Option::DLSITE_METADATA_FETCH_LANGUAGES, ['en']);
        $options->validate(Option::DLSITE_METADATA_FETCH_LANGUAGES, []);

        $this->assertSame(
            Option::DLSITE_METADATA_LANGUAGE,
            $options->normalizeKeyCompatibility('fetched_tag_language'),
        );
        $this->assertSame(
            Option::DLSITE_METADATA_LANGUAGE_FOLLOWS_UI,
            $options->normalizeKeyCompatibility('fetched_tag_language_follows_ui'),
        );
        $this->assertSame('ja', $options->normalizeCompatibility(Option::DLSITE_METADATA_LANGUAGE, 'jp'));
        $this->assertSame(
            ['jp', 'ja', 'en'],
            $options->normalizeCompatibility(Option::DLSITE_METADATA_FETCH_LANGUAGES, ['jp', 'ja', 'en']),
        );
    }

    public function test_empty_optional_fetch_language_list_roundtrips_through_portable_options(): void
    {
        $options = app(PortableOptions::class);

        $options->validate(Option::DLSITE_METADATA_FETCH_LANGUAGES, []);
        $options->apply(Option::DLSITE_METADATA_FETCH_LANGUAGES, []);

        $this->assertSame([], $options->current(Option::DLSITE_METADATA_FETCH_LANGUAGES));
    }

    public function test_optional_fetch_language_validation_rejects_japanese_duplicates_and_non_lists(): void
    {
        $options = app(PortableOptions::class);

        foreach (
            [
                [DlsiteLanguage::Japanese->value],
                [DlsiteLanguage::English->value, DlsiteLanguage::English->value],
                ['language' => DlsiteLanguage::English->value],
            ] as $value
        ) {
            try {
                $options->validate(Option::DLSITE_METADATA_FETCH_LANGUAGES, $value);
                $this->fail('Expected optional fetch language validation to fail.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_fetched_metadata_languages_merge_as_a_set_and_overwrite_as_a_whole(): void
    {
        $options = app(PortableOptions::class);

        $this->assertSame(
            ['en'],
            $options->combine(Option::DLSITE_METADATA_FETCH_LANGUAGES, ['en'], 'merge', []),
        );
        $this->assertSame(
            [],
            $options->combine(Option::DLSITE_METADATA_FETCH_LANGUAGES, [], 'overwrite', ['en']),
        );
    }

    public function test_legacy_english_description_field_layout_is_normalized_before_validation(): void
    {
        $options = app(PortableOptions::class);
        $legacyLayout = [['field' => 'description_english', 'visible' => true]];

        foreach (array_keys($options->layouts()) as $key) {
            $normalized = $options->normalizeCompatibility($key, $legacyLayout);

            $this->assertSame(ProductField::DescriptionLocalized->value, $normalized[0]['field'], $key);
            $options->validate($key, $normalized);
        }
    }

    public function test_schema_v1_field_name_normalization_is_separate_from_cross_version_compatibility(): void
    {
        $options = app(PortableOptions::class);
        $legacyLayout = [['field' => 'rj_code', 'visible' => true]];

        $this->assertSame(
            $legacyLayout,
            $options->normalizeCompatibility(Option::QUICK_ADD_FIELD_LAYOUT, $legacyLayout),
        );
        $this->assertSame(
            [['field' => 'product_code', 'visible' => true]],
            $options->normalizeSchemaV1(Option::QUICK_ADD_FIELD_LAYOUT, $legacyLayout),
        );
    }

    #[DataProvider('columnWidths')]
    public function test_index_column_width_import_validation(array $width, bool $valid): void
    {
        $options = app(PortableOptions::class);
        $layout = [['field' => 'tags', 'visible' => true, ...$width]];

        if (! $valid) {
            $this->expectException(ValidationException::class);
        }

        $options->validate(Option::INDEX_FIELD_LAYOUT, $layout);

        if ($valid) {
            $this->addToAssertionCount(1);
        }
    }

    #[DataProvider('tableWidths')]
    public function test_index_table_width_import_validation(array $width, bool $valid): void
    {
        $options = app(PortableOptions::class);

        if (! $valid) {
            $this->expectException(ValidationException::class);
        }

        $options->validate(Option::INDEX_TABLE_WIDTH, $width);

        if ($valid) {
            $this->addToAssertionCount(1);
        }
    }

    public static function tableWidths(): array
    {
        return [
            'default' => [[
                'mode' => Option::INDEX_TABLE_WIDTH_DEFAULT,
                'custom' => '',
                'side_margin' => null,
            ], true],
            'zero side margin without unit' => [[
                'mode' => Option::INDEX_TABLE_WIDTH_DEFAULT,
                'custom' => '',
                'side_margin' => '0',
            ], true],
            'zero side margin with unit' => [[
                'mode' => Option::INDEX_TABLE_WIDTH_DEFAULT,
                'custom' => '',
                'side_margin' => '0px',
            ], true],
            'custom width' => [[
                'mode' => Option::INDEX_TABLE_WIDTH_CUSTOM,
                'custom' => '72vw',
                'side_margin' => '3rem',
            ], true],
            'missing custom width' => [[
                'mode' => Option::INDEX_TABLE_WIDTH_CUSTOM,
                'side_margin' => null,
            ], false],
            'invalid custom width' => [[
                'mode' => Option::INDEX_TABLE_WIDTH_CUSTOM,
                'custom' => 'calc(100%)',
                'side_margin' => null,
            ], false],
            'non-custom mode with custom width' => [[
                'mode' => Option::INDEX_TABLE_WIDTH_WIDE,
                'custom' => '72vw',
                'side_margin' => null,
            ], false],
        ];
    }

    public static function columnWidths(): array
    {
        return [
            'no width setting' => [[], true],
            'default' => [['width_mode' => 'default'], true],
            'default with width' => [['width_mode' => 'default', 'width' => '180px'], false],
            'pixels' => [['width_mode' => 'custom', 'width' => '180px'], true],
            'fractional rem' => [['width_mode' => 'custom', 'width' => '12.5rem'], true],
            'em' => [['width_mode' => 'custom', 'width' => '10em'], true],
            'percentage' => [['width_mode' => 'custom', 'width' => '25%'], true],
            'viewport' => [['width_mode' => 'custom', 'width' => '20vw'], true],
            'missing custom width' => [['width_mode' => 'custom'], false],
            'empty custom width' => [['width_mode' => 'custom', 'width' => ''], false],
            'null custom width' => [['width_mode' => 'custom', 'width' => null], false],
            'invalid custom width' => [['width_mode' => 'custom', 'width' => 'calc(100%)'], false],
            'invalid supplied default width' => [['width_mode' => 'default', 'width' => 'invalid'], false],
            'invalid mode' => [['width_mode' => 'unknown'], false],
        ];
    }
}
