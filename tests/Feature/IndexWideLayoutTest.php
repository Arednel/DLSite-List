<?php

namespace Tests\Feature;

use App\Enums\UiLanguage;
use App\Livewire\IndexTableWidthSettings;
use App\Livewire\ProductIndex;
use App\Models\Option;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IndexWideLayoutTest extends TestCase
{
    use RefreshDatabase;

    private const EXTRA_ROOM_HINT = 'When the table is wider than Default, the extra room goes to the Title and Tags columns.';

    public function test_table_width_settings_explain_where_the_extra_room_goes(): void
    {
        Livewire::test(IndexTableWidthSettings::class)
            ->assertSee(self::EXTRA_ROOM_HINT);
    }

    #[DataProvider('localizedExtraRoomHintProvider')]
    public function test_extra_room_hint_is_localized_on_the_options_page(UiLanguage $language, string $expected): void
    {
        Option::setUiLanguage($language);

        $this->get(route('options.index', ['tab' => 'general']))
            ->assertOk()
            ->assertSee($expected)
            ->assertDontSee(self::EXTRA_ROOM_HINT);
    }

    public static function localizedExtraRoomHintProvider(): array
    {
        return [
            'Japanese' => [
                UiLanguage::Japanese,
                '表の幅が「標準」より広いときは、増えた幅をタイトル列とタグ列に優先して使います。',
            ],
            'Traditional Chinese' => [
                UiLanguage::TraditionalChinese,
                '表格比「預設」寬時，多出來的寬度會優先給標題欄和標籤欄。',
            ],
        ];
    }

    public function test_full_width_index_renders_the_hooks_the_wide_layout_relies_on(): void
    {
        Product::factory()->create([
            'id' => 'RJ000000001',
            'maker_id' => 'RG000000001',
            'work_name' => 'WIDE_LAYOUT_WORK',
        ]);
        Option::setIndexTableWidth([
            'mode' => Option::INDEX_TABLE_WIDTH_FULL,
            'custom' => '',
        ]);

        Livewire::test(ProductIndex::class)
            ->assertSee('--index-table-width: 100%', false)
            ->assertSee('class="list-unit product-list-panel"', false)
            ->assertSee('class="header-title configurable tags"', false)
            ->assertSee('WIDE_LAYOUT_WORK');
    }

    public function test_stylesheet_gives_extra_room_to_tags_only_when_the_table_is_wider_than_default(): void
    {
        $css = $this->indexStylesheet();

        $this->assertStringContainsString('container: index-list / inline-size;', $this->topLevelRule($css, '.list-unit'));

        // The default 1024px table keeps its fixed Tags width.
        $this->assertMatchesRegularExpression('/\.header-title\.tags\s*\{\s*width:\s*120px;?\s*\}/', $css);

        // Only a wider table lets Tags share the extra room with Title.
        $this->assertMatchesRegularExpression(
            '/@container index-list \(width > 1024px\)\s*\{\s*'
                . '\.list-table \.list-table-header \.header-title\.tags\s*\{[^}]*width:\s*auto;[^}]*min-width:\s*120px;[^}]*\}/',
            $css,
        );

        // Short columns keep their fixed widths.
        foreach (['score' => '65px', 'age-category' => '60px', 'progress' => '90px'] as $column => $width) {
            $this->assertMatchesRegularExpression(
                '/\.header-title\.' . preg_quote($column, '/') . '\s*\{\s*width:\s*' . $width . ';?\s*\}/',
                $css,
            );
        }
    }

    public function test_stylesheet_sizes_the_index_against_the_page_instead_of_the_viewport(): void
    {
        $css = $this->indexStylesheet();

        foreach (['.list-container', '.cover-block', '.cover-block .image-container', '.list-unit'] as $selector) {
            $this->assertStringNotContainsString(
                '100vw',
                $this->topLevelRule($css, $selector),
                "{$selector} should be sized against its container so the scrollbar never adds a horizontal scroll.",
            );
        }

        $this->assertMatchesRegularExpression(
            '/@media \(min-width: 1200px\)\s*\{\s*\.list-container\s*\{\s*--index-page-gutter:\s*60px;/',
            $css,
        );
    }

    private function indexStylesheet(): string
    {
        $css = file_get_contents(public_path('css/index.css'));

        $this->assertIsString($css);

        return str_replace("\r\n", "\n", $css);
    }

    private function topLevelRule(string $css, string $selector): string
    {
        $pattern = '/^' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/m';

        $this->assertSame(1, preg_match($pattern, $css, $matches), "Missing top-level rule for {$selector}.");

        return $matches[1];
    }
}
