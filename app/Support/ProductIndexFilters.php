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
use Illuminate\Support\Arr;

final readonly class ProductIndexFilters
{
    // All Index filter and sort keys accepted from the URL and mirrored by the Livewire component.
    public const INPUT_KEYS = [
        'search',
        'title',
        'notes',
        'genre',
        'series',
        'circle',
        'scenario',
        'voice_actor',
        'illustration',
        'author',
        'description',
        'description_english',
        'tags',
        'tag_match',
        'age_category',
        'progress',
        'score',
        'priority',
        'num_re_listen_times',
        're_listen_value',
        'start_date_from',
        'start_date_to',
        'end_date_from',
        'end_date_to',
        'created_at_from',
        'created_at_to',
        'updated_at_from',
        'updated_at_to',
        'sort_first_field',
        'sort_first_direction',
        'sort_second_field',
        'sort_second_direction',
    ];

    // These groups can hide a redirected work from the Index. Sort and page state stay outside this list.
    public const VISIBILITY_FILTER_GROUPS = [
        ['search'],
        ['title'],
        ['notes'],
        ['genre'],
        ['series'],
        ['circle'],
        ['scenario'],
        ['voice_actor'],
        ['illustration'],
        ['author'],
        ['description'],
        ['description_english'],
        ['tags', 'tag_match'],
        ['age_category'],
        ['progress'],
        ['score'],
        ['priority'],
        ['num_re_listen_times'],
        ['re_listen_value'],
        ['start_date_from', 'start_date_to'],
        ['end_date_from', 'end_date_to'],
        ['created_at_from', 'created_at_to'],
        ['updated_at_from', 'updated_at_to'],
    ];

    public function __construct(
        public string $search = '',
        public string $title = '',
        public string $notes = '',
        public string $genre = '',
        public string $series = '',
        public string $circle = '',
        public string $scenario = '',
        public string $voiceActor = '',
        public string $illustration = '',
        public string $author = '',
        public string $description = '',
        public string $descriptionEnglish = '',
        public string $tags = '',
        public ?ProductIndexTagMatch $tagMatch = null,
        public ?ProductAgeCategory $ageCategory = null,
        public ?ProductProgress $progress = null,
        public ?ProductScore $score = null,
        public ?ProductPriority $priority = null,
        public ?int $numReListenTimes = null,
        public ?ProductReListenValue $reListenValue = null,
        public string $startDateFrom = '',
        public string $startDateTo = '',
        public string $endDateFrom = '',
        public string $endDateTo = '',
        public string $createdAtFrom = '',
        public string $createdAtTo = '',
        public string $updatedAtFrom = '',
        public string $updatedAtTo = '',
        public ?ProductIndexSort $primarySort = null,
        public ?ProductIndexSort $secondarySort = null,
    ) {}

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
            circle: self::normalizeText($query['circle'] ?? null),
            scenario: self::normalizeText($query['scenario'] ?? null),
            voiceActor: self::normalizeText($query['voice_actor'] ?? null),
            illustration: self::normalizeText($query['illustration'] ?? null),
            author: self::normalizeText($query['author'] ?? null),
            description: self::normalizeText($query['description'] ?? null),
            descriptionEnglish: self::normalizeText($query['description_english'] ?? null),
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
            startDateFrom: self::normalizeDate($query['start_date_from'] ?? null),
            startDateTo: self::normalizeDate($query['start_date_to'] ?? null),
            endDateFrom: self::normalizeDate($query['end_date_from'] ?? null),
            endDateTo: self::normalizeDate($query['end_date_to'] ?? null),
            createdAtFrom: self::normalizeDate($query['created_at_from'] ?? null),
            createdAtTo: self::normalizeDate($query['created_at_to'] ?? null),
            updatedAtFrom: self::normalizeDate($query['updated_at_from'] ?? null),
            updatedAtTo: self::normalizeDate($query['updated_at_to'] ?? null),
            primarySort: $primarySort,
            secondarySort: $secondarySort,
        );
    }

    /**
     * @param  array<string, string>|null  $sortFields
     */
    public static function optionSets(?array $sortFields = null): array
    {
        return [
            'age_categories' => ProductAgeCategory::options(),
            'progress' => ProductProgress::options(),
            'scores' => ProductScore::options(),
            'priorities' => ProductPriority::options(),
            're_listen_values' => ProductReListenValue::options(),
            'tag_match' => ProductIndexTagMatch::options(),
            'sort_fields' => $sortFields ?? ProductIndexSortField::options(),
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
            'circle' => $this->circle,
            'scenario' => $this->scenario,
            'voice_actor' => $this->voiceActor,
            'illustration' => $this->illustration,
            'author' => $this->author,
            'description' => $this->description,
            'description_english' => $this->descriptionEnglish,
            'tags' => $this->tags,
            'tag_match' => $this->tagMatch?->value ?? '',
            'age_category' => $this->ageCategory?->value ?? '',
            'progress' => $this->progress?->value ?? '',
            'score' => $this->score?->value ?? '',
            'priority' => $this->priority?->value ?? '',
            'num_re_listen_times' => $this->numReListenTimes === null ? '' : (string) $this->numReListenTimes,
            're_listen_value' => $this->reListenValue?->value ?? '',
            'start_date_from' => $this->startDateFrom,
            'start_date_to' => $this->startDateTo,
            'end_date_from' => $this->endDateFrom,
            'end_date_to' => $this->endDateTo,
            'created_at_from' => $this->createdAtFrom,
            'created_at_to' => $this->createdAtTo,
            'updated_at_from' => $this->updatedAtFrom,
            'updated_at_to' => $this->updatedAtTo,
            'sort_first_field' => $this->primarySort?->field->value ?? '',
            'sort_first_direction' => $this->primarySort?->direction->value ?? '',
            'sort_second_field' => $this->secondarySort?->field->value ?? '',
            'sort_second_direction' => $this->secondarySort?->direction->value ?? '',
        ];
    }

    public function toQuery(): array
    {
        return collect($this->toInput())
            ->reject(fn(string $value) => $value === '')
            ->all();
    }

    public function toQueryWithout(array|string $keys): array
    {
        return Arr::except($this->toQuery(), $keys);
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

    private static function normalizeDate(mixed $value): string
    {
        $value = self::normalizeText($value);

        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)) {
            return '';
        }

        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])
            ? $value
            : '';
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
