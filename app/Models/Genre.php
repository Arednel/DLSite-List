<?php

namespace App\Models;

use App\Support\VisibleGenreAttachment;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        'title',
        'description',
        'order',
        'hidden_on_index',
        'color',
        'text_color',
    ];

    protected $casts = [
        'hidden_on_index' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Genre $genre): void {
            if ($genre->isDirty('title') || blank($genre->title_key)) {
                $genre->title_key = self::titleKey($genre->title);
            }

            if ($genre->order === null) {
                $genre->order = self::nextOrder();
            }
        });
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(GenreGroup::class, 'genre_group_genre')
            ->withPivot(['id', 'order'])
            ->withTimestamps()
            ->orderBy('genre_groups.order')
            ->orderByPivot('order')
            ->orderBy('genre_groups.title');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withTimestamps();
    }

    public function visibleProducts(): BelongsToMany
    {
        return $this->products()->where(VisibleGenreAttachment::query());
    }

    #[Scope]
    protected function visibleOnIndex(Builder $query): void
    {
        $query
            ->where('genres.hidden_on_index', false)
            ->whereDoesntHave('groups', function (Builder $group): void {
                $group->hiddenOnIndex();
            });
    }

    #[Scope]
    protected function hiddenOnIndex(Builder $query): void
    {
        $query->where(function (Builder $hiddenQuery): void {
            $hiddenQuery
                ->where('genres.hidden_on_index', true)
                ->orWhereHas('groups', function (Builder $group): void {
                    $group->hiddenOnIndex();
                });
        });
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
                'description' => null,
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

    private static function nextOrder(): int
    {
        return (int) self::query()->max('order') + 1;
    }
}
