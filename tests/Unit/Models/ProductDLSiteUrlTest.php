<?php

namespace Tests\Unit\Models;

use App\Models\Product;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductDLSiteUrlTest extends TestCase
{
    public function test_disabled_age_appropriate_links_return_maniax_without_loading_age(): void
    {
        $product = new Product(['id' => 'RJ01234567']);

        $this->assertSame(
            'https://www.dlsite.com/maniax/work/=/product_id/RJ01234567.html',
            $product->dlsiteWorkUrl(false),
        );
    }

    #[DataProvider('enabledAgeUrlProvider')]
    public function test_enabled_age_appropriate_links_use_the_expected_dlsite_section(
        ?string $ageCategory,
        string $section,
    ): void {
        $product = new Product([
            'id' => 'RJ01234567',
            'age_category' => $ageCategory,
        ]);

        $this->assertSame(
            "https://www.dlsite.com/{$section}/work/=/product_id/RJ01234567.html",
            $product->dlsiteWorkUrl(true),
        );
    }

    public static function enabledAgeUrlProvider(): array
    {
        return [
            'all ages' => ['ALL_AGES', 'home'],
            'r15' => ['R15', 'maniax'],
            'r18' => ['R18', 'maniax'],
            'missing age' => [null, 'maniax'],
            'invalid legacy age' => ['UNKNOWN', 'maniax'],
        ];
    }
}
