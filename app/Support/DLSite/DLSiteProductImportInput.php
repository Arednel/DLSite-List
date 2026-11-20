<?php

namespace App\Support\DLSite;

use App\Enums\DlsiteLanguage;
use App\Enums\ProductContributorRole;
use App\Enums\ProductField;
use App\Enums\RatingScale;
use App\Http\Requests\BaseProductRequest;
use App\Models\Option;
use App\Support\ProductFieldLayout;
use Illuminate\Support\Arr;
use InvalidArgumentException;

final readonly class DLSiteProductImportInput
{
    /**
     * @param array<string, mixed> $values
     * @param list<string> $visibleFields
     * @param array<string, bool> $submitted
     */
    public function __construct(
        public array $values,
        public array $visibleFields,
        public array $submitted,
        public bool $autoSeriesFromTitleName,
        public RatingScale $ratingScale = RatingScale::Ten,
        public DlsiteLanguage $metadataLanguage = DlsiteLanguage::English,
        public array $fetchLanguages = [
            DlsiteLanguage::Japanese->value,
            DlsiteLanguage::English->value,
        ],
    ) {}

    public static function fromRequest(
        BaseProductRequest $request,
        array $validated,
        array $layout,
    ): self {
        $submissionKeys = [
            'work_name',
            'localized_title',
            'age_category',
            'product_format',
            'circle',
            'publisher',
            'brand',
            'maker_id',
            'description',
            'description_localized',
            'notes',
            'series',
            'score',
            'dlsite_rating',
            'dlsite_rating_count',
            'progress',
            'genre_custom',
            'add.start_date',
            'add.finish_date',
            'add.num_re_listen_times',
            'add.re_listen_value',
            'add.priority',
            ...collect(ProductContributorRole::cases())
                ->reject(fn(ProductContributorRole $role): bool => $role === ProductContributorRole::Circle)
                ->map(fn(ProductContributorRole $role): string => $role->value)
                ->all(),
        ];

        $submitted = [];
        foreach ($submissionKeys as $key) {
            $submitted[$key] = $request->wasSubmitted($key);
        }

        $metadataLanguage = DlsiteLanguage::from($validated['metadata_language']);
        $fetchLanguages = array_column(Option::effectiveDlsiteMetadataFetchLanguages(), 'value');
        if (! in_array($metadataLanguage->value, $fetchLanguages, true)) {
            $fetchLanguages[] = $metadataLanguage->value;
        }

        return new self(
            values: Arr::except($validated, ['id', 'product_code_list', 'product_codes']),
            visibleFields: ProductFieldLayout::visibleFields($layout),
            submitted: $submitted,
            autoSeriesFromTitleName: Option::autoSeriesFromTitleName(),
            ratingScale: $request->enum('dlsite_rating_scale', RatingScale::class, RatingScale::Ten),
            metadataLanguage: $metadataLanguage,
            fetchLanguages: $fetchLanguages,
        );
    }

    /**
     * @return array{
     *     values: array<string, mixed>,
     *     visible_fields: list<string>,
     *     submitted: array<string, bool>,
     *     auto_series_from_title_name: bool,
     *     rating_scale: string,
     *     metadata_language: string,
     *     fetch_languages: list<string>
     * }
     */
    public function toSnapshot(): array
    {
        return [
            'values' => $this->values,
            'visible_fields' => $this->visibleFields,
            'submitted' => $this->submitted,
            'auto_series_from_title_name' => $this->autoSeriesFromTitleName,
            'rating_scale' => $this->ratingScale->value,
            'metadata_language' => $this->metadataLanguage->value,
            'fetch_languages' => $this->fetchLanguages,
        ];
    }

    public static function fromSnapshot(array $snapshot): self
    {
        $metadataLanguageValue = $snapshot['metadata_language'] ?? null;
        $metadataLanguage = is_string($metadataLanguageValue)
            ? DlsiteLanguage::tryFrom($metadataLanguageValue)
            : null;
        if ($metadataLanguage === null) {
            throw new InvalidArgumentException('Bulk Import snapshot has an invalid metadata_language.');
        }

        $fetchLanguages = $snapshot['fetch_languages'] ?? null;
        if (! is_array($fetchLanguages) || ! array_is_list($fetchLanguages)) {
            throw new InvalidArgumentException('Bulk Import snapshot has invalid fetch_languages.');
        }

        $seenLanguages = [];
        foreach ($fetchLanguages as $language) {
            if (
                ! is_string($language)
                || DlsiteLanguage::tryFrom($language) === null
                || isset($seenLanguages[$language])
            ) {
                throw new InvalidArgumentException('Bulk Import snapshot has invalid fetch_languages.');
            }

            $seenLanguages[$language] = true;
        }

        if (
            ! isset($seenLanguages[DlsiteLanguage::Japanese->value])
            || ! isset($seenLanguages[$metadataLanguage->value])
        ) {
            throw new InvalidArgumentException('Bulk Import snapshot has incomplete fetch_languages.');
        }

        return new self(
            values: is_array($snapshot['values'] ?? null) ? $snapshot['values'] : [],
            visibleFields: array_values(array_unique(array_filter(
                is_array($snapshot['visible_fields'] ?? null) ? $snapshot['visible_fields'] : [],
                'is_string',
            ))),
            submitted: is_array($snapshot['submitted'] ?? null) ? $snapshot['submitted'] : [],
            autoSeriesFromTitleName: (bool) ($snapshot['auto_series_from_title_name'] ?? false),
            ratingScale: RatingScale::tryFrom((string) ($snapshot['rating_scale'] ?? '')) ?? RatingScale::Ten,
            metadataLanguage: $metadataLanguage,
            fetchLanguages: $fetchLanguages,
        );
    }

    public function value(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    private function wasSubmitted(string $key): bool
    {
        return (bool) ($this->submitted[$key] ?? false);
    }

    private function wasAnySubmitted(string|array $keys): bool
    {
        foreach ((array) $keys as $key) {
            if ($this->wasSubmitted($key)) {
                return true;
            }
        }

        return false;
    }

    public function fieldVisible(ProductField $field): bool
    {
        return in_array($field->value, $this->visibleFields, true);
    }

    public function fieldSubmitted(ProductField $field, string|array $keys): bool
    {
        return $this->fieldVisible($field) && $this->wasAnySubmitted($keys);
    }
}
