<?php

namespace App\Support\Refetch;

use App\Enums\DlsiteLanguage;
use App\Enums\ProductContributorRole;
use App\Enums\ProductFormat;
use App\Enums\RefetchCategory;
use App\Models\Genre;
use App\Models\Product;
use App\Support\LocalizedMetadataField;
use App\Support\DLSite\DLSiteFetchResult;
use App\Support\DLSite\DLSiteRating;
use App\Support\ProductContributorSync;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class RefetchDiffBuilder
{
    public function __construct(
        private readonly ProductContributorSync $contributorSync,
    ) {}

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function build(
        Product $product,
        DLSiteFetchResult $fetch,
        ?string $publicImageDirectory,
    ): array {
        $work = $fetch->workData;
        $contributors = $this->contributorSync->namesByRole($product);
        $changes = [];
        $translatedLocales = collect(array_keys($work->localizedMetadata))
            ->filter(fn(string $locale): bool => $locale !== DlsiteLanguage::Japanese->value)
            ->values()
            ->all();
        $currentLocalizations = $translatedLocales === []
            ? collect()
            : $product->localizations()
            ->whereIn('locale', $translatedLocales)
            ->get(['locale', 'title', 'description'])
            ->keyBy('locale');

        foreach ($work->localizedMetadata as $locale => $metadata) {
            $language = DlsiteLanguage::tryFrom($locale);
            if ($language === null) {
                continue;
            }

            $currentLocalization = $language === DlsiteLanguage::Japanese
                ? null
                : $currentLocalizations->get($language->value);
            $title = $metadata['title'] ?? null;
            if ($language !== DlsiteLanguage::Japanese || $title !== null) {
                $this->add(
                    $changes,
                    RefetchCategory::Titles,
                    LocalizedMetadataField::format($language, LocalizedMetadataField::TITLE),
                    $language->titleLabel(),
                    $language === DlsiteLanguage::Japanese ? $product->work_name : $currentLocalization?->title,
                    $title,
                );
            }

            $this->add(
                $changes,
                RefetchCategory::Descriptions,
                LocalizedMetadataField::format($language, LocalizedMetadataField::DESCRIPTION),
                $language->descriptionLabel(),
                $language === DlsiteLanguage::Japanese ? $product->description : $currentLocalization?->description,
                $metadata['description'] ?? null,
            );
        }
        $this->add($changes, RefetchCategory::Series, 'series', 'Series', $product->series, $work->autoSeries());
        $this->add($changes, RefetchCategory::Age, 'age_category', 'Age', $product->age_category, $work->ageCategory);
        $this->add($changes, RefetchCategory::SiteId, 'site_id', 'Site ID', $product->site_id, $work->siteId);
        $this->addProductFormat($changes, RefetchCategory::ProductFormat, 'product_format', 'Product Format', $product->product_format ?? [], $work->productFormat);
        $this->add($changes, RefetchCategory::AnnouncementDate, 'announce_date', 'Scheduled release date', $product->announce_date?->format('Y-m-d H:i:s'), $work->announceDate);
        $this->add(
            $changes,
            RefetchCategory::Rating,
            'dlsite_rating',
            'DLsite Rating',
            $product->only(DLSiteRating::ATTRIBUTE_KEYS),
            $work->rating->attributes(),
        );
        $this->add(
            $changes,
            RefetchCategory::Circle,
            ProductContributorRole::Circle->value,
            'Circle',
            $contributors[ProductContributorRole::Circle->value] ?? [],
            $work->contributorsByRole[ProductContributorRole::Circle->value] ?? [],
        );
        $this->add($changes, RefetchCategory::Publisher, 'publisher', 'Publisher', $product->publisher, $work->publisher);
        $this->add($changes, RefetchCategory::Brand, 'brand', 'Brand', $product->brand, $work->brand);
        $this->add($changes, RefetchCategory::Maker, 'maker_id', 'Maker ID', $product->maker_id, $work->makerId);

        foreach (
            [
                RefetchCategory::Scenario,
                RefetchCategory::VoiceActor,
                RefetchCategory::Illustration,
                RefetchCategory::Author,
            ] as $category
        ) {
            $role = $category->contributorRole();
            $this->add(
                $changes,
                $category,
                $role->value,
                match ($role) {
                    ProductContributorRole::Scenario => 'Scenario Author',
                    ProductContributorRole::VoiceActor => 'Voice Actor',
                    ProductContributorRole::Illustration => 'Illustration Author',
                    ProductContributorRole::Author => 'Author',
                    ProductContributorRole::Circle => 'Circle',
                },
                $contributors[$role->value] ?? [],
                $work->contributorsByRole[$role->value] ?? [],
            );
        }

        $tagChange = $this->tagChange($product, $work->genresByLocale());

        if ($tagChange !== null) {
            $changes[RefetchCategory::Tags->value]['tags'] = $tagChange;
        }

        if ($publicImageDirectory !== null) {
            $this->addImageChanges($changes, $product, $fetch, $publicImageDirectory);
        }

        return $changes;
    }

    /**
     * @param  array<string, array<string, array<string, mixed>>>  $changes
     */
    private function add(
        array &$changes,
        RefetchCategory $category,
        string $field,
        string $label,
        mixed $old,
        mixed $new,
    ): void {
        $equivalent = $category === RefetchCategory::Rating
            ? $old === $new
            : $this->equivalent($old, $new);

        if ($equivalent) {
            return;
        }

        $changes[$category->value][$field] = compact('label', 'old', 'new');
    }

    /**
     * @param  array<string, array<string, array<string, mixed>>>  $changes
     */
    private function addProductFormat(
        array &$changes,
        RefetchCategory $category,
        string $field,
        string $label,
        mixed $old,
        mixed $new,
    ): void {
        $old = ProductFormat::normalizeDlsiteValues(is_array($old) ? $old : [$old]);
        $new = ProductFormat::normalizeDlsiteValues(is_array($new) ? $new : [$new]);

        if ($old === $new) {
            return;
        }

        $changes[$category->value][$field] = compact('label', 'old', 'new');
    }

    private function equivalent(mixed $old, mixed $new): bool
    {
        if (is_array($old) || is_array($new)) {
            return $this->normalizedList($old) === $this->normalizedList($new);
        }

        return $this->text($old) === $this->text($new);
    }

    /**
     * @return list<string>
     */
    private function normalizedList(mixed $values): array
    {
        return collect(is_array($values) ? $values : [$values])
            ->map(fn(mixed $value): ?string => $this->text($value))
            ->filter(fn(?string $value): bool => $value !== null)
            ->unique(fn(string $value): string => mb_convert_case($value, MB_CASE_FOLD, 'UTF-8'))
            ->sortBy(fn(string $value): string => mb_convert_case($value, MB_CASE_FOLD, 'UTF-8'))
            ->values()
            ->all();
    }

    private function text(mixed $value): ?string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, list<string>> $fetchedByLocale
     * @return array<string, mixed>|null
     */
    private function tagChange(Product $product, array $fetchedByLocale): ?array
    {
        $fetchedByLocale = collect($fetchedByLocale)
            ->map(fn(array $tags): array => $this->normalizeTags($tags))
            ->all();
        $oldByLocale = $this->currentFetchedTagsByLocale($product, array_keys($fetchedByLocale));
        $custom = $product->customGenres()->orderBy('genres.title')->pluck('genres.title')->all();

        $changedLocales = collect($fetchedByLocale)
            ->filter(function (array $tags, string $locale) use ($oldByLocale): bool {
                return $this->tagKeys($oldByLocale[$locale] ?? []) !== $this->tagKeys($tags);
            })
            ->keys()
            ->values()
            ->all();

        if ($changedLocales === []) {
            return null;
        }

        $added = [];
        $stale = [];
        $customToFetched = [];
        foreach ($fetchedByLocale as $locale => $tags) {
            $old = $oldByLocale[$locale] ?? [];
            $added[$locale] = $this->tagDiff($tags, $old, $custom);
            $stale[$locale] = $this->tagDiff($old, $tags);
            $customToFetched[$locale] = $this->tagIntersection($tags, $custom);
        }

        return [
            'label' => 'Fetched Tags',
            'old' => [
                'fetched' => $oldByLocale,
                'custom' => $custom,
            ],
            'new' => [
                'fetched' => $fetchedByLocale,
                'custom' => $custom,
            ],
            'details' => [
                'changed_locales' => $changedLocales,
                'added' => $added,
                'stale' => $stale,
                'custom_to_fetched' => $customToFetched,
            ],
        ];
    }

    /**
     * @param list<string> $locales
     * @return array<string, list<string>>
     */
    private function currentFetchedTagsByLocale(Product $product, array $locales): array
    {
        if ($locales === []) {
            return [];
        }

        $grouped = DB::table('genre_product')
            ->join('genres', 'genres.id', '=', 'genre_product.genre_id')
            ->join('genre_product_languages', 'genre_product_languages.genre_product_id', '=', 'genre_product.id')
            ->where('genre_product.product_id', $product->getKey())
            ->where('genre_product.source', Genre::PIVOT_SOURCE_FETCHED)
            ->whereIn('genre_product_languages.language', $locales)
            ->orderBy('genre_product_languages.language')
            ->orderBy('genres.title')
            ->get(['genre_product_languages.language', 'genres.title'])
            ->groupBy('language')
            ->map(fn($rows): array => $rows->pluck('title')->values()->all())
            ->all();

        return collect($locales)
            ->mapWithKeys(fn(string $locale): array => [$locale => $grouped[$locale] ?? []])
            ->all();
    }

    /**
     * @param  array<string, array<string, array<string, mixed>>>  $changes
     */
    private function addImageChanges(
        array &$changes,
        Product $product,
        DLSiteFetchResult $fetch,
        string $publicImageDirectory,
    ): void {
        $failed = array_flip($fetch->failedImages);
        $stagedCover = "{$publicImageDirectory}/cover.jpg";

        if (! isset($failed['cover.jpg']) && Storage::disk('public')->exists($stagedCover)) {
            $old = $this->publicStoragePath($product->work_image);

            if ($this->fileHash($old) !== $this->fileHash($stagedCover)) {
                $changes[RefetchCategory::Cover->value]['cover'] = [
                    'label' => 'Cover',
                    'old' => $product->work_image,
                    'new' => "storage/{$publicImageDirectory}/cover.jpg",
                    'staged_path' => "{$publicImageDirectory}/cover.jpg",
                ];
            }
        }

        $failedSamples = collect($fetch->failedImages)
            ->filter(fn(string $filename): bool => str_starts_with($filename, 'sample_'))
            ->isNotEmpty();

        if ($failedSamples) {
            return;
        }

        $sampleCount = count($fetch->workData->sampleImages);
        $stagedSamples = collect($sampleCount === 0 ? [] : range(1, $sampleCount))
            ->map(fn(int $index): string => "{$publicImageDirectory}/sample_{$index}.jpg")
            ->filter(fn(string $path): bool => Storage::disk('public')->exists($path))
            ->values()
            ->all();

        if (count($stagedSamples) !== count($fetch->workData->sampleImages)) {
            return;
        }

        $currentHashes = collect($product->sample_images ?? [])
            ->map(fn(string $path): ?string => $this->fileHash($this->publicStoragePath($path)))
            ->all();
        $stagedHashes = collect($stagedSamples)
            ->map(fn(string $path): ?string => $this->fileHash($path))
            ->all();

        if ($currentHashes !== $stagedHashes) {
            $changes[RefetchCategory::SampleImages->value]['sample_images'] = [
                'label' => 'Sample Images',
                'old' => $product->sample_images ?? [],
                'new' => array_map(fn(string $path): string => "storage/{$path}", $stagedSamples),
                'staged_paths' => $stagedSamples,
            ];
        }
    }

    private function publicStoragePath(?string $path): ?string
    {
        if ($path === null || ! str_starts_with($path, 'storage/')) {
            return null;
        }

        return substr($path, strlen('storage/'));
    }

    private function fileHash(?string $path): ?string
    {
        return $path !== null && Storage::disk('public')->exists($path)
            ? hash('sha256', Storage::disk('public')->get($path))
            : null;
    }

    /**
     * @param  list<string>  $tags
     * @return list<string>
     */
    private function normalizeTags(array $tags): array
    {
        return collect($tags)
            ->map(fn(mixed $tag): string => trim((string) $tag))
            ->filter(fn(string $tag): bool => $tag !== '')
            ->unique(fn(string $tag): string => Genre::titleKey($tag))
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $tags
     * @return list<string>
     */
    private function tagKeys(array $tags): array
    {
        return collect($tags)
            ->map(fn(string $tag): string => Genre::titleKey($tag))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $source
     * @param  list<string>  ...$without
     * @return list<string>
     */
    private function tagDiff(array $source, array ...$without): array
    {
        $withoutKeys = array_flip($this->tagKeys(array_merge(...$without)));

        return collect($source)
            ->reject(fn(string $tag): bool => isset($withoutKeys[Genre::titleKey($tag)]))
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $source
     * @param  list<string>  $only
     * @return list<string>
     */
    private function tagIntersection(array $source, array $only): array
    {
        $onlyKeys = array_flip($this->tagKeys($only));

        return collect($source)
            ->filter(fn(string $tag): bool => isset($onlyKeys[Genre::titleKey($tag)]))
            ->values()
            ->all();
    }
}
