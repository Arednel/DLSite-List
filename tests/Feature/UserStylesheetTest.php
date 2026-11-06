<?php

namespace Tests\Feature;

use App\Livewire\CustomCssSettings;
use App\Livewire\OptionsResetDefaults;
use App\Models\Option;
use App\Support\UserStylesheet;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class UserStylesheetTest extends TestCase
{
    use RefreshDatabase;

    private string $originalCss;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalCss = app(UserStylesheet::class)->contents();
    }

    protected function tearDown(): void
    {
        app(UserStylesheet::class)->save($this->originalCss);

        parent::tearDown();
    }

    public function test_custom_css_component_loads_saved_stylesheet_without_rendering_it_as_raw_html(): void
    {
        $stylesheet = app(UserStylesheet::class);
        $css = "</textarea><script>alert('x')</script>\nbody { color: blue; }\n";
        $stylesheet->save($css);

        $component = Livewire::test(CustomCssSettings::class)
            ->assertSet('css', $css);

        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">' . $component->html());
        $xpath = new DOMXPath($document);

        $textarea = $xpath->query('//textarea[@id="custom-css"]')->item(0);

        $this->assertNotNull($textarea);
        $this->assertSame('css', $textarea->getAttribute('wire:model'));
        $this->assertSame(0, $xpath->query('//script')->length);
    }

    public function test_custom_css_component_saves_the_stylesheet_verbatim(): void
    {
        $stylesheet = app(UserStylesheet::class);
        $updated = "body { color: blue; }\n";

        Livewire::test(CustomCssSettings::class)
            ->set('css', $updated)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirectToRoute('options.index', ['tab' => 'display']);

        $this->assertSame($updated, file_get_contents($stylesheet->path()));
    }

    public function test_custom_css_reset_empties_the_stylesheet(): void
    {
        $stylesheet = app(UserStylesheet::class);
        $stylesheet->save("body { color: red; }\n");

        Livewire::test(CustomCssSettings::class)
            ->call('askResetToDefault')
            ->assertSet('confirmingResetToDefault', true)
            ->call('resetToDefault')
            ->assertRedirectToRoute('options.index', ['tab' => 'display']);

        $this->assertSame('', file_get_contents($stylesheet->path()));
    }

    public function test_visible_settings_reset_also_clears_custom_css(): void
    {
        $stylesheet = app(UserStylesheet::class);
        $stylesheet->save("body { color: red; }\n");

        Option::resetVisibleSettingsToDefault();

        $this->assertSame('', file_get_contents($stylesheet->path()));
    }

    public function test_reset_all_options_also_clears_custom_css(): void
    {
        $stylesheet = app(UserStylesheet::class);
        $stylesheet->save("body { color: red; }\n");

        Livewire::test(OptionsResetDefaults::class, ['activeTab' => 'display'])
            ->call('resetAll')
            ->assertRedirectToRoute('options.index', ['tab' => 'display']);

        $this->assertSame('', file_get_contents($stylesheet->path()));
    }

    public function test_user_stylesheet_route_serves_css_without_optional_authentication(): void
    {
        $stylesheet = app(UserStylesheet::class);
        $stylesheet->save("body { color: red; }\n");
        Option::setUserAuthenticationEnabled(true);

        $response = $this->get(route('styles.user'))->assertOk();
        $baseResponse = $response->baseResponse;

        $this->assertInstanceOf(BinaryFileResponse::class, $baseResponse);
        $this->assertSame(realpath($stylesheet->path()), realpath($baseResponse->getFile()->getPathname()));
        $response->assertHeader('Content-Type', 'text/css; charset=UTF-8');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_visible_pages_load_user_stylesheet_last_among_stylesheets(): void
    {
        $expected = "href=\"{{ url('/styles/user.css') }}?v={{ filemtime(storage_path('app/public/generated/user.css')) }}\"";
        $views = [
            resource_path('views/Index.blade.php'),
            resource_path('views/Create.blade.php'),
            resource_path('views/Edit.blade.php'),
            resource_path('views/TagLibrary.blade.php'),
            resource_path('views/Options.blade.php'),
            resource_path('views/OptionsRefetch.blade.php'),
            resource_path('views/OptionsTransfer.blade.php'),
            resource_path('views/layouts/auth.blade.php'),
            resource_path('views/WorkFormCompleted.blade.php'),
        ];

        foreach ($views as $view) {
            $contents = file_get_contents($view);
            $userStylesheetPosition = strpos($contents, '/styles/user.css');

            $this->assertStringContainsString($expected, $contents, $view);
            $this->assertNotFalse($userStylesheetPosition, $view);
            $this->assertStringNotContainsString(
                '<link rel="stylesheet"',
                substr($contents, $userStylesheetPosition),
                $view,
            );

            $livewireStylesPosition = strpos($contents, '@livewireStyles');
            if ($livewireStylesPosition !== false) {
                $this->assertLessThan($livewireStylesPosition, $userStylesheetPosition, $view);
            }
        }

        $index = file_get_contents(resource_path('views/Index.blade.php'));
        $this->assertLessThan(
            strpos($index, '/styles/user.css'),
            strpos($index, '/styles/options.css'),
        );
    }

    public function test_user_stylesheet_migration_creates_and_removes_the_file(): void
    {
        $stylesheet = app(UserStylesheet::class);
        $migration = require database_path('migrations/2026_11_05_000000_create_user_stylesheet.php');

        $migration->down();
        $this->assertFileDoesNotExist($stylesheet->path());

        $migration->up();
        $this->assertFileExists($stylesheet->path());
        $this->assertSame('', file_get_contents($stylesheet->path()));
    }
}
