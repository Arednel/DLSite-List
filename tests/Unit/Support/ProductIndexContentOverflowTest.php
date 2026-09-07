<?php

namespace Tests\Unit\Support;

use App\Support\ProductIndexContentOverflow;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProductIndexContentOverflowTest extends TestCase
{
    public function test_missing_settings_normalize_to_disabled_targets_at_eighty_pixels(): void
    {
        $this->assertSame([
            'inline_notes' => ['enabled' => false, 'height' => '80px'],
            'notes_column' => ['enabled' => false, 'height' => '80px'],
            'tags' => ['enabled' => false, 'height' => '80px'],
        ], ProductIndexContentOverflow::normalize(null));
    }

    public function test_disabled_targets_discard_custom_heights(): void
    {
        $this->assertSame(ProductIndexContentOverflow::DEFAULTS, ProductIndexContentOverflow::normalize([
            'inline_notes' => ['enabled' => false, 'height' => '6rem'],
            'notes_column' => ['enabled' => '0', 'height' => '120px'],
            'tags' => ['enabled' => 'false', 'height' => '25vh'],
        ]));
    }

    public function test_normalization_falls_back_per_value_and_ignores_unknown_targets(): void
    {
        $this->assertSame([
            'inline_notes' => ['enabled' => true, 'height' => '4.5rem'],
            'notes_column' => ['enabled' => true, 'height' => '80px'],
            'tags' => ['enabled' => false, 'height' => '80px'],
        ], ProductIndexContentOverflow::normalize([
            'inline_notes' => ['enabled' => '1', 'height' => ' 4.5REM '],
            'notes_column' => ['enabled' => true, 'height' => 'calc(100%)'],
            'tags' => 'not-an-array',
            'unknown' => ['enabled' => true, 'height' => '1px'],
        ]));
    }

    #[DataProvider('validHeightProvider')]
    public function test_supported_positive_css_heights_are_preserved(string $height, string $normalized): void
    {
        $this->assertSame($normalized, ProductIndexContentOverflow::normalize([
            'tags' => ['enabled' => true, 'height' => $height],
        ])['tags']['height']);
    }

    #[DataProvider('invalidHeightProvider')]
    public function test_unsafe_or_unsupported_css_heights_fall_back_to_default(string $height): void
    {
        $this->assertSame(ProductIndexContentOverflow::DEFAULT_HEIGHT, ProductIndexContentOverflow::normalize([
            'tags' => ['enabled' => true, 'height' => $height],
        ])['tags']['height']);
    }

    public static function validHeightProvider(): iterable
    {
        yield 'pixels' => ['120px', '120px'];
        yield 'root em and normalization' => [' 4.5REM ', '4.5rem'];
        yield 'em' => ['3em', '3em'];
        yield 'percentage' => ['50%', '50%'];
        yield 'viewport width' => ['25vw', '25vw'];
        yield 'viewport height' => ['25vh', '25vh'];
        yield 'viewport minimum' => ['25vmin', '25vmin'];
        yield 'viewport maximum' => ['25vmax', '25vmax'];
        yield 'small viewport height' => ['25svh', '25svh'];
        yield 'large viewport height' => ['25lvh', '25lvh'];
        yield 'dynamic viewport height' => ['25dvh', '25dvh'];
        yield 'leading decimal' => ['.5rem', '.5rem'];
    }

    public static function invalidHeightProvider(): iterable
    {
        yield 'zero' => ['0px'];
        yield 'decimal zero' => ['0.0rem'];
        yield 'negative' => ['-1px'];
        yield 'calculation' => ['calc(100% - 2rem)'];
        yield 'variable' => ['var(--height)'];
        yield 'unsupported keyword' => ['auto'];
        yield 'unsupported unit' => ['12ch'];
        yield 'missing unit' => ['80'];
        yield 'internal whitespace' => ['80 px'];
        yield 'trailing decimal point' => ['1.rem'];
    }
}
