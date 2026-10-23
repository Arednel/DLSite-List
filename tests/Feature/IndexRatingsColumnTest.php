<?php

namespace Tests\Feature;

use App\Enums\ProductField;
use App\Enums\ProductIndexSortField;
use App\Models\Option;
use App\Models\Product;
use App\Support\ProductFieldLayout;
use App\Support\ProductIndexFilters;
use App\Support\ProductIndexResults;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IndexRatingsColumnTest extends TestCase
{
    use RefreshDatabase;

    public function test_column_shows_the_dlsite_rating_above_the_personal_score(): void
    {
        $product = Product::factory()->create([
            'score' => '9',
            'dlsite_rating' => 4.96,
            'dlsite_rating_count' => 57,
        ]);

        $html = $this->get('/')->assertOk()->getContent();
        $xpath = $this->xpath($html);

        $this->assertSame([
            ['DLsite', '100%', '4.96', 'DLsite rating 4.96 out of 5'],
            ['Mine', '90%', '9', 'Your Score 9 out of 10'],
        ], $this->ratingRows($xpath, $product));
        $this->assertStringContainsString('css/star-rating.css', $html);
        $this->assertStringNotContainsString('/ 10', $this->scoreCell($xpath, $product)->textContent);
    }

    public function test_column_shows_only_the_rating_that_exists(): void
    {
        $dlsiteOnly = Product::factory()->create(['dlsite_rating' => 4.24, 'dlsite_rating_count' => 554]);
        $scoreOnly = Product::factory()->create(['score' => '3']);

        $xpath = $this->xpath($this->get('/')->assertOk()->getContent());

        $this->assertSame(
            [['DLsite', '80%', '4.24', 'DLsite rating 4.24 out of 5']],
            $this->ratingRows($xpath, $dlsiteOnly),
        );
        $this->assertSame(
            [['Mine', '30%', '3', 'Your Score 3 out of 10']],
            $this->ratingRows($xpath, $scoreOnly),
        );
    }

    public function test_column_shows_a_dash_without_ratings_and_ignores_an_average_without_a_count(): void
    {
        $unrated = Product::factory()->create();
        $averageOnly = Product::factory()->create(['dlsite_rating' => 4.5, 'dlsite_rating_count' => null]);
        $averageOnlyWithScore = Product::factory()->create([
            'score' => '7',
            'dlsite_rating' => 4.5,
            'dlsite_rating_count' => null,
        ]);

        $xpath = $this->xpath($this->get('/')->assertOk()->getContent());

        foreach ([$unrated, $averageOnly] as $product) {
            $this->assertSame([], $this->ratingRows($xpath, $product), $product->id);
            $this->assertSame('-', trim($this->scoreCell($xpath, $product)->textContent), $product->id);
        }

        $this->assertSame(
            [['Mine', '70%', '7', 'Your Score 7 out of 10']],
            $this->ratingRows($xpath, $averageOnlyWithScore),
        );
    }

    public function test_column_is_titled_ratings_and_still_sorts_by_the_personal_score(): void
    {
        $product = Product::factory()->create(['score' => '8']);

        $xpath = $this->xpath($this->get('/')->assertOk()->getContent());
        $header = $xpath->query('//th[contains(@class, "score")]')->item(0);

        $this->assertInstanceOf(DOMElement::class, $header);
        $this->assertSame('Ratings', $header->getAttribute('data-column'));
        $this->assertStringContainsString("sortByHeader('score')", $xpath->query('.//button', $header)->item(0)->getAttribute('wire:click'));
        $this->assertSame('Ratings', $this->scoreCell($xpath, $product)->getAttribute('data-label'));

        // The shared "Score" text keeps its meaning on the other surfaces.
        $this->assertSame('Score', ProductField::Score->label());
        $this->assertSame('Score', ProductIndexSortField::Score->label());
        $this->assertSame('Score', $this->layoutLabel(ProductFieldLayout::SURFACE_FILTER));
        $this->assertSame('Score', $this->layoutLabel(ProductFieldLayout::SURFACE_EDIT));
        $this->assertSame('Ratings', $this->layoutLabel(ProductFieldLayout::SURFACE_INDEX));
    }

    public function test_index_hydrates_the_dlsite_rating_only_with_the_ratings_column(): void
    {
        Product::factory()->create(['score' => '8', 'dlsite_rating' => 4.5, 'dlsite_rating_count' => 10]);
        $results = app(ProductIndexResults::class);

        $visible = $results->getProducts(new ProductIndexFilters, Option::INDEX_PER_PAGE_UNLIMITED, [
            ProductField::Score->value,
        ])->sole()->getAttributes();
        $hidden = $results->getProducts(new ProductIndexFilters, Option::INDEX_PER_PAGE_UNLIMITED, [
            ProductField::Series->value,
        ])->sole()->getAttributes();

        $this->assertArrayHasKey('dlsite_rating', $visible);
        $this->assertArrayHasKey('dlsite_rating_count', $visible);
        $this->assertArrayNotHasKey('dlsite_rating', $hidden);
        $this->assertArrayNotHasKey('dlsite_rating_count', $hidden);
    }

    /**
     * @return iterable<string, array{string, string, string, string, string}> locale, column title, personal label, DLsite and personal screen reader text
     */
    public static function localizedRatings(): iterable
    {
        yield 'Japanese' => ['ja', '評価', '自分', 'DLsite評価 5点中4.96点', '評価 10点中9点'];
        yield 'Traditional Chinese' => ['zh_TW', '評分', '個人', 'DLsite 評價 4.96 分（滿分 5 分）', '你的分數 9 分（滿分 10 分）'];
    }

    #[DataProvider('localizedRatings')]
    public function test_column_text_is_localized(
        string $locale,
        string $title,
        string $mine,
        string $dlsiteDescription,
        string $scoreDescription,
    ): void {
        Option::setUiLanguage($locale);
        $product = Product::factory()->create([
            'score' => '9',
            'dlsite_rating' => 4.96,
            'dlsite_rating_count' => 57,
        ]);

        $xpath = $this->xpath($this->get('/')->assertOk()->getContent());

        $this->assertSame($title, $this->scoreCell($xpath, $product)->getAttribute('data-label'));
        $this->assertSame([
            ['DLsite', '100%', '4.96', $dlsiteDescription],
            [$mine, '90%', '9', $scoreDescription],
        ], $this->ratingRows($xpath, $product));
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">' . $html);

        return new DOMXPath($document);
    }

    private function scoreCell(DOMXPath $xpath, Product $product): DOMElement
    {
        $cell = $xpath->query('//tr[@id="' . $product->id . '"]/td[contains(concat(" ", @class, " "), " score ")]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $cell, $product->id);

        return $cell;
    }

    /**
     * @return list<array{string, string, string, string}> label, star fill, number and screen reader text per row
     */
    private function ratingRows(DOMXPath $xpath, Product $product): array
    {
        $rows = [];
        $text = fn(DOMElement $row, string $class): string => trim(
            (string) $xpath->query('.//*[contains(concat(" ", @class, " "), " ' . $class . ' ")]', $row)->item(0)?->textContent,
        );

        foreach ($xpath->query('.//*[contains(concat(" ", @class, " "), " star-rating ")]', $this->scoreCell($xpath, $product)) as $row) {
            $stars = $xpath->query('.//*[contains(@class, "star-rating__stars")]', $row)->item(0);
            preg_match('/--star-rating-fill: (\d+%)/', $stars->getAttribute('style'), $fill);

            $this->assertSame('true', $stars->getAttribute('aria-hidden'));
            $rows[] = [
                $text($row, 'star-rating__label'),
                $fill[1] ?? '',
                $text($row, 'star-rating__number'),
                $text($row, 'star-rating__description'),
            ];
        }

        return $rows;
    }

    private function layoutLabel(string $surface): string
    {
        return collect(ProductFieldLayout::normalize([], $surface))
            ->firstWhere('field', ProductField::Score->value)['label'];
    }
}
