<?php

namespace App\Support;

use App\Enums\ProductAgeCategory;
use App\Enums\ProductIndexSortDirection;
use App\Enums\ProductIndexSortField;
use App\Enums\ProductIndexTagMatch;
use App\Enums\ProductPriority;
use App\Enums\ProductProgress;
use App\Enums\ProductReListenValue;
use App\Enums\ProductScore;
use BackedEnum;

final readonly class ProductIndexFilters
{
    public function __construct(
        public string $search = '',
        public string $title = '',
        public string $notes = '',
        public string $genre = '',
        public string $series = '',
        public string $tags = '',
        public ?ProductIndexTagMatch $tagMatch = null,
        public ?ProductAgeCategory $ageCategory = null,
        public ?ProductProgress $progress = null,
        public ?ProductScore $score = null,
        public ?ProductPriority $priority = null,
        public ?int $numReListenTimes = null,
        public ?ProductReListenValue $reListenValue = null,
        public ?ProductIndexSort $primarySort = null,
        public ?ProductIndexSort $secondarySort = null,
    ) {
    }

    public static function fromQuery(array $query): self
    {
        $tags = self::normalizeText($query['tags'] ?? null);
        $primarySort = self::normalizeSort(
            self::normalizeEnum($query['sort_first_field'] ?? null, ProductIndexSortField::class),
            self::normalizeEnum($query['sort_first_direction'] ?? null, ProductIndexSortDirection::class),
        );
        $secondarySort = self::normalizeSort(
            self::normalizeEnum($query['sort_second_field'] ?? null, ProductIndexSortField::class),
            self::normalizeEnum($query['sort_second_direction'] ?? null, ProductIndexSortDirection::class),
        );

        if ($primarySort === null) {
            $secondarySort = null;
        }

        if ($primarySort !== null && $secondarySort?->field === $primarySort->field) {
            $secondarySort = null;
        }

        return new self(
            search: self::normalizeText($query['search'] ?? null),
            title: self::normalizeText($query['title'] ?? null),
            notes: self::normalizeText($query['notes'] ?? null),
            genre: self::normalizeText($query['genre'] ?? null),
            series: self::normalizeText($query['series'] ?? null),
            tags: $tags,
            tagMatch: $tags === ''
                ? null
                : (self::normalizeEnum($query['tag_match'] ?? null, ProductIndexTagMatch::class) ?? ProductIndexTagMatch::All),
            ageCategory: self::normalizeEnum($query['age_category'] ?? null, ProductAgeCategory::class),
            progress: self::normalizeEnum($query['progress'] ?? null, ProductProgress::class),
            score: self::normalizeEnum($query['score'] ?? null, ProductScore::class),
            priority: self::normalizeEnum($query['priority'] ?? null, ProductPriority::class),
            numReListenTimes: self::normalizeNonNegativeInteger($query['num_re_listen_times'] ?? null),
            reListenValue: self::normalizeEnum($query['re_listen_value'] ?? null, ProductReListenValue::class),
            primarySort: $primarySort,
            secondarySort: $secondarySort,
        );
    }

    public static function optionSets(): array
    {
        return [
            'age_categories' => ProductAgeCategory::options(),
            'progress' => ProductProgress::options(),
            'scores' => ProductScore::options(),
            'priorities' => ProductPriority::options(),
            're_listen_values' => ProductReListenValue::options(),
            'tag_match' => ProductIndexTagMatch::options(),
            'sort_fields' => ProductIndexSortField::options(),
            'sort_directions' => ProductIndexSortDirection::options(),
        ];
    }

    public function progressHeading(): string
    {
        return $this->progress?->value ?? 'All ASMR';
    }

    public function hasActiveFilters(): bool
    {
        return $this->toQuery() !== [];
    }

    public function parsedTags(): array
    {
        return TagInput::parse($this->tags);
    }

    public function resolvedTagMatch(): ProductIndexTagMatch
    {
        return $this->tagMatch ?? ProductIndexTagMatch::All;
    }

    public function toInput(): array
    {
        return [
            'search' => $this->search,
            'title' => $this->title,
            'notes' => $this->notes,
            'genre' => $this->genre,
            'series' => $this->series,
            'tags' => $this->tags,
            'tag_match' => $this->tagMatch?->value ?? '',
            'age_category' => $this->ageCategory?->value ?? '',
            'progress' => $this->progress?->value ?? '',
            'score' => $this->score?->value ?? '',
            'priority' => $this->priority?->value ?? '',
            'num_re_listen_times' => $this->numReListenTimes === null ? '' : (string) $this->numReListenTimes,
            're_listen_value' => $this->reListenValue?->value ?? '',
            'sort_first_field' => $this->primarySort?->field->value ?? '',
            'sort_first_direction' => $this->primarySort?->direction->value ?? '',
            'sort_second_field' => $this->secondarySort?->field->value ?? '',
            'sort_second_direction' => $this->secondarySort?->direction->value ?? '',
        ];
    }

    public function toQuery(): array
    {
        return collect($this->toInput())
            ->reject(fn (string $value) => $value === '')
            ->all();
    }

    /**
     * @return list<ProductIndexSort>
     */
    public function sorts(): array
    {
        return array_values(array_filter([
            $this->primarySort,
            $this->secondarySort,
        ]));
    }

    private static function normalizeText(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function normalizeNonNegativeInteger(mixed $value): ?int
    {
        $value = self::normalizeText($value);

        if ($value === '' || !ctype_digit($value)) {
            return null;
        }

        return (int) $value;
    }

    /**
     * @template TEnum of BackedEnum
     *
     * @param  class-string<TEnum>  $enumClass
     * @return TEnum|null
     */
    private static function normalizeEnum(mixed $value, string $enumClass): ?BackedEnum
    {
        $value = self::normalizeText($value);

        return $value === '' ? null : $enumClass::tryFrom($value);
    }

    private static function normalizeSort(
        ?ProductIndexSortField $field,
        ?ProductIndexSortDirection $direction,
    ): ?ProductIndexSort {
        if ($field === null) {
            return null;
        }

        return new ProductIndexSort(
            $field,
            $direction ?? ProductIndexSortDirection::Desc,
        );
    }
}
