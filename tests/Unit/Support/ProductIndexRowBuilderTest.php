<?php

namespace Tests\Unit\Support;

use App\Enums\RatingScale;
use App\Enums\RatingVisualStyle;
use App\Models\Product;
use App\Support\ProductIndexContributorRow;
use App\Support\ProductIndexRow;
use App\Support\ProductIndexRowBuilder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use InvalidArgumentException;
use Tests\TestCase;

class ProductIndexRowBuilderTest extends TestCase
{
    public function test_it_builds_typed_rows_with_encoded_filter_and_return_urls(): void
    {
        $product = new ProductWithIndexAccessor;
        $product->setRawAttributes([
            'id' => 'RJ000000321',
            'work_name' => 'Typed Row',
            'work_name_english' => 'Typed Row English',
            'notes' => 'Typed notes',
            'progress' => 'Listening',
            'work_image' => '/images/typed-row.jpg',
            'score' => 9,
            'dlsite_rating_5' => '4.97',
            'dlsite_rating_10' => '9.93',
            'dlsite_rating_count' => 1330,
            'series' => 'Series & One',
            'age_category' => 'ALL_AGES',
            'circle' => 'Circle / One',
            'maker_id' => 'RG 321',
            'description' => 'Japanese description',
            'description_english' => 'English description',
        ]);

        $contributorsByProductId = collect([
            $product->getKey() => collect([
                'voice_actor' => collect([
                    (object) [
                        'name' => 'Voice / Actor & Co',
                        'maker_id' => 'VA 1',
                    ],
                ]),
            ]),
        ]);

        $row = app(ProductIndexRowBuilder::class)->build(
            collect([$product]),
            $contributorsByProductId,
            ['rating'],
            true,
            [
                'search' => 'rain & snow',
                'page' => '2',
            ],
            RatingScale::Ten,
            RatingVisualStyle::ExtendedDLSite,
        )->sole();

        $this->assertInstanceOf(ProductIndexRow::class, $row);
        $this->assertSame('RJ000000321', $row->id);
        $this->assertSame('TYPED ROW', $row->workName);
        $this->assertSame('Typed Row English', $row->workNameEnglish);
        $this->assertSame('9', $row->score);
        $this->assertSame('9.93', $row->dlsiteRating);
        $this->assertSame(90, $row->scoreStarFill);
        $this->assertSame(100, $row->dlsiteStarFill);
        $this->assertSame('1,330', $row->dlsiteVotes);
        $this->assertSame('/?series=Series%20%26%20One', $row->seriesUrl);
        $this->assertSame('/?circle=Circle%20%2F%20One', $row->circleUrl);
        $this->assertSame(
            'https://www.dlsite.com/home/work/=/product_id/RJ000000321.html',
            $row->dlsiteWorkUrl,
        );
        $this->assertSame(
            '/edit/RJ000000321?return_query%5Bsearch%5D=rain%20%26%20snow'
                . '&return_query%5Bpage%5D=2&return_fragment=RJ000000321',
            $row->editUrl,
        );

        $contributor = $row->contributors['voice_actor']->sole();

        $this->assertInstanceOf(ProductIndexContributorRow::class, $contributor);
        $this->assertSame('Voice / Actor & Co', $contributor->name);
        $this->assertSame('VA 1', $contributor->makerId);
        $this->assertSame(
            '/?voice_actor=Voice%20%2F%20Actor%20%26%20Co',
            $contributor->indexUrl,
        );

        $fivePointRow = app(ProductIndexRowBuilder::class)->build(
            collect([$product]),
            $contributorsByProductId,
            ['score', 'dlsite_rating'],
            true,
            [],
            RatingScale::Five,
            RatingVisualStyle::Simple,
        )->sole();

        $this->assertSame('4.5', $fivePointRow->score);
        $this->assertSame('4.97', $fivePointRow->dlsiteRating);
        $this->assertNull($fivePointRow->scoreStarFill);
        $this->assertNull($fivePointRow->dlsiteStarFill);
        $this->assertNull($fivePointRow->dlsiteVotes);

        $hiddenRatingRow = app(ProductIndexRowBuilder::class)->build(
            collect([$product]),
            $contributorsByProductId,
            [],
            true,
            [],
            RatingScale::Ten,
            RatingVisualStyle::ExtendedDLSite,
        )->sole();

        $this->assertNull($hiddenRatingRow->score);
        $this->assertNull($hiddenRatingRow->dlsiteRating);
        $this->assertNull($hiddenRatingRow->scoreStarFill);
        $this->assertNull($hiddenRatingRow->dlsiteStarFill);
        $this->assertNull($hiddenRatingRow->dlsiteVotes);
    }

    public function test_it_rounds_star_fills_up_to_the_next_half_star(): void
    {
        $ratings = [
            '0.00' => 0,
            '3.00' => 60,
            '3.01' => 70,
            '3.50' => 70,
            '3.51' => 80,
            '4.00' => 80,
            '4.01' => 90,
            '4.50' => 90,
            '4.51' => 100,
            '4.73' => 100,
            '5.00' => 100,
        ];

        foreach ($ratings as $ratingFive => $expectedFill) {
            $product = new Product;
            $product->setRawAttributes([
                'id' => 'RJ000000001',
                'work_name' => 'Half Star Test',
                'score' => 9,
                'dlsite_rating_5' => $ratingFive,
                'dlsite_rating_10' => '9.00',
            ]);

            $row = app(ProductIndexRowBuilder::class)->build(
                collect([$product]),
                collect(),
                ['rating'],
                false,
                [],
                RatingScale::Ten,
                RatingVisualStyle::DLSite,
            )->sole();

            $this->assertSame(90, $row->scoreStarFill);
            $this->assertSame($expectedFill, $row->dlsiteStarFill, "Unexpected fill for rating {$ratingFive}");
        }
    }

    public function test_it_defaults_unhydrated_optional_attributes_without_changing_required_fields(): void
    {
        $product = new Product;
        $product->setRawAttributes([
            'id' => 'RJ000000654',
            'work_name' => 'Narrow Row',
            'work_name_english' => null,
            'notes' => null,
            'progress' => null,
        ]);

        $row = app(ProductIndexRowBuilder::class)->build(
            collect([$product]),
            collect(),
            [],
            false,
            [],
            RatingScale::Ten,
            RatingVisualStyle::DLSite,
        )->sole();

        $this->assertSame('RJ000000654', $row->id);
        $this->assertSame('Narrow Row', $row->workName);
        $this->assertNull($row->workNameEnglish);
        $this->assertNull($row->notes);
        $this->assertNull($row->progress);
        $this->assertNull($row->workImage);
        $this->assertNull($row->score);
        $this->assertNull($row->dlsiteRating);
        $this->assertNull($row->scoreStarFill);
        $this->assertNull($row->dlsiteStarFill);
        $this->assertNull($row->dlsiteVotes);
        $this->assertNull($row->series);
        $this->assertNull($row->ageCategory);
        $this->assertNull($row->circle);
        $this->assertNull($row->makerId);
        $this->assertNull($row->description);
        $this->assertNull($row->descriptionEnglish);
        $this->assertTrue($row->contributors->isEmpty());
        $this->assertNull($row->seriesUrl);
        $this->assertNull($row->circleUrl);
        $this->assertSame(
            'https://www.dlsite.com/maniax/work/=/product_id/RJ000000654.html',
            $row->dlsiteWorkUrl,
        );
        $this->assertSame(
            '/edit/RJ000000654?return_fragment=RJ000000654',
            $row->editUrl,
        );

        $product->forceFill([
            'dlsite_rating_5' => '0.00',
            'dlsite_rating_10' => '0.00',
            'dlsite_rating_count' => 0,
        ]);
        $zeroRatingRow = app(ProductIndexRowBuilder::class)->build(
            collect([$product]),
            collect(),
            ['dlsite_rating'],
            false,
            [],
            RatingScale::Five,
            RatingVisualStyle::ExtendedDLSite,
        )->sole();

        $this->assertNull($zeroRatingRow->score);
        $this->assertNull($zeroRatingRow->scoreStarFill);
        $this->assertSame('0.0', $zeroRatingRow->dlsiteRating);
        $this->assertSame(0, $zeroRatingRow->dlsiteStarFill);
        $this->assertSame('0', $zeroRatingRow->dlsiteVotes);
    }

    public function test_it_rejects_a_row_when_the_required_work_name_was_not_hydrated(): void
    {
        $product = new Product;
        $product->setRawAttributes([
            'id' => 'RJ000000987',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Array value for key [work_name] must be a string');

        app(ProductIndexRowBuilder::class)->build(
            collect([$product]),
            collect(),
            [],
            false,
            [],
            RatingScale::Ten,
            RatingVisualStyle::Simple,
        );
    }
}

class ProductWithIndexAccessor extends Product
{
    protected function workName(): Attribute
    {
        return Attribute::make(
            get: fn(string $value): string => strtoupper($value),
        );
    }
}
