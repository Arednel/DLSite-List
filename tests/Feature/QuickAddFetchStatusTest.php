<?php

namespace Tests\Feature;

use App\Models\Option;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QuickAddFetchStatusTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('dlsitePresentationProvider')]
    public function test_dlsite_quick_add_renders_the_fetch_status_contract(
        bool $modal,
        string $theme,
        string $locale,
    ): void {
        Option::setProductFormTheme($theme);
        Option::setUiLanguage($locale);
        $path = '/create' . ($modal ? '?modal=1' : '');

        $response = $this->get($path)
            ->assertOk()
            ->assertSee('<html lang="' . $locale . '" class="product-form-theme-' . $theme . '">', false)
            ->assertSee('data-dlsite-fetch-form', false)
            ->assertSee(
                'data-dlsite-fetch-status role="status" aria-live="polite" hidden',
                false,
            )
            ->assertSee(trans('Data is being fetched...', locale: $locale))
            ->assertSee('scripts/dlsite-create-status.js', false);

        $this->assertTwoEnabledSubmitButtons(
            $response->getContent(),
            trans('Add work', locale: $locale),
        );
    }

    public static function dlsitePresentationProvider(): iterable
    {
        yield 'standalone Cherry English' => [
            false,
            Option::PRODUCT_FORM_THEME_CHERRY,
            'en',
        ];

        yield 'standalone Black Japanese' => [
            false,
            Option::PRODUCT_FORM_THEME_BLACK,
            'ja',
        ];

        yield 'modal Black English' => [
            true,
            Option::PRODUCT_FORM_THEME_BLACK,
            'en',
        ];

        yield 'modal Cherry Japanese' => [
            true,
            Option::PRODUCT_FORM_THEME_CHERRY,
            'ja',
        ];
    }

    #[DataProvider('modalProvider')]
    public function test_custom_quick_add_omits_the_fetch_status_contract(bool $modal): void
    {
        $path = '/create/custom' . ($modal ? '?modal=1' : '');

        $response = $this->get($path)
            ->assertOk()
            ->assertDontSee('data-dlsite-fetch-form', false)
            ->assertDontSee('data-dlsite-fetch-status', false)
            ->assertDontSee('Data is being fetched...')
            ->assertDontSee('scripts/dlsite-create-status.js', false);

        $this->assertTwoEnabledSubmitButtons($response->getContent(), __('Add work'));
    }

    public static function modalProvider(): iterable
    {
        yield 'standalone' => [false];
        yield 'modal' => [true];
    }

    public function test_laravel_validation_reload_hides_the_fetch_status_and_shows_the_error(): void
    {
        $this->followingRedirects()
            ->from('/create')
            ->post('/store', ['id' => 'not-an-rj'])
            ->assertOk()
            ->assertSee(
                'data-dlsite-fetch-status role="status" aria-live="polite" hidden',
                false,
            )
            ->assertSee('class="text-error"', false)
            ->assertSee('Could not find an RJ code (format: RJ + numbers) in your input.');
    }

    private function assertTwoEnabledSubmitButtons(string $html, string $label): void
    {
        preg_match_all('/<input\b[^>]*\btype="submit"[^>]*>/i', $html, $matches);

        $this->assertCount(2, $matches[0]);

        foreach ($matches[0] as $submitButton) {
            $this->assertStringContainsString('value="' . e($label) . '"', $submitButton);
            $this->assertDoesNotMatchRegularExpression(
                '/\sdisabled(?:\s|=|>)/i',
                $submitButton,
            );
        }
    }
}
