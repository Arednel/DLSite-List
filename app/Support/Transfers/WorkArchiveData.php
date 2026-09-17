<?php

namespace App\Support\Transfers;

use App\Enums\ProductAgeCategory;
use App\Enums\ProductContributorRole;
use App\Models\Product;
use App\Support\DLSite\DLSiteWorkData;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

final class WorkArchiveData
{
    public const FORMAT = 'dlsite-async+dlsite-list';

    private const DLSITE_FIELDS = [
        'product_id',
        'site_id',
        'maker_id',
        'work_name',
        'age_category',
        'circle',
        'brand',
        'publisher',
        'work_image',
        'regist_date',
        'work_type',
        'book_type',
        'announce_date',
        'modified_date',
        'scenario',
        'illustration',
        'voice_actor',
        'author',
        'music',
        'writer',
        'genre',
        'label',
        'event',
        'file_format',
        'file_size',
        'language',
        'page_count',
        'description',
        'sample_images',
        'work_name_masked',
        'title_name',
        'title_name_masked',
    ];

    private const CUSTOM_FIELDS = [
        'notes',
        'score',
        'progress',
        'start_date',
        'end_date',
        'num_re_listen_times',
        're_listen_value',
        'priority',
        'custom_tags',
        'created_at',
        'updated_at',
        'cover',
        'sample_images',
    ];

    public function __construct(private readonly LibraryData $data) {}

    public function exportSnapshot(Product $product, bool $images = false): array
    {
        $contributors = $this->data->category($product, 'contributors');
        $tags = $this->data->category($product, 'tags');
        $coverInspection = $images ? $this->data->inspectImageCategory($product, 'cover') : null;
        $sampleInspection = $images ? $this->data->inspectImageCategory($product, 'sample_images') : null;
        $cover = $coverInspection === null ? null : Arr::except($coverInspection, ['unavailable']);
        $samples = $sampleInspection === null ? null : Arr::except($sampleInspection, ['unavailable']);
        $coverPath = $cover['files'][0]['path'] ?? null;
        $samplePaths = $samples === null ? null : array_column($samples['files'], 'path');

        $japanese = $this->locale(
            $product,
            $product->work_name,
            $product->description,
            $tags['jp'],
            $contributors,
            $coverPath,
            $samplePaths,
        );
        $english = $this->locale(
            $product,
            $product->work_name_english ?? $product->work_name,
            $product->description_english ?? $product->description,
            $tags['en'],
            $contributors,
            $coverPath,
            $samplePaths,
        );
        $custom = [
            'notes' => $product->notes,
            'score' => $product->score,
            'progress' => $product->progress,
            'start_date' => $product->start_date,
            'end_date' => $product->end_date,
            'num_re_listen_times' => $product->num_re_listen_times,
            're_listen_value' => $product->re_listen_value,
            'priority' => $product->priority,
            'custom_tags' => $tags['custom'],
        ];
        if ($images) {
            $custom['cover'] = $cover;
            $custom['sample_images'] = $samples;
        }
        $custom['created_at'] = $product->created_at?->utc()->toIso8601ZuluString();
        $custom['updated_at'] = $product->updated_at?->utc()->toIso8601ZuluString();

        return [
            'work' => ['japanese' => $japanese, 'english' => $english, 'dlsite_list' => $custom],
            'image_warnings' => [
                ...($coverInspection['unavailable'] ?? []),
                ...($sampleInspection['unavailable'] ?? []),
            ],
        ];
    }

    public function assertLayout(array $document, string $expectedCode): void
    {
        if (
            Validator::make(['work' => $document], [
                'work' => ['array:japanese,english,dlsite_list'],
            ])->fails()
            || ! is_array($document['japanese'] ?? null) || ($document['japanese'] !== [] && array_is_list($document['japanese']))
            || ! is_array($document['english'] ?? null) || ($document['english'] !== [] && array_is_list($document['english']))
            || (array_key_exists('dlsite_list', $document) && (! is_array($document['dlsite_list']) || ($document['dlsite_list'] !== [] && array_is_list($document['dlsite_list']))))
        ) {
            throw new InvalidArchive('Unsupported or invalid archive layout: invalid work JSON.');
        }

        $codes = [];
        foreach (['japanese', 'english'] as $locale) {
            if (! array_key_exists('product_id', $document[$locale]) || $document[$locale]['product_id'] === null) {
                continue;
            }
            if (! is_string($document[$locale]['product_id'])) {
                throw new InvalidArchive('Unsupported or invalid archive layout: invalid work product ID.');
            }
            $codes[] = strtoupper(trim($document[$locale]['product_id']));
        }
        if ($codes === [] || collect($codes)->contains(fn(string $code): bool => $code !== $expectedCode)) {
            throw new InvalidArchive('Unsupported or invalid archive layout: work path and product ID do not match.');
        }
    }

    public function import(array $document, string $expectedCode): array
    {
        $this->assertLayout($document, $expectedCode);
        $custom = $document['dlsite_list'] ?? [];
        if (Validator::make(['dlsite_list' => $custom], [
            'dlsite_list' => ['array:' . implode(',', self::CUSTOM_FIELDS)],
        ])->fails()) {
            throw new InvalidArgumentException('Unknown dlsite_list fields in schema v1.');
        }

        $work = DLSiteWorkData::fromArray($document, $expectedCode);
        $title = $this->localeText($document['japanese'], 'work_name') ?? $work->workName ?? $work->englishWorkName;
        if ($title === null) {
            throw new InvalidArgumentException('DLSite work data is missing a work name.');
        }

        $record = ['rj_code' => $expectedCode];
        foreach (['created_at', 'updated_at'] as $field) {
            if (array_key_exists($field, $custom)) {
                $record[$field] = $custom[$field];
            }
        }

        $record['titles'] = ['work_name' => $title];
        if (array_key_exists('work_name', $document['english'])) {
            $englishTitle = $this->localeText($document['english'], 'work_name');
            $record['titles']['work_name_english'] = $englishTitle === $title ? null : $englishTitle;
        }

        $descriptions = [];
        if (array_key_exists('description', $document['japanese'])) {
            $descriptions['description'] = $this->localeText($document['japanese'], 'description');
        }
        if (array_key_exists('description', $document['english'])) {
            $englishDescription = $this->localeText($document['english'], 'description');
            $descriptions['description_english'] = $englishDescription === ($descriptions['description'] ?? null) ? null : $englishDescription;
        }
        if ($descriptions !== []) {
            $record['descriptions'] = $descriptions;
        }

        $details = [];
        if ($this->hasEither($document, 'maker_id')) {
            $details['maker_id'] = $this->localeText($document['japanese'], 'maker_id') ?? $this->localeText($document['english'], 'maker_id');
        }
        if ($this->hasEither($document, 'title_name') || $this->hasEither($document, 'title_name_masked')) {
            $details['series'] = $this->localeText($document['japanese'], 'title_name_masked')
                ?? $this->localeText($document['japanese'], 'title_name')
                ?? $this->localeText($document['english'], 'title_name_masked')
                ?? $this->localeText($document['english'], 'title_name');
        }
        if ($this->hasEither($document, 'age_category')) {
            $details['age_category'] = $work->ageCategory;
        }
        if (array_key_exists('notes', $custom)) {
            $details['notes'] = $custom['notes'];
        }
        if ($details !== []) {
            $record['details'] = $details;
        }

        $listening = Arr::only($custom, LibraryData::FIELDS['listening']);
        if ($listening !== []) {
            $record['listening'] = $listening;
        }

        foreach (ProductContributorRole::cases() as $role) {
            if ($this->hasEither($document, $role->dlsiteKey())) {
                $record['contributors'][$role->value] = $work->contributorsByRole[$role->value];
            }
        }

        $tags = [];
        if (array_key_exists('genre', $document['japanese'])) {
            $tags['jp'] = $work->japaneseGenres;
        }
        if (array_key_exists('genre', $document['english'])) {
            $tags['en'] = $work->englishGenres;
        }
        if (array_key_exists('custom_tags', $custom)) {
            $tags['custom'] = $custom['custom_tags'];
        }
        if ($tags !== []) {
            $record['tags'] = $tags;
        }

        foreach (['cover', 'sample_images'] as $category) {
            if (array_key_exists($category, $custom)) {
                $record[$category] = $custom[$category];
            }
        }

        return $record;
    }

    private function locale(Product $product, string $title, ?string $description, array $tags, array $contributors, ?string $cover, ?array $samples): array
    {
        $age = ProductAgeCategory::tryFrom((string) $product->age_category);
        $values = [
            'product_id' => strtoupper($product->id),
            'site_id' => match ($age) {
                ProductAgeCategory::AllAges => 'home',
                ProductAgeCategory::R15, ProductAgeCategory::R18 => 'maniax',
                null => null,
            },
            'maker_id' => $product->maker_id,
            'work_name' => $title,
            'age_category' => $age === null ? null : ['_value_' => $this->ageValue($age), '_name_' => $age->value],
            'circle' => $contributors['circle'][0] ?? $product->circle,
            'brand' => null,
            'publisher' => null,
            'work_image' => $cover,
            'regist_date' => null,
            'work_type' => null,
            'book_type' => null,
            'announce_date' => null,
            'modified_date' => null,
            'scenario' => $contributors['scenario'],
            'illustration' => $contributors['illustration'],
            'voice_actor' => $contributors['voice_actor'],
            'author' => $contributors['author'],
            'music' => null,
            'writer' => null,
            'genre' => $tags,
            'label' => null,
            'event' => null,
            'file_format' => null,
            'file_size' => null,
            'language' => null,
            'page_count' => null,
            'description' => $description,
            'sample_images' => $samples,
            'work_name_masked' => $title,
            'title_name' => $product->series,
            'title_name_masked' => $product->series,
        ];

        return Arr::only($values, self::DLSITE_FIELDS);
    }

    private function ageValue(ProductAgeCategory $age): int
    {
        return match ($age) {
            ProductAgeCategory::AllAges => 1,
            ProductAgeCategory::R15 => 2,
            ProductAgeCategory::R18 => 3,
        };
    }

    private function hasEither(array $document, string $key): bool
    {
        return array_key_exists($key, $document['japanese']) || array_key_exists($key, $document['english']);
    }

    private function localeText(array $locale, string $key): ?string
    {
        $value = $locale[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
