<?php

namespace App\Models;

use App\Enums\ProductContributorRole;
use App\Enums\ProductIndexTagMatch;
use App\Support\VisibleGenreAttachment;
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
            ->withPivot(['id', 'source'])
            ->withTimestamps();
    }

    public function contributors(): BelongsToMany
    {
        return $this->belongsToMany(Contributor::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    public function contributorsForRole(ProductContributorRole|string $role): BelongsToMany
    {
        $role = $role instanceof ProductContributorRole
            ? $role
            : ProductContributorRole::from($role);

        return $this->contributors()
            ->withPivotValue('role', $role->value);
    }

    public function japaneseGenres(): BelongsToMany
    {
        return $this->fetchedGenresForLanguage(Genre::LANGUAGE_JAPANESE);
    }

    public function englishGenres(): BelongsToMany
    {
        return $this->fetchedGenresForLanguage(Genre::LANGUAGE_ENGLISH);
    }

    public function customGenres(): BelongsToMany
    {
        return $this->genres()->wherePivot('source', Genre::PIVOT_SOURCE_CUSTOM);
    }

    private function fetchedGenresForLanguage(string $language): BelongsToMany
    {
        return $this->genres()
            ->wherePivot('source', Genre::PIVOT_SOURCE_FETCHED)
            ->whereExists(function ($query) use ($language): void {
                $query->select('genre_product_languages.id')
                    ->from('genre_product_languages')
                    ->whereColumn('genre_product_languages.genre_product_id', 'genre_product.id')
                    ->where('genre_product_languages.language', $language);
            });
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
            } else {
                $genreQuery->where('title', $genreFilter);
            }

            $genreQuery->where(VisibleGenreAttachment::query());
        });
    }

    #[Scope]
    protected function filterSeries(Builder $query, string $series): void
    {
        $query->where('series', $series);
    }

    #[Scope]
    protected function filterContributor(Builder $query, string $role, string $name): void
    {
        $name = trim($name);

        if ($name === '') {
            return;
        }

        $query->whereHas('contributors', function (Builder $contributorQuery) use ($role, $name): void {
            $contributorQuery->where('contributor_product.role', $role)
                ->whereLike('contributors.name', '%' . $name . '%');
        });
    }

    #[Scope]
    protected function filterCircle(Builder $query, string $circle): void
    {
        $circle = trim($circle);

        if ($circle === '') {
            return;
        }

        $query->where(function (Builder $circleQuery) use ($circle): void {
            $circleQuery
                ->whereLike('circle', '%' . $circle . '%')
                ->orWhereLike('maker_id', '%' . $circle . '%')
                ->orWhereHas('contributors', function (Builder $contributorQuery) use ($circle): void {
                    $contributorQuery->where('contributor_product.role', 'circle')
                        ->whereLike('contributors.name', '%' . $circle . '%');
                });
        });
    }

    #[Scope]
    protected function filterDescription(Builder $query, string $description): void
    {
        $description = '%' . trim($description) . '%';

        $query->where(function (Builder $descriptionQuery) use ($description): void {
            $descriptionQuery
                ->whereLike('description', $description)
                ->orWhereLike('description_english', $description);
        });
    }

    #[Scope]
    protected function searchIndex(Builder $query, string $search): void
    {
        $search = '%' . trim($search) . '%';

        $query->where(function (Builder $searchQuery) use ($search): void {
            $searchQuery->whereAny([
                'id',
                'work_name',
                'work_name_english',
                'series',
                'notes',
                'circle',
                'description',
                'description_english',
            ], 'like', $search)
                ->orWhereHas('contributors', function (Builder $contributorQuery) use ($search): void {
                    $contributorQuery->whereLike('contributors.name', $search);
                })
                ->orWhereHas('genres', function (Builder $genreQuery) use ($search): void {
                    $genreQuery->whereLike('title', $search);
                    $genreQuery->where(VisibleGenreAttachment::query());
                });
        });
    }

    #[Scope]
    protected function filterTitle(Builder $query, string $title): void
    {
        $title = '%' . trim($title) . '%';

        $query->where(function (Builder $titleQuery) use ($title): void {
            $titleQuery->whereLike('work_name', $title)
                ->orWhereLike('work_name_english', $title);
        });
    }

    #[Scope]
    protected function filterNotes(Builder $query, string $notes): void
    {
        $query->whereLike('notes', '%' . trim($notes) . '%');
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
            ->map(fn(string $tag) => mb_strtolower($tag))
            ->unique()
            ->values()
            ->all();

        if ($tagMatch === ProductIndexTagMatch::All) {
            foreach ($normalizedTags as $tag) {
                $query->whereHas('genres', function (Builder $genreQuery) use ($tag): void {
                    $genreQuery->whereLike('title', $tag);
                    $genreQuery->where(VisibleGenreAttachment::query());
                });
            }

            return;
        }

        $query->whereHas('genres', function (Builder $genreQuery) use ($normalizedTags): void {
            $genreQuery->where(function (Builder $tagQuery) use ($normalizedTags): void {
                foreach ($normalizedTags as $index => $tag) {
                    if ($index === 0) {
                        $tagQuery->whereLike('title', $tag);

                        continue;
                    }

                    $tagQuery->orWhereLike('title', $tag);
                }
            });
            $genreQuery->where(VisibleGenreAttachment::query());
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
