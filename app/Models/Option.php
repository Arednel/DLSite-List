<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Option extends Model
{
    public const INDEX_PER_PAGE = 'index_per_page';

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
            ->mapWithKeys(fn (int $value): array => [$value => (string) $value])
            ->all();
    }
}
