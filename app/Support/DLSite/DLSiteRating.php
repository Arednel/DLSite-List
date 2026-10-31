<?php

namespace App\Support\DLSite;

/**
 * DLsite's average star rating and the number of ratings behind it.
 *
 * Both values are null when the work has no ratings yet. A missing rating
 * object (null instead of this class) means the rating could not be read.
 */
final readonly class DLSiteRating
{
    /** Keys used by DLsite's product info endpoint, the scraper JSON and archives. */
    public const AVERAGE_KEY = 'rate_average_2dp';

    public const COUNT_KEY = 'rate_count';

    public const MAX_AVERAGE = 5;

    public const MAX_COUNT = 4294967295;

    public function __construct(
        public ?float $average,
        public ?int $count,
    ) {}

    /** Reads the first locale that carries rating keys; null when none does. */
    public static function fromLocales(array ...$locales): ?self
    {
        foreach ($locales as $locale) {
            if (array_key_exists(self::AVERAGE_KEY, $locale) || array_key_exists(self::COUNT_KEY, $locale)) {
                return self::from($locale[self::AVERAGE_KEY] ?? null, $locale[self::COUNT_KEY] ?? null);
            }
        }

        return null;
    }

    /** Keeps a rating only when both values are usable, so the pair never disagrees. */
    public static function from(mixed $average, mixed $count): self
    {
        $average = self::average($average);
        $count = self::count($count);

        if ($average === null || $count === null || $count === 0) {
            return new self(null, null);
        }

        return new self($average, $count);
    }

    public static function average(mixed $value): ?float
    {
        if (is_bool($value) || ! is_numeric($value)) {
            return null;
        }

        $value = round((float) $value, 2);

        return $value >= 0 && $value <= self::MAX_AVERAGE ? $value : null;
    }

    public static function count(mixed $value): ?int
    {
        if (is_bool($value) || is_float($value) && floor($value) !== $value) {
            return null;
        }

        $value = filter_var(is_float($value) ? (int) $value : $value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => self::MAX_COUNT],
        ]);

        return $value === false ? null : $value;
    }

    /** @return array{dlsite_rating: ?float, dlsite_rating_count: ?int} */
    public function attributes(): array
    {
        return [
            'dlsite_rating' => $this->average,
            'dlsite_rating_count' => $this->count,
        ];
    }
}
