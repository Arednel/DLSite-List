<?php

namespace App\Support\Transfers;

use App\Enums\DlsiteLanguage;
use App\Enums\ProductContributorRole;
use App\Models\Genre;
use App\Models\GenreGroup;
use App\Models\Product;
use App\Support\DLSite\DLSiteRating;
use finfo;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class LibraryData
{
    public const UTC_DATE_PATTERN = '/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|\+00:00)\z/';

    public const FIELDS = [
        'titles' => [],
        'descriptions' => [],
        'details' => ['maker_id', 'site_id', 'publisher', 'brand', 'series', 'age_category', 'product_format', 'announce_date', 'notes', ...DLSiteRating::ATTRIBUTE_KEYS],
        'listening' => ['progress', 'score', 'priority', 'num_re_listen_times', 're_listen_value', 'start_date', 'end_date'],
    ];

    public const METADATA = ['title', 'description', 'hidden_on_index', 'color', 'text_color'];

    public const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'image/avif' => 'avif', 'image/bmp' => 'bmp', 'image/x-ms-bmp' => 'bmp'];

    public function work(Product $product, bool $images = false): array
    {
        $attributes = $product->attributesToArray();
        $attributes['announce_date'] = $product->announce_date?->format('Y-m-d H:i:s');
        $localized = $product->localizations()
            ->whereIn('locale', DlsiteLanguage::values())
            ->get(['locale', 'title', 'description'])
            ->keyBy('locale');

        $data = [
            'product_code' => strtoupper($product->id),
            'created_at' => $product->created_at?->utc()->toIso8601ZuluString(),
            'updated_at' => $product->updated_at?->utc()->toIso8601ZuluString(),
            'titles' => [DlsiteLanguage::Japanese->value => $product->work_name],
            'descriptions' => [DlsiteLanguage::Japanese->value => $product->description],
        ];
        foreach (DlsiteLanguage::cases() as $language) {
            if ($language === DlsiteLanguage::Japanese) {
                continue;
            }
            $data['titles'][$language->value] = $localized->get($language->value)?->title;
            $data['descriptions'][$language->value] = $localized->get($language->value)?->description;
        }
        foreach (Arr::except(self::FIELDS, ['titles', 'descriptions']) as $category => $fields) {
            $data[$category] = Arr::only($attributes, $fields);
        }
        $data['contributors'] = $this->category($product, 'contributors');
        $tagBuckets = $this->tagBucketsForProducts([$product->id]);
        $data['tags'] = $tagBuckets[$product->id];
        if ($images) {
            $data['cover'] = $this->imageCategory($product, 'cover');
            $data['sample_images'] = $this->imageCategory($product, 'sample_images');
        }

        return $data;
    }

    public function category(Product $product, string $category, bool $lock = false): array
    {
        if ($category === 'contributors') {
            $contributors = $product->contributors()
                ->when($lock, fn($query) => $query->lockForUpdate())
                ->orderBy('contributor_product.id')
                ->get(['contributors.id', 'contributors.name']);

            return $this->contributorValues($product, $contributors);
        }
        if ($category === 'tags') {
            $values = ['custom' => $this->tagBucket($product, 'custom', $lock)];

            foreach (DlsiteLanguage::cases() as $language) {
                $values[$language->value] = $this->tagBucket($product, $language->value, $lock);
            }

            return $values;
        }

        return Arr::only($product->attributesToArray(), self::FIELDS[$category] ?? []);
    }

    /** @return list<string> */
    public function tagBucket(Product $product, string $bucket, bool $lock = false): array
    {
        if ($bucket === 'custom') {
            return $product->customGenres()
                ->when($lock, fn($query) => $query->lockForUpdate())
                ->orderBy('genre_product.id')
                ->pluck('title')
                ->all();
        }

        $language = DlsiteLanguage::tryFrom($bucket);
        if ($language === null) {
            return [];
        }

        return $product->fetchedGenresForLanguage($language)
            ->when($lock, fn($query) => $query->lockForUpdate())
            ->orderBy('genre_product.id')
            ->pluck('title')
            ->all();
    }

    /** Serialize contributors that the caller already loaded in attachment order. */
    public function preorderedLoadedContributors(Product $product): array
    {
        if (! $product->relationLoaded('contributors')) {
            throw new \LogicException('Preordered contributor export relation is not loaded.');
        }

        return $this->contributorValues($product, $product->getRelation('contributors'));
    }

    private function contributorValues(Product $product, $contributors): array
    {
        $values = $contributors
            ->groupBy('pivot.role')
            ->map(fn($contributors): array => $contributors->pluck('name')->values()->all())
            ->all();
        $values = array_replace(array_fill_keys(array_column(ProductContributorRole::cases(), 'value'), []), $values);
        if ($values['circle'] === [] && filled($product->circle)) {
            $values['circle'] = [$product->circle];
        }

        return $values;
    }

    /**
     * @param list<string> $productIds
     * @return array<string, array<string, list<string>>>
     */
    public function tagBucketsForProducts(array $productIds): array
    {
        $emptyBuckets = array_fill_keys(['custom', ...DlsiteLanguage::values()], []);
        $buckets = array_fill_keys($productIds, $emptyBuckets);
        if ($productIds === []) {
            return $buckets;
        }

        $rows = DB::table('genre_product')
            ->join('genres', 'genres.id', '=', 'genre_product.genre_id')
            ->leftJoin('genre_product_languages', function (JoinClause $join): void {
                $join->on('genre_product_languages.genre_product_id', '=', 'genre_product.id')
                    ->where('genre_product.source', Genre::PIVOT_SOURCE_FETCHED)
                    ->whereIn('genre_product_languages.language', DlsiteLanguage::values());
            })
            ->whereIn('genre_product.product_id', $productIds)
            ->orderBy('genre_product.id')
            ->get([
                'genre_product.product_id',
                'genre_product.id',
                'genre_product.source',
                'genre_product_languages.language',
                'genres.title',
            ]);

        foreach ($rows as $row) {
            if ($row->source === Genre::PIVOT_SOURCE_CUSTOM) {
                $buckets[$row->product_id]['custom'][] = $row->title;
            } elseif ($row->source === Genre::PIVOT_SOURCE_FETCHED && $row->language !== null) {
                $buckets[$row->product_id][$row->language][] = $row->title;
            }
        }

        return $buckets;
    }

    public function imageCategory(Product $product, string $category): array
    {
        return Arr::except($this->inspectImageCategory($product, $category), ['unavailable']);
    }

    public function inspectImageCategory(Product $product, string $category): array
    {
        $paths = $category === 'cover' ? array_filter([$product->work_image]) : ($product->sample_images ?? []);
        $files = [];
        $unavailable = [];
        $complete = true;
        foreach (array_values($paths) as $index => $path) {
            $label = $category === 'cover' ? 'cover image' : 'sample image ' . ($index + 1);
            if (! is_string($path)) {
                $complete = false;
                $unavailable[] = $label;

                continue;
            }
            $relative = str_starts_with($path, 'storage/') ? substr($path, 8) : '';
            $root = realpath(Storage::disk('public')->path('Works/' . $product->id));
            $publicRoot = realpath(Storage::disk('public')->path(''));
            $absolute = $relative === '' ? false : realpath(Storage::disk('public')->path($relative));
            if (! $root || ! $absolute || ! $publicRoot || ! str_starts_with($root, $publicRoot . DIRECTORY_SEPARATOR) || ! str_starts_with($absolute, $root . DIRECTORY_SEPARATOR) || ! is_file($absolute) || ! is_readable($absolute)) {
                $complete = false;
                $unavailable[] = $label;

                continue;
            }
            $mime = @(new finfo(FILEINFO_MIME_TYPE))->file($absolute);
            if (! isset(self::IMAGE_TYPES[$mime]) || @getimagesize($absolute) === false) {
                $complete = false;
                $unavailable[] = $label;

                continue;
            }
            $hash = @hash_file('sha256', $absolute);
            $bytes = @filesize($absolute);
            if ($hash === false || $bytes === false) {
                $complete = false;
                $unavailable[] = $label;

                continue;
            }
            $extension = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));
            if ($extension !== self::IMAGE_TYPES[$mime] && ! ($extension === 'jpeg' && $mime === 'image/jpeg')) {
                $extension = self::IMAGE_TYPES[$mime];
            }
            $files[] = ['path' => 'works/' . strtoupper($product->id) . '/images/' . ($category === 'cover' ? 'cover' : 'sample_' . ($index + 1)) . '.' . $extension, 'sha256' => $hash, 'bytes' => $bytes, 'media_type' => $mime, 'source' => $relative];
        }

        return ['complete' => $complete, 'files' => $files, 'unavailable' => $unavailable];
    }

    public function tagRecords(): iterable
    {
        foreach (Genre::query()->with('parents')->orderBy('title_key')->lazy(200) as $tag) {
            yield ['type' => 'tag', 'key' => Genre::titleKey($tag->title), 'value' => [...Arr::only($tag->attributesToArray(), self::METADATA), 'parents' => $tag->parents->pluck('title')->all()]];
        }
        foreach (GenreGroup::query()->ordered()->with('genres')->lazy(200) as $group) {
            yield ['type' => 'group', 'key' => Genre::titleKey($group->title), 'value' => [...Arr::only($group->attributesToArray(), self::METADATA), 'members' => $group->genres->pluck('title')->all()]];
        }
    }

    public static function hash(mixed $value): string
    {
        $canonical = function (mixed $value) use (&$canonical): mixed {
            if (! is_array($value)) {
                return $value;
            }
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map($canonical, $value);
        };

        return hash('sha256', json_encode($canonical($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
