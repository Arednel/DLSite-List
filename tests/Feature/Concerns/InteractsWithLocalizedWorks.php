<?php

namespace Tests\Feature\Concerns;

use App\Enums\DlsiteLanguage;
use App\Models\Genre;
use App\Models\Product;
use App\Support\ProductGenreSync;

trait InteractsWithLocalizedWorks
{
    private function workLocales(string $id, string $prefix): array
    {
        $locales = [];
        foreach (DlsiteLanguage::cases() as $language) {
            $code = $language->value;
            $locales[$code] = [
                'product_id' => $id,
                'work_name' => "{$prefix} title [{$code}]",
                'description' => "{$prefix} description [{$code}]",
                'genre' => ["{$prefix} tag [{$code}]"],
            ];
        }

        return $locales;
    }

    private function createLocalizedWork(array $locales): Product
    {
        $japanese = $locales['ja'];
        $product = Product::factory()->create([
            'id' => $japanese['product_id'],
            'work_name' => $japanese['work_name'],
            'description' => $japanese['description'],
        ]);
        $tags = [];
        foreach ($locales as $code => $fields) {
            if ($code !== 'ja') {
                $product->syncLocalization($code, [
                    'title' => $fields['work_name'],
                    'description' => $fields['description'],
                ]);
            }
            $tags[$code] = Genre::resolveIdsFromTitles($fields['genre']);
        }
        app(ProductGenreSync::class)->sync($product, $tags, Genre::resolveIdsFromTitles(['Custom tag']));

        return $product;
    }

    private function assertStoredWorkLocales(Product $product, array $expected): void
    {
        $product->refresh();
        $translations = $product->localizations()->get()->keyBy('locale');
        foreach ($expected as $code => $fields) {
            $japanese = $code === 'ja';
            $this->assertSame($fields['work_name'], $japanese ? $product->work_name : $translations->get($code)?->title, $code);
            $this->assertSame($fields['description'], $japanese ? $product->description : $translations->get($code)?->description, $code);
            $this->assertSame($fields['genre'], $product->fetchedGenresForLanguage($code)->pluck('title')->all(), $code);
        }
    }
}
