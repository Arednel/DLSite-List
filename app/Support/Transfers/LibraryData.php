<?php

namespace App\Support\Transfers;

use App\Enums\ProductContributorRole;
use App\Models\Genre;
use App\Models\GenreGroup;
use App\Models\Product;
use finfo;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

final class LibraryData
{
    public const UTC_DATE_PATTERN = '/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|\+00:00)\z/';

    public const FIELDS = [
        'titles' => ['work_name', 'work_name_english'],
        'descriptions' => ['description', 'description_english'],
        'details' => ['maker_id', 'series', 'age_category', 'notes'],
        'listening' => ['progress', 'score', 'priority', 'num_re_listen_times', 're_listen_value', 'start_date', 'end_date'],
    ];

    public const METADATA = ['title', 'description', 'hidden_on_index', 'color', 'text_color'];

    public const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'image/avif' => 'avif', 'image/bmp' => 'bmp', 'image/x-ms-bmp' => 'bmp'];

    public function work(Product $product, bool $images = false): array
    {
        $attributes = $product->attributesToArray();
        $data = ['rj_code' => strtoupper($product->id), 'created_at' => $product->created_at?->utc()->toIso8601ZuluString(), 'updated_at' => $product->updated_at?->utc()->toIso8601ZuluString()];
        foreach (self::FIELDS as $category => $fields) {
            $data[$category] = Arr::only($attributes, $fields);
        }
        $data['contributors'] = $this->category($product, 'contributors');
        $data['tags'] = $this->category($product, 'tags');
        if ($images) {
            $data['cover'] = $this->imageCategory($product, 'cover');
            $data['sample_images'] = $this->imageCategory($product, 'sample_images');
        }

        return $data;
    }

    public function category(Product $product, string $category, bool $lock = false): array
    {
        if ($category === 'contributors') {
            $values = $product->contributors()
                ->when($lock, fn($query) => $query->lockForUpdate())
                ->orderBy('contributor_product.id')
                ->get(['contributors.id', 'contributors.name'])
                ->groupBy('pivot.role')
                ->map(fn($contributors): array => $contributors->pluck('name')->values()->all())
                ->all();
            $values = array_replace(array_fill_keys(array_column(ProductContributorRole::cases(), 'value'), []), $values);
            if ($values['circle'] === [] && filled($product->circle)) {
                $values['circle'] = [$product->circle];
            }

            return $values;
        }
        if ($category === 'tags') {
            $values = [];
            foreach (['custom' => 'customGenres', 'jp' => 'japaneseGenres', 'en' => 'englishGenres'] as $bucket => $relation) {
                $values[$bucket] = $product->$relation()->when($lock, fn($query) => $query->lockForUpdate())->orderBy('genre_product.id')->pluck('title')->all();
            }

            return $values;
        }

        return Arr::only($product->attributesToArray(), self::FIELDS[$category] ?? []);
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
