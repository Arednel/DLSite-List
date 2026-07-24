<?php

namespace Tests\Unit\Support;

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
            'score' => 8,
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
            true,
            [
                'search' => 'rain & snow',
                'page' => '2',
            ],
        )->sole();

        $this->assertInstanceOf(ProductIndexRow::class, $row);
        $this->assertSame('RJ000000321', $row->id);
        $this->assertSame('TYPED ROW', $row->workName);
        $this->assertSame('Typed Row English', $row->workNameEnglish);
        $this->assertSame(8, $row->score);
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
            false,
            [],
        )->sole();

        $this->assertSame('RJ000000654', $row->id);
        $this->assertSame('Narrow Row', $row->workName);
        $this->assertNull($row->workNameEnglish);
        $this->assertNull($row->notes);
        $this->assertNull($row->progress);
        $this->assertNull($row->workImage);
        $this->assertNull($row->score);
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
            false,
            [],
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
