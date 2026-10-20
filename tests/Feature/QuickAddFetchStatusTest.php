<?php

namespace Tests\Feature;

use App\Models\Option;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QuickAddFetchStatusTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('dlsitePresentationProvider')]
    public function test_dlsite_quick_add_renders_the_fetch_status_contract(
        bool $modal,
        string $locale,
    ): void {
        Option::setUiLanguage($locale);
        $path = '/create' . ($modal ? '?modal=1' : '');

        $response = $this->get($path)
            ->assertOk()
            ->assertSee(trans('Data is being fetched...', locale: $locale));
        $xpath = $this->htmlXPath($response->getContent());

        $this->assertCount(1, $xpath->query('//*[@data-dlsite-fetch-form]'));
        $this->assertCount(1, $xpath->query('//script[contains(@src, "scripts/dlsite-create-status.js")]'));
        $statuses = $xpath->query('//*[@data-dlsite-fetch-status]');
        $this->assertCount(1, $statuses);
        $status = $statuses->item(0);
        $this->assertInstanceOf(DOMElement::class, $status);
        $this->assertSame('status', $status->getAttribute('role'));
        $this->assertSame('polite', $status->getAttribute('aria-live'));
        $this->assertTrue($status->hasAttribute('hidden'));
    }

    public static function dlsitePresentationProvider(): iterable
    {
        yield 'standalone English' => [false, 'en'];
        yield 'standalone Japanese' => [false, 'ja'];
        yield 'modal English' => [true, 'en'];
        yield 'modal Japanese' => [true, 'ja'];
    }

    #[DataProvider('modalProvider')]
    public function test_custom_quick_add_omits_the_fetch_status_contract(bool $modal): void
    {
        $path = '/create/custom' . ($modal ? '?modal=1' : '');

        $response = $this->get($path)
            ->assertOk()
            ->assertDontSee('Data is being fetched...');
        $xpath = $this->htmlXPath($response->getContent());

        $this->assertCount(0, $xpath->query('//*[@data-dlsite-fetch-form]'));
        $this->assertCount(0, $xpath->query('//*[@data-dlsite-fetch-status]'));
        $this->assertCount(0, $xpath->query('//script[contains(@src, "scripts/dlsite-create-status.js")]'));
    }

    public static function modalProvider(): iterable
    {
        yield 'standalone' => [false];
        yield 'modal' => [true];
    }

    public function test_laravel_validation_reload_hides_the_fetch_status_and_shows_the_error(): void
    {
        $response = $this->followingRedirects()
            ->from('/create')
            ->post('/store', ['id' => 'not-a-product'])
            ->assertOk()
            ->assertSee('Could not find a product code (RJ, BJ or VJ followed by numbers) in your input.');
        $xpath = $this->htmlXPath($response->getContent());
        $statuses = $xpath->query('//*[@data-dlsite-fetch-status]');

        $this->assertCount(1, $statuses);
        $status = $statuses->item(0);
        $this->assertInstanceOf(DOMElement::class, $status);
        $this->assertTrue($status->hasAttribute('hidden'));
    }

    private function htmlXPath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">' . $html);

        return new DOMXPath($document);
    }
}
