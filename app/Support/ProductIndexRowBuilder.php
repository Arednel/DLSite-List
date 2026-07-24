<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

final readonly class ProductIndexRowBuilder
{
    public function __construct(private UrlGenerator $url) {}

    /**
     * @param  Collection<int, Product>  $products
     * @param  Collection<string, Collection<string, Collection<int, object>>>  $contributorsByProductId
     * @param  array<string, string>  $returnQuery
     * @return Collection<int, ProductIndexRow>
     */
    public function build(
        Collection $products,
        Collection $contributorsByProductId,
        bool $ageAppropriateLinksEnabled,
        array $returnQuery,
    ): Collection {
        $indexUrl = $this->url->route('index', absolute: false);

        return $products->map(function (Product $product) use (
            $ageAppropriateLinksEnabled,
            $contributorsByProductId,
            $indexUrl,
            $returnQuery,
        ): ProductIndexRow {
            $attributes = $product->attributesToArray();
            $id = Arr::string($attributes, 'id');
            $series = $attributes['series'] ?? null;
            $circle = $attributes['circle'] ?? null;

            return new ProductIndexRow(
                id: $id,
                workName: Arr::string($attributes, 'work_name'),
                workNameEnglish: $attributes['work_name_english'] ?? null,
                notes: $attributes['notes'] ?? null,
                progress: $attributes['progress'] ?? null,
                workImage: $attributes['work_image'] ?? null,
                score: isset($attributes['score']) ? (int) $attributes['score'] : null,
                series: $series,
                ageCategory: $attributes['age_category'] ?? null,
                circle: $circle,
                makerId: $attributes['maker_id'] ?? null,
                description: $attributes['description'] ?? null,
                descriptionEnglish: $attributes['description_english'] ?? null,
                contributors: $this->buildContributors(
                    $contributorsByProductId->get($id) ?? collect(),
                    $indexUrl,
                ),
                dlsiteWorkUrl: $product->dlsiteWorkUrl($ageAppropriateLinksEnabled),
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
            );
        });
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
