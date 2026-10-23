<?php

namespace Tests\Feature;

use App\Enums\LibraryImportDecision;
use App\Enums\UiLanguage;
use App\Livewire\IndexTableWidthSettings;
use App\Livewire\OptionsResetDefaults;
use App\Livewire\ProductIndex;
use App\Models\Option;
use App\Models\Product;
use App\Support\Transfers\PortableOptions;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Concerns\InteractsWithLibraryTransfers;
use Tests\TestCase;

class IndexWideLayoutTest extends TestCase
{
    use InteractsWithLibraryTransfers;

    private const ADAPTIVE_CLASS = 'index-adaptive-columns';

    private const SWITCH_LABEL = 'Adaptive column widths';

    private const SWITCH_HELP = 'When enabled, a table wider than 1024px (Wide, Full, or Custom) gives the extra room to the Title and Tags columns, and the page does not scroll sideways.';

    private const OLD_HINT = 'When the table is wider than Default, the extra room goes to the Title and Tags columns.';

    public function test_adaptive_column_widths_is_disabled_by_default(): void
    {
        $this->assertFalse(Option::defaultFor(Option::INDEX_ADAPTIVE_COLUMN_WIDTHS_ENABLED));
        $this->assertFalse(Option::indexAdaptiveColumnWidthsEnabled());
        $this->assertFalse(Option::productIndexSettings()->adaptiveColumnWidthsEnabled);

        Livewire::test(IndexTableWidthSettings::class)
            ->assertSet('adaptiveColumnWidths', false)
            ->assertSee(self::SWITCH_LABEL)
            ->assertSee(self::SWITCH_HELP)
            ->assertSee('wire:model.live="adaptiveColumnWidths"', false)
            ->assertDontSee(self::OLD_HINT);
    }

    public function test_switch_is_saved_with_the_table_width(): void
    {
        Livewire::test(IndexTableWidthSettings::class)
            ->set('mode', Option::INDEX_TABLE_WIDTH_FULL)
            ->set('adaptiveColumnWidths', true)
            ->assertSet('saved', false)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('adaptiveColumnWidths', true)
            ->assertSet('saved', true)
            ->assertSet('notice', 'Index table width setting saved.');

        $this->assertTrue(Option::indexAdaptiveColumnWidthsEnabled());
        $this->assertTrue(Option::productIndexSettings()->adaptiveColumnWidthsEnabled);
        $this->assertSame(Option::INDEX_TABLE_WIDTH_FULL, Option::indexTableWidth()['mode']);

        Livewire::test(IndexTableWidthSettings::class)
            ->assertSet('adaptiveColumnWidths', true)
            ->set('adaptiveColumnWidths', false)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('adaptiveColumnWidths', false);

        $this->assertFalse(Option::indexAdaptiveColumnWidthsEnabled());
        $this->assertSame(Option::INDEX_TABLE_WIDTH_FULL, Option::indexTableWidth()['mode']);
    }

    public function test_changing_the_switch_clears_the_saved_notice_without_saving(): void
    {
        Livewire::test(IndexTableWidthSettings::class)
            ->call('save')
            ->assertSet('saved', true)
            ->set('adaptiveColumnWidths', true)
            ->assertSet('saved', false)
            ->assertSet('notice', '');

        $this->assertFalse(Option::indexAdaptiveColumnWidthsEnabled());
    }

    public function test_section_reset_turns_the_switch_off_with_the_table_width(): void
    {
        Option::setIndexTableWidth(['mode' => Option::INDEX_TABLE_WIDTH_FULL, 'custom' => '']);
        Option::setIndexAdaptiveColumnWidthsEnabled(true);

        Livewire::test(IndexTableWidthSettings::class)
            ->assertSet('adaptiveColumnWidths', true)
            ->call('askResetToDefault')
            ->call('resetToDefault')
            ->assertHasNoErrors()
            ->assertSet('mode', Option::INDEX_TABLE_WIDTH_DEFAULT)
            ->assertSet('adaptiveColumnWidths', false)
            ->assertSet('notice', 'Index table width reset to default.');

        $this->assertFalse(Option::indexAdaptiveColumnWidthsEnabled());
        $this->assertDatabaseMissing('options', ['key' => Option::INDEX_ADAPTIVE_COLUMN_WIDTHS_ENABLED]);
        $this->assertDatabaseMissing('options', ['key' => Option::INDEX_TABLE_WIDTH]);
    }

    public function test_global_defaults_reset_turns_the_switch_off_and_refreshes_the_component(): void
    {
        Option::setIndexAdaptiveColumnWidthsEnabled(true);

        $component = Livewire::test(IndexTableWidthSettings::class)
            ->assertSet('adaptiveColumnWidths', true);

        Livewire::test(OptionsResetDefaults::class, ['activeTab' => 'general'])
            ->call('resetAll')
            ->assertRedirect(route('options.index', ['tab' => 'general']));

        $this->assertFalse(Option::indexAdaptiveColumnWidthsEnabled());
        $this->assertDatabaseMissing('options', ['key' => Option::INDEX_ADAPTIVE_COLUMN_WIDTHS_ENABLED]);

        $component
            ->call('refreshFromSettings')
            ->assertSet('adaptiveColumnWidths', false);
    }

    public function test_switch_is_a_portable_boolean_option(): void
    {
        $options = app(PortableOptions::class);

        $this->assertArrayHasKey(Option::INDEX_ADAPTIVE_COLUMN_WIDTHS_ENABLED, $options->defaults());
        $this->assertFalse($options->current(Option::INDEX_ADAPTIVE_COLUMN_WIDTHS_ENABLED));

        Option::setIndexAdaptiveColumnWidthsEnabled(true);
        $this->assertTrue($options->all()[Option::INDEX_ADAPTIVE_COLUMN_WIDTHS_ENABLED]);

        $this->expectException(ValidationException::class);
        $options->validate(Option::INDEX_ADAPTIVE_COLUMN_WIDTHS_ENABLED, 'yes');
    }

    public function test_switch_survives_options_export_and_import(): void
    {
        Option::setIndexAdaptiveColumnWidthsEnabled(true);
        $export = $this->exported(['options']);

        Option::setIndexAdaptiveColumnWidthsEnabled(false);
        $this->apply($this->imported($export), LibraryImportDecision::Overwrite);

        $this->assertTrue(Option::indexAdaptiveColumnWidthsEnabled());
    }

    #[DataProvider('localizedSwitchProvider')]
    public function test_switch_label_and_help_are_localized_on_the_options_page(
        UiLanguage $language,
        string $label,
        string $help,
    ): void {
        Option::setUiLanguage($language);

        $response = $this->get(route('options.index', ['tab' => 'general']))
            ->assertOk()
            ->assertSee($label)
            ->assertSee($help)
            ->assertDontSee(self::OLD_HINT);

        if ($language !== UiLanguage::English) {
            $response->assertDontSee(self::SWITCH_HELP);
        }
    }

    public static function localizedSwitchProvider(): array
    {
        return [
            'English' => [
                UiLanguage::English,
                self::SWITCH_LABEL,
                self::SWITCH_HELP,
            ],
            'Japanese' => [
                UiLanguage::Japanese,
                '列幅の自動調整',
                '有効にすると、表の幅が1024pxより広いとき（ワイド・全幅・カスタム）、増えた幅をタイトル列とタグ列に使い、ページが横にスクロールしないようにします。',
            ],
            'Traditional Chinese' => [
                UiLanguage::TraditionalChinese,
                '自適應欄寬',
                '啟用後，表格比 1024px 寬時（寬版、滿版、自訂），多出來的寬度會給標題欄和標籤欄，頁面也不會左右捲動。',
            ],
        ];
    }

    #[DataProvider('tableWidthModeProvider')]
    public function test_index_does_not_render_the_adaptive_class_while_the_switch_is_off(string $mode): void
    {
        $this->createWork();
        Option::setIndexTableWidth(['mode' => $mode, 'custom' => '']);

        Livewire::test(ProductIndex::class)
            ->assertSee('WIDE_LAYOUT_WORK')
            ->assertDontSee(self::ADAPTIVE_CLASS, false);

        $this->get(route('index'))
            ->assertOk()
            ->assertSee('WIDE_LAYOUT_WORK')
            ->assertDontSee(self::ADAPTIVE_CLASS, false);
    }

    #[DataProvider('tableWidthModeProvider')]
    public function test_index_renders_the_adaptive_class_only_while_the_switch_is_on(string $mode): void
    {
        $this->createWork();
        Option::setIndexTableWidth(['mode' => $mode, 'custom' => '']);
        Option::setIndexAdaptiveColumnWidthsEnabled(true);

        Livewire::test(ProductIndex::class)
            ->assertSee('class="' . self::ADAPTIVE_CLASS . '"', false)
            ->assertSee('class="list-unit product-list-panel"', false)
            ->assertSee('class="header-title configurable tags"', false)
            ->assertSee('WIDE_LAYOUT_WORK');

        $this->get(route('index'))
            ->assertOk()
            ->assertSee('class="' . self::ADAPTIVE_CLASS . '"', false);

        Option::setIndexAdaptiveColumnWidthsEnabled(false);

        Livewire::test(ProductIndex::class)
            ->assertDontSee(self::ADAPTIVE_CLASS, false);
    }

    public static function tableWidthModeProvider(): array
    {
        return [
            'Default' => [Option::INDEX_TABLE_WIDTH_DEFAULT],
            'Full' => [Option::INDEX_TABLE_WIDTH_FULL],
        ];
    }

    public function test_full_width_index_keeps_the_width_variable_and_layout_hooks(): void
    {
        $this->createWork();
        Option::setIndexTableWidth(['mode' => Option::INDEX_TABLE_WIDTH_FULL, 'custom' => '']);

        Livewire::test(ProductIndex::class)
            ->assertSee('--index-table-width: 100%', false)
            ->assertSee('class="list-unit product-list-panel"', false)
            ->assertSee('class="header-title configurable tags"', false);
    }

    public function test_every_adaptive_rule_is_scoped_under_the_adaptive_class(): void
    {
        $scoped = 0;

        foreach ($this->rules($this->indexStylesheet()) as ['selectors' => $selectors, 'body' => $body]) {
            $adaptive = str_contains($body, 'index-list')
                || str_contains($body, '--index-page-gutter')
                || array_filter($selectors, fn(string $selector): bool => str_contains($selector, self::ADAPTIVE_CLASS)) !== [];

            if (! $adaptive) {
                continue;
            }

            foreach ($selectors as $selector) {
                $this->assertStringStartsWith(
                    '.' . self::ADAPTIVE_CLASS . ' ',
                    $selector,
                    'Adaptive rules must only apply while the option adds the class.',
                );
            }
            $scoped++;
        }

        $this->assertGreaterThanOrEqual(5, $scoped);
    }

    public function test_default_rules_keep_the_original_layout(): void
    {
        $css = $this->indexStylesheet();

        $this->assertSame(
            'width: min(calc(var(--index-table-width, 1024px) + 34px), calc(100vw - 16px));',
            $this->declaration($this->topLevelRule($css, '.list-container'), 'width'),
        );
        $this->assertSame(
            'width: var(--index-table-width, 1024px);',
            $this->declaration($this->topLevelRule($css, '.cover-block'), 'width'),
        );
        $this->assertSame(
            'width: var(--index-table-width, 1024px);',
            $this->declaration($this->topLevelRule($css, '.cover-block .image-container'), 'width'),
        );
        $this->assertSame(
            'width: min(var(--index-table-width, 1024px), calc(100vw - 50px));',
            $this->declaration($this->topLevelRule($css, '.list-unit'), 'width'),
        );
        $this->assertStringNotContainsString('container', $this->topLevelRule($css, '.list-unit'));
        $this->assertMatchesRegularExpression(
            '/^\.list-table \.list-table-header \.header-title\.tags\s*\{\s*width:\s*120px;?\s*\}/m',
            $css,
        );
    }

    public function test_adaptive_rules_give_extra_room_to_tags_only_when_the_table_is_wider_than_default(): void
    {
        $css = $this->indexStylesheet();
        $class = preg_quote('.' . self::ADAPTIVE_CLASS, '/');

        $this->assertMatchesRegularExpression(
            '/' . $class . ' \.list-unit\s*\{[^}]*container:\s*index-list \/ inline-size;/',
            $css,
        );

        // Only a wider table lets Tags share the extra room with Title.
        $this->assertMatchesRegularExpression(
            '/@container index-list \(width > 1024px\)\s*\{\s*'
                . $class . ' \.list-table \.list-table-header \.header-title\.tags\s*\{[^}]*width:\s*auto;[^}]*min-width:\s*120px;[^}]*\}/',
            $css,
        );

        // Short columns keep fixed pixel widths and are not touched by the wide-table rule.
        foreach (['score', 'age-category', 'progress'] as $column) {
            $this->assertMatchesRegularExpression(
                '/\.header-title\.' . preg_quote($column, '/') . '\s*\{\s*width:\s*\d+px;?\s*\}/',
                $css,
            );
        }

        $this->assertSame(1, preg_match('/@container index-list \(width > 1024px\)\s*\{(.*?)\n\}/s', $css, $wideRule));
        $this->assertStringNotContainsString('.header-title.score', $wideRule[1]);
        $this->assertStringNotContainsString('.header-title.age-category', $wideRule[1]);
        $this->assertStringNotContainsString('.header-title.progress', $wideRule[1]);
    }

    public function test_adaptive_rules_size_the_index_against_the_page_and_leave_mobile_cards_alone(): void
    {
        $css = $this->indexStylesheet();
        $class = '.' . self::ADAPTIVE_CLASS;

        $this->assertSame(1, preg_match('/@media \(min-width: 769px\)\s*\{(.*?)\n\}/s', $css, $desktop));

        foreach (['.list-container', '.cover-block', '.cover-block .image-container', '.list-unit', '.progress-menu-container .progress-menu'] as $selector) {
            $this->assertStringContainsString("{$class} {$selector}", $desktop[1], "{$selector} needs an adaptive desktop rule.");
        }
        $this->assertStringNotContainsString('100vw', $desktop[1], 'Adaptive rules size the Index against the page, not the viewport.');

        $this->assertMatchesRegularExpression(
            '/@media \(min-width: 1200px\)\s*\{\s*' . preg_quote($class, '/') . ' \.list-container\s*\{\s*--index-page-gutter:\s*60px;/',
            $css,
        );
    }

    private function createWork(): void
    {
        Product::factory()->create([
            'id' => 'RJ000000001',
            'maker_id' => 'RG000000001',
            'work_name' => 'WIDE_LAYOUT_WORK',
        ]);
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

    private function declaration(string $body, string $property): string
    {
        $this->assertSame(
            1,
            preg_match('/^\s*(' . preg_quote($property, '/') . ':[^;]*;)/m', $body, $matches),
            "Missing {$property} declaration.",
        );

        return $matches[1];
    }

    /**
     * Innermost style rules, including rules nested in @media and @container blocks.
     *
     * @return list<array{selectors: list<string>, body: string}>
     */
    private function rules(string $css): array
    {
        $css = preg_replace('#/\*.*?\*/#s', '', $css);
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER);

        return array_map(fn(array $match): array => [
            'selectors' => array_map('trim', explode(',', trim($match[1]))),
            'body' => $match[2],
        ], $matches);
    }
}
