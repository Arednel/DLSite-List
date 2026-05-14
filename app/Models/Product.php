<?php

namespace App\Models;

use App\Enums\ProductIndexTagMatch;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $fillable = [
        'id',
        'maker_id',
        'work_name',
        'work_name_english',
        'age_category',
        'circle',
        'work_image',
        'description',
        'description_english',
        'notes',
        'sample_images',
        'score',
        'series',
        'progress',
        'start_date',
        'end_date',
        'num_re_listen_times',
        're_listen_value',
        'priority',
    ];

    protected $casts = [
        'start_date' => 'array',
        'end_date' => 'array',
        'rj_number' => 'integer',
        'start_date_sort' => 'integer',
        'end_date_sort' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            $product->syncIndexKeys();
        });
    }

    public function syncIndexKeys(): void
    {
        $this->rj_number = self::rjNumberFromId($this->id);
        $this->start_date_sort = self::dateSortValue($this->start_date);
        $this->end_date_sort = self::dateSortValue($this->end_date);
    }

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class)
            ->withPivot('source')
            ->withTimestamps();
    }

    public function japaneseGenres(): BelongsToMany
    {
        return $this->genres()
            ->where('genres.type', Genre::TYPE_AUTO_GENERATED_JAPANESE)
            ->wherePivot('source', Genre::PIVOT_SOURCE_FETCHED);
    }

    public function englishGenres(): BelongsToMany
    {
        return $this->genres()
            ->where('genres.type', Genre::TYPE_AUTO_GENERATED_ENGLISH)
            ->wherePivot('source', Genre::PIVOT_SOURCE_FETCHED);
    }

    public function customGenres(): BelongsToMany
    {
        return $this->genres()->wherePivot('source', Genre::PIVOT_SOURCE_CUSTOM);
    }

    #[Scope]
    protected function filterGenre(Builder $query, string $genreFilter): void
    {
        $genreFilter = trim($genreFilter);

        if ($genreFilter === '') {
            return;
        }

        $query->whereHas('genres', function (Builder $genreQuery) use ($genreFilter): void {
            if (ctype_digit($genreFilter)) {
                $genreQuery->whereKey((int) $genreFilter);

                return;
            }

            $genreQuery->where('title', $genreFilter);
        });
    }

    #[Scope]
    protected function filterSeries(Builder $query, string $series): void
    {
        $query->where('series', $series);
    }

    #[Scope]
    protected function searchIndex(Builder $query, string $search): void
    {
        $search = '%'.trim($search).'%';

        $query->where(function (Builder $searchQuery) use ($search): void {
            $searchQuery->whereAny([
                'id',
                'work_name',
                'work_name_english',
                'series',
                'notes',
            ], 'like', $search)
                ->orWhereHas('genres', fn (Builder $genreQuery) => $genreQuery->whereLike('title', $search));
        });
    }

    #[Scope]
    protected function filterTitle(Builder $query, string $title): void
    {
        $title = '%'.trim($title).'%';

        $query->where(function (Builder $titleQuery) use ($title): void {
            $titleQuery->whereLike('work_name', $title)
                ->orWhereLike('work_name_english', $title);
        });
    }

    #[Scope]
    protected function filterNotes(Builder $query, string $notes): void
    {
        $query->whereLike('notes', '%'.trim($notes).'%');
    }

    #[Scope]
    protected function orderByNumericRj(Builder $query, string $direction = 'desc'): void
    {
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        $query->orderBy('rj_number', $direction);
    }

    #[Scope]
    protected function filterTags(
        Builder $query,
        array $tags,
        ProductIndexTagMatch $tagMatch = ProductIndexTagMatch::Any,
    ): void {
        if ($tags === []) {
            return;
        }

        $normalizedTags = collect($tags)
            ->map(fn (string $tag) => mb_strtolower($tag))
            ->unique()
            ->values()
            ->all();

        if ($tagMatch === ProductIndexTagMatch::All) {
            foreach ($normalizedTags as $tag) {
                $query->whereHas('genres', function (Builder $genreQuery) use ($tag): void {
                    $genreQuery->whereRaw('LOWER(title) = ?', [$tag]);
                });
            }

            return;
        }

        $query->whereHas('genres', function (Builder $genreQuery) use ($normalizedTags): void {
            $genreQuery->where(function (Builder $tagQuery) use ($normalizedTags): void {
                foreach ($normalizedTags as $index => $tag) {
                    if ($index === 0) {
                        $tagQuery->whereRaw('LOWER(title) = ?', [$tag]);

                        continue;
                    }

                    $tagQuery->orWhereRaw('LOWER(title) = ?', [$tag]);
                }
            });
        });
    }

    public static function rjNumberFromId(?string $id): ?int
    {
        if ($id === null || ! preg_match('/^RJ(\d+)$/i', $id, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    public static function dateSortValue(mixed $date): ?int
    {
        if (! is_array($date)) {
            return null;
        }

        $year = self::dateSortPart($date['year'] ?? null);
        $month = self::dateSortPart($date['month'] ?? null);
        $day = self::dateSortPart($date['day'] ?? null);

        if ($year === null && $month === null && $day === null) {
            return null;
        }

        return (int) sprintf('%04d%02d%02d', $year ?? 0, $month ?? 0, $day ?? 0);
    }

    private static function dateSortPart(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
