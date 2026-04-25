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
    ];

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
    protected function searchIndex(Builder $query, string $search): void
    {
        $search = '%' . trim($search) . '%';

        $query->where(function (Builder $searchQuery) use ($search): void {
            $searchQuery->whereLike('id', $search)
                ->orWhereLike('work_name', $search)
                ->orWhereLike('work_name_english', $search)
                ->orWhereLike('series', $search)
                ->orWhereLike('notes', $search)
                ->orWhereHas('genres', fn (Builder $genreQuery) => $genreQuery->whereLike('title', $search));
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
}
