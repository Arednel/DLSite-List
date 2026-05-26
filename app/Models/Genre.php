<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Genre extends Model
{
    use HasFactory;

    public const TYPE_AUTO_GENERATED_JAPANESE = 'auto_generated_japanese';

    public const TYPE_AUTO_GENERATED_ENGLISH = 'auto_generated_english';

    public const TYPE_CUSTOM = 'custom';

    public const LANGUAGE_ENGLISH = 'en';

    public const LANGUAGE_JAPANESE = 'jp';

    public const PIVOT_SOURCE_FETCHED = 'fetched';

    public const PIVOT_SOURCE_CUSTOM = 'custom';

    protected $fillable = [
        'group_id',
        'title',
        'description',
        'order',
    ];

    protected static function booted(): void
    {
        static::saving(function (Genre $genre): void {
            if ($genre->isDirty('title') || blank($genre->title_key)) {
                $genre->title_key = self::titleKey($genre->title);
            }
        });
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(GenreGroup::class, 'group_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withTimestamps();
    }

    public static function resolveIdsFromTitles(array $titles): array
    {
        return collect($titles)
            ->map(fn(mixed $title) => self::normalizeTitle($title))
            ->filter()
            ->unique(fn(string $title): string => self::titleKey($title))
            ->map(fn(string $title) => self::resolveByTitle($title)->getKey())
            ->values()
            ->all();
    }

    public static function resolveByTitle(string $title): self
    {
        $normalizedTitle = self::normalizeTitle($title);

        if ($normalizedTitle === null) {
            throw new \InvalidArgumentException('Genre title must not be empty.');
        }

        return self::query()->firstOrCreate(
            ['title_key' => self::titleKey($normalizedTitle)],
            [
                'title' => $normalizedTitle,
                'group_id' => null,
                'description' => null,
                'order' => null,
            ],
        );
    }

    public static function titleKey(mixed $title): string
    {
        return mb_convert_case(trim((string) $title), MB_CASE_FOLD, 'UTF-8');
    }

    private static function normalizeTitle(mixed $title): ?string
    {
        if ($title === null) {
            return null;
        }

        $normalizedTitle = trim((string) $title);

        return $normalizedTitle === '' ? null : $normalizedTitle;
    }
}
