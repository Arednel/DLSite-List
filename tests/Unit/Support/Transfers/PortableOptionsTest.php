<?php

namespace Tests\Unit\Support\Transfers;

use App\Models\Option;
use App\Support\Transfers\PortableOptions;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PortableOptionsTest extends TestCase
{
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
