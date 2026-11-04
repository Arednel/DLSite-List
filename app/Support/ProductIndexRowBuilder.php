<?php

namespace App\Support;

use App\Enums\ProductField;
use App\Enums\RatingScale;
use App\Enums\RatingVisualStyle;
use App\Models\Product;
use App\Support\DLSite\DLSiteRating;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

final readonly class ProductIndexRowBuilder
{
    public function __construct(private UrlGenerator $url) {}

    /**
     * @param  Collection<int, Product>  $products
     * @param  Collection<string, Collection<string, Collection<int, object>>>  $contributorsByProductId
     * @param  list<string>  $visibleFields
     * @param  array<string, string>  $returnQuery
     * @return Collection<int, ProductIndexRow>
     */
    public function build(
        Collection $products,
        Collection $contributorsByProductId,
        array $visibleFields,
        bool $ageAppropriateLinksEnabled,
        array $returnQuery,
        RatingScale $ratingScale,
        RatingVisualStyle $ratingVisualStyle,
        bool $announceLinksEnabled = true,
    ): Collection {
        $indexUrl = $this->url->route('index', absolute: false);
        $scoreVisible = in_array(ProductField::Score->value, $visibleFields, true)
            || in_array(ProductField::Rating->value, $visibleFields, true);
        $dlsiteRatingVisible = in_array(ProductField::DLSiteRating->value, $visibleFields, true)
            || in_array(ProductField::Rating->value, $visibleFields, true);
        $showStars = $ratingVisualStyle->showsStars();
        $showScoreStars = $scoreVisible && $showStars;
        $showDLSiteStars = $dlsiteRatingVisible && $showStars;
        $showVoteCount = $dlsiteRatingVisible && $ratingVisualStyle->showsVoteCount();
        $ratingColumn = $dlsiteRatingVisible ? $ratingScale->dlsiteColumn() : null;

        return $products->map(function (Product $product) use (
            $ageAppropriateLinksEnabled,
            $announceLinksEnabled,
            $contributorsByProductId,
            $indexUrl,
            $returnQuery,
            $ratingScale,
            $scoreVisible,
            $showScoreStars,
            $showDLSiteStars,
            $showVoteCount,
            $ratingColumn,
        ): ProductIndexRow {
            $attributes = $product->attributesToArray();
            $id = Arr::string($attributes, 'id');
            $series = $attributes['series'] ?? null;
            $circle = $attributes['circle'] ?? null;
            $publisher = $attributes['publisher'] ?? null;
            $brand = $attributes['brand'] ?? null;
            $makerNames = collect([$circle, $publisher, $brand])
                ->filter(fn(?string $name): bool => filled($name))
                ->map(fn(string $name): array => [
                    'name' => $name,
                    'indexUrl' => $this->indexFilterUrl($indexUrl, 'maker_names', $name),
                ])
                ->values()
                ->all();
            $workImage = $attributes['work_image'] ?? null;
            $score = $scoreVisible && isset($attributes['score'])
                ? (int) $attributes['score']
                : null;
            $dlsiteRating = $ratingColumn !== null
                ? ($attributes[$ratingColumn] ?? null)
                : null;

            return new ProductIndexRow(
                id: $id,
                workName: Arr::string($attributes, 'work_name'),
                workNameEnglish: $attributes['work_name_english'] ?? null,
                notes: $attributes['notes'] ?? null,
                progress: $attributes['progress'] ?? null,
                workImage: blank($workImage)
                    ? null
                    : Product::versionedImagePath($workImage),
                score: $ratingScale->displayScore($score),
                dlsiteRating: DLSiteRating::displayValue($dlsiteRating),
                scoreStarFill: $showScoreStars ? ($score ?? 0) * 10 : null,
                dlsiteStarFill: $showDLSiteStars
                    ? $this->dlsiteStarFill($attributes['dlsite_rating_5'] ?? null)
                    : null,
                dlsiteVotes: $showVoteCount && isset($attributes['dlsite_rating_count'])
                    ? number_format((int) $attributes['dlsite_rating_count'])
                    : null,
                series: $series,
                ageCategory: $attributes['age_category'] ?? null,
                circle: $circle,
                publisher: $publisher,
                brand: $brand,
                makerNames: $makerNames,
                makerId: $attributes['maker_id'] ?? null,
                description: $attributes['description'] ?? null,
                descriptionEnglish: $attributes['description_english'] ?? null,
                contributors: $this->buildContributors(
                    $contributorsByProductId->get($id) ?? collect(),
                    $indexUrl,
                ),
                dlsiteWorkUrl: $product->dlsiteWorkUrl($ageAppropriateLinksEnabled, $announceLinksEnabled),
                editUrl: $this->url->route('products.edit', [
                    'product' => $id,
                    'return_query' => $returnQuery,
                    'return_fragment' => $id,
                ], false),
                seriesUrl: $series === null || $series === ''
                    ? null
                    : $this->indexFilterUrl($indexUrl, 'series', $series),
                circleUrl: empty($circle)
                    ? null
                    : $this->indexFilterUrl($indexUrl, 'circle', $circle),
                publisherUrl: blank($publisher)
                    ? null
                    : $this->indexFilterUrl($indexUrl, 'publisher', $publisher),
                brandUrl: blank($brand)
                    ? null
                    : $this->indexFilterUrl($indexUrl, 'brand', $brand),
            );
        });
    }

    private function dlsiteStarFill(string|int|float|null $ratingFive): int
    {
        if ($ratingFive === null) {
            return 0;
        }

        $halfUnits = max(0, min(10, (int) round(((float) $ratingFive) * 2, 0, PHP_ROUND_HALF_UP)));

        return $halfUnits * 10;
    }

    /**
     * @return Collection<string, Collection<int, ProductIndexContributorRow>>
     */
    private function buildContributors(Collection $contributorsByRole, string $indexUrl): Collection
    {
        return $contributorsByRole->map(
            fn(Collection $contributors, string $role): Collection => $contributors
                ->map(function (object $contributor) use ($indexUrl, $role): ProductIndexContributorRow {
                    $name = (string) $contributor->name;

                    return new ProductIndexContributorRow(
                        name: $name,
                        makerId: $contributor->maker_id ?? null,
                        indexUrl: $this->indexFilterUrl($indexUrl, $role, $name),
                    );
                }),
        );
    }

    private function indexFilterUrl(string $indexUrl, string $field, string $value): string
    {
        return $indexUrl . '?' . Arr::query([$field => $value]);
    }
}
