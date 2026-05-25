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
            ->map(fn(string $title) => self::resolveByTitle($title)->getKey())
            ->unique()
            ->values()
            ->all();
    }

    public static function resolveByTitle(string $title): self
    {
        return self::query()->firstOrCreate(
            ['title' => $title],
            [
                'group_id' => null,
                'description' => null,
                'order' => null,
            ],
        );
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
