<?php

namespace App\Support\Transfers;

use App\Enums\DlsiteLanguage;
use App\Enums\ProductAgeCategory;
use App\Enums\ProductContributorRole;
use App\Support\DLSite\DLSiteRating;
use App\Models\Product;
use App\Rules\MaxBytes;
use App\Support\DLSite\DLSiteWorkData;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final class WorkArchiveData
{
    public const FORMAT = 'dlsite-async-locales+dlsite-list';

    public const LEGACY_FORMAT = 'dlsite-async+dlsite-list';

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
        'product_format',
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
        ...DLSiteRating::ATTRIBUTE_KEYS,
        'progress',
        'start_date',
        'end_date',
        'num_re_listen_times',
        're_listen_value',
        'priority',
        'custom_tags',
        'site_id',
        'created_at',
        'updated_at',
        'cover',
        'sample_images',
    ];

    public function __construct(private readonly LibraryData $data) {}

    /**
     * Export using preloaded localizations/contributors and prepared tag buckets in attachment order.
     *
     * @param array<string, list<string>> $tags
     */
    public function exportSnapshotFromLoadedData(Product $product, array $tags, bool $images = false): array
    {
        if (! $product->relationLoaded('localizations')) {
            throw new \LogicException('Preordered localization export relation is not loaded.');
        }

        $contributors = $this->data->preorderedLoadedContributors($product);
        $coverInspection = $images ? $this->data->inspectImageCategory($product, 'cover') : null;
        $sampleInspection = $images ? $this->data->inspectImageCategory($product, 'sample_images') : null;
        $cover = $coverInspection === null ? null : Arr::except($coverInspection, ['unavailable']);
        $samples = $sampleInspection === null ? null : Arr::except($sampleInspection, ['unavailable']);
        $coverPath = $cover['files'][0]['path'] ?? null;
        $samplePaths = $samples === null ? null : array_column($samples['files'], 'path');

        $localizations = $product->getRelation('localizations')->keyBy('locale');

        $workSections = [];
        foreach (DlsiteLanguage::cases() as $language) {
            $locale = $language->value;
            $isJapanese = $language === DlsiteLanguage::Japanese;
            $localization = $isJapanese ? null : $localizations->get($locale);
            $title = $isJapanese ? $product->work_name : $localization?->title;
            $description = $isJapanese ? $product->description : $localization?->description;

            $workSections[$locale] = $this->locale(
                $product,
                $title,
                $description,
                $tags[$locale] ?? [],
                $contributors,
                $coverPath,
                $samplePaths,
            );
        }
        $custom = [
            'notes' => $product->notes,
            'score' => $product->score,
            'dlsite_rating_5' => $product->dlsite_rating_5,
            'dlsite_rating_10' => $product->dlsite_rating_10,
            'dlsite_rating_count' => $product->dlsite_rating_count,
            'progress' => $product->progress,
            'start_date' => $product->start_date,
            'end_date' => $product->end_date,
            'num_re_listen_times' => $product->num_re_listen_times,
            're_listen_value' => $product->re_listen_value,
            'priority' => $product->priority,
            'custom_tags' => $tags['custom'],
            'site_id' => $product->site_id,
        ];
        if ($images) {
            $custom['cover'] = $cover;
            $custom['sample_images'] = $samples;
        }
        $custom['created_at'] = $product->created_at?->utc()->toIso8601ZuluString();
        $custom['updated_at'] = $product->updated_at?->utc()->toIso8601ZuluString();

        return [
            'work' => ['locales' => $workSections, 'dlsite_list' => $custom],
            'image_warnings' => [
                ...($coverInspection['unavailable'] ?? []),
                ...($sampleInspection['unavailable'] ?? []),
            ],
        ];
    }

    public function assertLayout(array $document, string $expectedCode, int $schemaVersion): void
    {
        $currentFormat = $schemaVersion >= 4;
        $rules = $currentFormat ? [
            'work' => [Rule::array(['locales', 'dlsite_list'])],
            'work.locales' => ['required', Rule::array(DlsiteLanguage::cases())],
            'work.locales.ja' => ['present', 'array'],
            'work.locales.*' => ['array'],
        ] : [
            'work' => [Rule::array(['japanese', 'english', 'dlsite_list'])],
            'work.japanese' => ['present', 'array'],
            'work.english' => ['present', 'array'],
        ];
        if (
            ! in_array($schemaVersion, TransferArchive::SUPPORTED_SCHEMA_VERSIONS, true)
            || Validator::make(['work' => $document], $rules)->fails()
            || (array_key_exists('dlsite_list', $document) && (! is_array($document['dlsite_list']) || ($document['dlsite_list'] !== [] && array_is_list($document['dlsite_list']))))
        ) {
            throw new InvalidArchive('transfer.archive.invalid_work_json');
        }

        $codes = [];
        foreach ($this->localeDocuments($document, $schemaVersion) as $locale) {
            if (! is_array($locale) || ($locale !== [] && array_is_list($locale))) {
                throw new InvalidArchive('transfer.archive.invalid_work_json');
            }
            if (! array_key_exists('product_id', $locale) || $locale['product_id'] === null) {
                continue;
            }
            if (! is_string($locale['product_id'])) {
                throw new InvalidArchive('transfer.archive.invalid_work_product_id');
            }
            $codes[] = strtoupper(trim($locale['product_id']));
        }
        if ($codes === [] || collect($codes)->contains(fn(string $code): bool => $code !== $expectedCode)) {
            throw new InvalidArchive('transfer.archive.work_path_product_id_mismatch');
        }
    }

    public function import(array $document, string $expectedCode, int $schemaVersion): array
    {
        $this->assertLayout($document, $expectedCode, $schemaVersion);
        $document = ['locales' => $this->localeDocuments($document, $schemaVersion), 'dlsite_list' => $document['dlsite_list'] ?? []];
        $custom = $document['dlsite_list'] ?? [];
        if (Validator::make(['dlsite_list' => $custom], [
            'dlsite_list' => ['array:' . implode(',', self::CUSTOM_FIELDS)],
        ])->fails()) {
            throw new InvalidArgumentException('transfer.archive.unknown_work_fields');
        }

        // Validate raw values before normalization; accept plain dates and the scraper's Japan offset.
        $rawRules = [
            ...LibraryWorkValidator::productFormatRules('locales.ja.product_format'),
            'locales.ja.announce_date' => ['sometimes', 'nullable', 'string', 'date_format:Y-m-d H:i:s,Y-m-d H:i:s\+09:00'],
            'dlsite_list.site_id' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/\A[a-z][a-z0-9_-]*\z/'],
        ];
        if ($schemaVersion >= 4) {
            $rawRules += [
                'locales.*.work_name' => ['sometimes', 'nullable', 'string', 'max:1000'],
                'locales.*.description' => ['sometimes', 'nullable', 'string', 'max:65535', new MaxBytes(65535)],
                'locales.*.genre' => ['sometimes', 'array', 'list', 'max:10000'],
                'locales.*.genre.*' => ['required', 'string', 'max:255'],
            ];
        }
        Validator::make($document, $rawRules)->validate();

        $document = DLSiteWorkData::normalizePayload($document);
        $locales = $document['locales'];
        $japanese = $locales[DlsiteLanguage::Japanese->value];

        $work = DLSiteWorkData::fromArray($document, $expectedCode);
        $title = $this->localeText($japanese, 'work_name');
        if ($title === null && $schemaVersion < 4) {
            $title = $work->workName ?? $work->titleForLocale(DlsiteLanguage::English);
        }
        if ($title === null) {
            throw new InvalidArgumentException('dlsite.work.missing_name');
        }

        $record = ['product_code' => $expectedCode];
        foreach (['created_at', 'updated_at'] as $field) {
            if (array_key_exists($field, $custom)) {
                $record[$field] = $custom[$field];
            }
        }

        $record['titles'] = [DlsiteLanguage::Japanese->value => $title];
        foreach ($locales as $locale => $payload) {
            if ($locale === DlsiteLanguage::Japanese->value || ! array_key_exists('work_name', $payload)) {
                continue;
            }
            $localizedTitle = $this->localeText($payload, 'work_name');
            if (
                $schemaVersion <= 3
                && $locale === DlsiteLanguage::English->value
                && $localizedTitle === $title
            ) {
                $localizedTitle = null;
            }
            $record['titles'][$locale] = $localizedTitle;
        }

        $descriptions = [];
        foreach ($locales as $locale => $payload) {
            if (! array_key_exists('description', $payload)) {
                continue;
            }
            $localizedDescription = $this->localeText($payload, 'description');
            if (
                $schemaVersion <= 3
                && $locale === DlsiteLanguage::English->value
                && $localizedDescription === ($descriptions[DlsiteLanguage::Japanese->value] ?? null)
            ) {
                $localizedDescription = null;
            }
            $descriptions[$locale] = $localizedDescription;
        }
        if ($descriptions !== []) {
            $record['descriptions'] = $descriptions;
        }

        $details = [];
        if (array_key_exists('site_id', $custom)) {
            $details['site_id'] = $custom['site_id'];
        } elseif (array_key_exists('site_id', $japanese)) {
            $details['site_id'] = $work->siteId;
        }
        foreach (['publisher', 'brand'] as $field) {
            if (array_key_exists($field, $japanese)) {
                $details[$field] = $work->{$field};
            }
        }
        if (array_key_exists('maker_id', $japanese)) {
            $details['maker_id'] = $work->makerId;
        }
        if (array_key_exists('title_name', $japanese) || array_key_exists('title_name_masked', $japanese)) {
            $details['series'] = $this->localeText($japanese, 'title_name_masked')
                ?? $this->localeText($japanese, 'title_name');
        }
        if (array_key_exists('age_category', $japanese)) {
            $details['age_category'] = $work->ageCategory;
        }
        if (array_key_exists('product_format', $japanese)) {
            $details['product_format'] = $work->productFormat === [] ? null : $work->productFormat;
        }
        if (array_key_exists('announce_date', $japanese)) {
            $details['announce_date'] = $work->announceDate;
        }
        if (array_key_exists('notes', $custom)) {
            $details['notes'] = $custom['notes'];
        }
        $details = [...$details, ...Arr::only($custom, DLSiteRating::ATTRIBUTE_KEYS)];
        if ($details !== []) {
            $record['details'] = $details;
        }

        $listening = Arr::only($custom, LibraryData::FIELDS['listening']);
        if ($listening !== []) {
            $record['listening'] = $listening;
        }

        foreach (ProductContributorRole::cases() as $role) {
            if (array_key_exists($role->dlsiteKey(), $japanese)) {
                $record['contributors'][$role->value] = $work->contributorsByRole[$role->value];
            }
        }

        $tags = [];
        foreach ($locales as $locale => $payload) {
            if (array_key_exists('genre', $payload)) {
                $tags[$locale] = $work->genresForLocale($locale);
            }
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

    /** @return array<string, array<string, mixed>> */
    private function localeDocuments(array $document, int $schemaVersion): array
    {
        return $schemaVersion >= 4 ? $document['locales'] : [
            'ja' => $document['japanese'],
            'en' => $document['english'],
        ];
    }

    private function locale(Product $product, ?string $title, ?string $description, array $tags, array $contributors, ?string $cover, ?array $samples): array
    {
        $age = ProductAgeCategory::tryFrom((string) $product->age_category);
        $values = [
            'product_id' => strtoupper($product->id),
            'site_id' => $product->site_id,
            'maker_id' => $product->maker_id,
            'work_name' => $title,
            'age_category' => $age === null ? null : ['_value_' => $this->ageValue($age), '_name_' => $age->value],
            'circle' => $contributors['circle'][0] ?? $product->circle,
            'brand' => $product->brand,
            'publisher' => $product->publisher,
            'work_image' => $cover,
            'regist_date' => null,
            'work_type' => null,
            'product_format' => $product->product_format,
            'book_type' => null,
            'announce_date' => $product->announce_date?->format('Y-m-d H:i:s'),
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

    private function localeText(array $locale, string $key): ?string
    {
        $value = $locale[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
