<?php

namespace App\Models;

use App\Enums\AutocompleteOrder;
use Illuminate\Database\Eloquent\Model;

class Option extends Model
{
    public const INDEX_PER_PAGE = 'index_per_page';

    public const EDIT_FETCHED_TAGS = 'edit_fetched_tags';

    public const TAG_AUTOCOMPLETE_ORDER = 'tag_autocomplete_order';

    public const SERIES_AUTOCOMPLETE_ORDER = 'series_autocomplete_order';

    public const INDEX_PER_PAGE_UNLIMITED = 'unlimited';

    public const DEFAULT_INDEX_PER_PAGE = 100;

    public const FIXED_INDEX_PER_PAGE_OPTIONS = [
        10,
        25,
        50,
        100,
        250,
        500,
        1000,
    ];

    protected $fillable = [
        'key',
        'value',
    ];

    public static function indexPerPage(): int|string
    {
        $option = self::query()
            ->where('key', self::INDEX_PER_PAGE)
            ->first(['value']);

        return self::normalizeIndexPerPage($option?->value ?? self::DEFAULT_INDEX_PER_PAGE);
    }

    public static function setIndexPerPage(int|string $value): void
    {
        self::query()->updateOrCreate(
            ['key' => self::INDEX_PER_PAGE],
            ['value' => (string) self::normalizeIndexPerPage($value)],
        );
    }

    public static function canEditFetchedTags(): bool
    {
        return self::query()
            ->where('key', self::EDIT_FETCHED_TAGS)
            ->value('value') === '1';
    }

    public static function setCanEditFetchedTags(bool $enabled): void
    {
        self::query()->updateOrCreate(
            ['key' => self::EDIT_FETCHED_TAGS],
            ['value' => $enabled ? '1' : '0'],
        );
    }

    public static function tagAutocompleteOrder(): AutocompleteOrder
    {
        return self::autocompleteOrder(self::TAG_AUTOCOMPLETE_ORDER);
    }

    public static function setTagAutocompleteOrder(AutocompleteOrder|string $order): void
    {
        self::setAutocompleteOrder(self::TAG_AUTOCOMPLETE_ORDER, $order);
    }

    public static function seriesAutocompleteOrder(): AutocompleteOrder
    {
        return self::autocompleteOrder(self::SERIES_AUTOCOMPLETE_ORDER);
    }

    public static function setSeriesAutocompleteOrder(AutocompleteOrder|string $order): void
    {
        self::setAutocompleteOrder(self::SERIES_AUTOCOMPLETE_ORDER, $order);
    }

    public static function normalizeIndexPerPage(mixed $value): int|string
    {
        if ($value === self::INDEX_PER_PAGE_UNLIMITED) {
            return self::INDEX_PER_PAGE_UNLIMITED;
        }

        if (is_numeric($value) && (int) $value > 0) {
            return (int) $value;
        }

        return self::DEFAULT_INDEX_PER_PAGE;
    }

    /**
     * @return array<int, string>
     */
    public static function fixedIndexPerPageOptions(): array
    {
        return collect(self::FIXED_INDEX_PER_PAGE_OPTIONS)
            ->mapWithKeys(fn(int $value): array => [$value => (string) $value])
            ->all();
    }

    private static function autocompleteOrder(string $key): AutocompleteOrder
    {
        return self::normalizeAutocompleteOrder(
            self::query()->where('key', $key)->value('value')
        );
    }

    private static function setAutocompleteOrder(string $key, AutocompleteOrder|string $order): void
    {
        self::query()->updateOrCreate(
            ['key' => $key],
            ['value' => self::normalizeAutocompleteOrder($order)->value],
        );
    }

    private static function normalizeAutocompleteOrder(AutocompleteOrder|string|null $order): AutocompleteOrder
    {
        if ($order instanceof AutocompleteOrder) {
            return $order;
        }

        return AutocompleteOrder::tryFrom((string) $order) ?? AutocompleteOrder::Usage;
    }
}
