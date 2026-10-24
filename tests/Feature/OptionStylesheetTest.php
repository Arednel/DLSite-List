<?php

namespace Tests\Feature;

use App\Models\Option;
use App\Models\User;
use App\Enums\ProductField;
use App\Support\OptionStylesheet;
use App\Support\ProductFieldLayout;
use App\Support\Transfers\PortableOptions;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class OptionStylesheetTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('widths')]
    public function test_saving_table_dimensions_regenerates_the_stylesheet(array $settings, ?string $width): void
    {
        Option::setIndexTableWidth($settings);

        $css = file_get_contents(app(OptionStylesheet::class)->path());
        if ($width === null) {
            $this->assertStringNotContainsString('--index-table-width:', $css);
        } else {
            $this->assertStringContainsString('--index-table-width: ' . $width . ';', $css);
        }

        if ($width === '100%') {
            $this->assertStringContainsString('overflow-x: clip;', $css);
        } else {
            $this->assertStringNotContainsString('overflow-x: clip;', $css);
        }

        if (isset($settings['side_margin']) && $settings['side_margin'] !== '0px') {
            $this->assertStringContainsString('--index-table-side-margin: ' . $settings['side_margin'] . ';', $css);
            $this->assertStringContainsString('@media (max-width: 768px)', $css);
            $this->assertStringContainsString('.product-index-layout .list-container', $css);
            $this->assertStringContainsString('.product-index-layout .cover-block', $css);
            $this->assertStringContainsString('.product-index-layout .list-unit', $css);
        } else {
            $this->assertStringNotContainsString('--index-table-side-margin:', $css);
            $this->assertStringNotContainsString('.product-index-layout .list-container', $css);
        }
    }

    public static function widths(): array
    {
        return [
            'default' => [['mode' => 'default'], null],
            'wide' => [['mode' => 'wide'], '1400px'],
            'full' => [['mode' => 'full'], '100%'],
            'custom full' => [['mode' => 'custom', 'custom' => '100%'], '100%'],
            'custom oversized' => [['mode' => 'custom', 'custom' => '110%'], '110%'],
            'custom' => [['mode' => 'custom', 'custom' => '72vw', 'side_margin' => '3rem'], '72vw'],
            'zero margin' => [['mode' => 'default', 'side_margin' => '0px'], null],
            'invalid width' => [['mode' => 'custom', 'custom' => 'red; color: red'], null],
        ];
    }

    public function test_index_column_width_modes_generate_css_overrides_and_reset_cleanly(): void
    {
        Option::setIndexFieldLayout([
            [
                'field' => ProductField::Tags->value,
                'visible' => true,
                'width_mode' => ProductFieldLayout::INDEX_WIDTH_MODE_DEFAULT,
            ],
            [
                'field' => ProductField::Score->value,
                'visible' => true,
                'width_mode' => ProductFieldLayout::INDEX_WIDTH_MODE_CUSTOM,
                'width' => '80px',
            ],
        ]);

        $stylesheet = app(OptionStylesheet::class);
        $css = file_get_contents($stylesheet->path());

        $this->assertStringNotContainsString('.header-title.tags', $css);
        $this->assertStringContainsString('.header-title.score', $css);
        $this->assertStringContainsString('width: 80px;', $css);
        $this->assertStringContainsString('min-width: 80px;', $css);

        Option::resetFieldLayoutsToDefault();
        $css = file_get_contents($stylesheet->path());

        $this->assertStringNotContainsString('.header-title.tags', $css);
        $this->assertStringNotContainsString('.header-title.score', $css);
    }

    public function test_overflow_targets_and_resets_regenerate_the_stylesheet(): void
    {
        Option::setIndexTableWidth(['mode' => 'wide', 'side_margin' => '60px']);
        Option::setIndexContentOverflow([
            'inline_notes' => ['enabled' => true, 'height' => '41px'],
            'notes_column' => ['enabled' => true, 'height' => '5rem'],
            'tags' => ['enabled' => false, 'height' => '20vh'],
        ]);

        $stylesheet = app(OptionStylesheet::class);
        $css = file_get_contents($stylesheet->path());
        $this->assertStringContainsString('data-overflow-target="inline_notes"', $css);
        $this->assertStringContainsString('--index-content-overflow-height: 41px;', $css);
        $this->assertStringContainsString('data-overflow-target="notes_column"', $css);
        $this->assertStringContainsString('--index-content-overflow-height: 5rem;', $css);
        $this->assertStringNotContainsString('data-overflow-target="tags"', $css);

        Option::resetIndexTableWidthToDefault();
        $css = file_get_contents($stylesheet->path());
        $this->assertStringNotContainsString('--index-table-', $css);
        $this->assertStringContainsString('--index-content-overflow-height: 41px;', $css);

        Option::resetIndexContentOverflowToDefault();
        $this->assertStringContainsString('No Options CSS overrides.', file_get_contents($stylesheet->path()));

        Option::setIndexTableWidth(['mode' => 'full']);
        Option::setIndexContentOverflow(['tags' => ['enabled' => true, 'height' => '20vh']]);
        Option::resetVisibleSettingsToDefault();
        $this->assertStringContainsString('No Options CSS overrides.', file_get_contents($stylesheet->path()));
    }

    public function test_import_regenerates_only_after_commit_and_rollback_preserves_the_file(): void
    {
        $stylesheet = app(OptionStylesheet::class);
        $original = file_get_contents($stylesheet->path());
        $options = app(PortableOptions::class);

        DB::transaction(function () use ($options, $stylesheet, $original): void {
            $options->apply(Option::INDEX_TABLE_WIDTH, ['mode' => 'wide']);
            $options->apply(Option::INDEX_CONTENT_OVERFLOW, ['tags' => ['enabled' => true, 'height' => '20vh']]);
            $options->apply(Option::INDEX_FIELD_LAYOUT, [[
                'field' => ProductField::Tags->value,
                'visible' => true,
                'custom_visible' => true,
                'fetched_visible' => true,
                'width_mode' => ProductFieldLayout::INDEX_WIDTH_MODE_CUSTOM,
                'width' => '320px',
            ]]);
            $this->assertSame($original, file_get_contents($stylesheet->path()));
        });

        $committed = file_get_contents($stylesheet->path());
        $this->assertStringContainsString('1400px', $committed);
        $this->assertStringContainsString('20vh', $committed);
        $this->assertStringContainsString('.header-title.tags', $committed);

        DB::beginTransaction();
        $options->apply(Option::INDEX_TABLE_WIDTH, ['mode' => 'full']);
        Option::resetIndexContentOverflowToDefault();
        $this->assertSame($committed, file_get_contents($stylesheet->path()));
        DB::rollBack();

        $this->assertSame($committed, file_get_contents($stylesheet->path()));
        $this->assertSame('wide', Option::indexTableWidth()['mode']);
    }

    public function test_unchanged_css_and_unrelated_options_do_not_replace_the_file(): void
    {
        Option::setIndexTableWidth(['mode' => 'wide']);
        $stylesheet = app(OptionStylesheet::class);
        $path = $stylesheet->path();
        touch($path, 1700000000);
        clearstatcache(true, $path);

        Option::setIndexTableWidth(['mode' => 'wide']);
        Option::setIndexPerPage(25);

        clearstatcache(true, $path);
        $this->assertSame(1700000000, filemtime($path));
    }

    public function test_index_loads_the_static_versioned_options_stylesheet_last(): void
    {
        $stylesheet = app(OptionStylesheet::class);
        clearstatcache(true, $stylesheet->path());
        $url = url('/styles/options.css') . '?v=' . filemtime($stylesheet->path());

        $this->get(route('index'))
            ->assertOk()
            ->assertSeeInOrder([
                asset('css/autocomplete.css'),
                $url,
            ], false);
    }

    public function test_stylesheet_uses_the_static_isolated_testing_storage_path(): void
    {
        $expectedStorage = dirname(__DIR__, 2) . '/storage/framework/testing/application';
        $expectedStylesheet = $expectedStorage . '/app/public/generated/options.css';

        $this->assertSame(
            str_replace('\\', '/', $expectedStorage),
            str_replace('\\', '/', storage_path()),
        );
        $this->assertSame(
            str_replace('\\', '/', $expectedStylesheet),
            str_replace('\\', '/', app(OptionStylesheet::class)->path()),
        );
    }

    public function test_migration_rebuilds_saved_options_and_down_removes_generated_file_and_directory(): void
    {
        Option::setIndexTableWidth(['mode' => 'wide']);
        $stylesheet = app(OptionStylesheet::class);
        $migration = require database_path('migrations/2026_10_24_000000_generate_options_stylesheet.php');
        $path = $stylesheet->path();
        $directory = dirname($path);

        $migration->down();
        $this->assertFileDoesNotExist($path);
        $this->assertDirectoryDoesNotExist($directory);

        $migration->up();
        $this->assertFileExists($path);
        $this->assertStringContainsString('1400px', file_get_contents($path));
    }

    public function test_write_failure_preserves_the_previous_file_and_surfaces_the_error(): void
    {
        Option::setIndexTableWidth(['mode' => 'wide']);
        $stylesheet = app(OptionStylesheet::class);
        $original = file_get_contents($stylesheet->path());
        $files = Mockery::mock(Filesystem::class)->makePartial();
        $files->shouldReceive('replace')->once()->andThrow(new RuntimeException('Cannot replace stylesheet.'));
        $this->app->instance(OptionStylesheet::class, new OptionStylesheet($files));

        try {
            Option::setIndexTableWidth(['mode' => 'full']);
            $this->fail('The write failure should be surfaced.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Cannot replace stylesheet.', $exception->getMessage());
        }

        $this->assertSame($original, file_get_contents($stylesheet->path()));
        $this->assertSame('full', Option::indexTableWidth()['mode']);
    }

    public function test_stylesheet_route_serves_the_generated_file_and_uses_optional_authentication(): void
    {
        $stylesheet = app(OptionStylesheet::class);
        $response = $this->get(route('styles.options'))->assertOk();
        $baseResponse = $response->baseResponse;

        $this->assertInstanceOf(BinaryFileResponse::class, $baseResponse);
        $this->assertSame(realpath($stylesheet->path()), realpath($baseResponse->getFile()->getPathname()));
        $response->assertHeader('Content-Type', 'text/css; charset=UTF-8');

        $user = User::factory()->create();
        Option::setUserAuthenticationEnabled(true);

        $this->get(route('styles.options'))->assertRedirect(route('login'));
        $this->actingAs($user)->get(route('styles.options'))->assertOk();
    }
}
