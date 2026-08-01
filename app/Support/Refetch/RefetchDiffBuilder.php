<?php

namespace App\Support\Refetch;

use App\Enums\ProductContributorRole;
use App\Enums\RefetchCategory;
use App\Models\Genre;
use App\Models\Product;
use App\Support\DLSite\DLSiteFetchResult;
use App\Support\ProductContributorSync;
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

        $this->add($changes, RefetchCategory::Titles, 'work_name', 'Japanese Title', $product->work_name, $work->workName);
        $this->add($changes, RefetchCategory::Titles, 'work_name_english', 'English Title', $product->work_name_english, $work->englishWorkName);
        $this->add($changes, RefetchCategory::Descriptions, 'description', 'Japanese Description', $product->description, $work->description);
        $this->add($changes, RefetchCategory::Descriptions, 'description_english', 'English Description', $product->description_english, $work->englishDescription);
        $this->add($changes, RefetchCategory::Series, 'series', 'Series', $product->series, $work->autoSeries());
        $this->add($changes, RefetchCategory::Age, 'age_category', 'Age', $product->age_category, $work->ageCategory);
        $this->add(
            $changes,
            RefetchCategory::Circle,
            ProductContributorRole::Circle->value,
            'Circle',
            $contributors[ProductContributorRole::Circle->value] ?? [],
            $work->contributorsByRole[ProductContributorRole::Circle->value] ?? [],
        );
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

        $tagChange = $this->tagChange($product, $work->japaneseGenres, $work->englishGenres);

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
        if ($this->equivalent($old, $new)) {
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
            ->filter()
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
     * @param  list<string>  $japanese
     * @param  list<string>  $english
     * @return array<string, mixed>|null
     */
    private function tagChange(Product $product, array $japanese, array $english): ?array
    {
        $oldJapanese = $product->japaneseGenres()->orderBy('genres.title')->pluck('genres.title')->all();
        $oldEnglish = $product->englishGenres()->orderBy('genres.title')->pluck('genres.title')->all();
        $custom = $product->customGenres()->orderBy('genres.title')->pluck('genres.title')->all();
        $japanese = $this->normalizeTags($japanese);
        $english = $this->normalizeTags($english);

        if (
            $this->tagKeys($oldJapanese) === $this->tagKeys($japanese)
            && $this->tagKeys($oldEnglish) === $this->tagKeys($english)
        ) {
            return null;
        }

        return [
            'label' => 'Fetched Tags',
            'old' => [
                'japanese' => $oldJapanese,
                'english' => $oldEnglish,
                'custom' => $custom,
            ],
            'new' => [
                'japanese' => $japanese,
                'english' => $english,
                'custom' => $custom,
            ],
            'details' => [
                'added_japanese' => $this->tagDiff($japanese, $oldJapanese, $custom),
                'added_english' => $this->tagDiff($english, $oldEnglish, $custom),
                'stale_japanese' => $this->tagDiff($oldJapanese, $japanese),
                'stale_english' => $this->tagDiff($oldEnglish, $english),
                'custom_to_fetched_japanese' => $this->tagIntersection($japanese, $custom),
                'custom_to_fetched_english' => $this->tagIntersection($english, $custom),
            ],
        ];
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
            ->filter()
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
