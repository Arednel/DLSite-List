<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GenreGroup extends Model
{
    use HasFactory;

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
        static::saving(function (GenreGroup $group): void {
            if ($group->order === null) {
                $group->order = (int) self::query()->max('order') + 1;
            }
        });
    }

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'genre_group_genre')
            ->withPivot(['id', 'order'])
            ->withTimestamps()
            ->orderByPivot('order')
            ->orderBy('genres.title');
    }

    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query
            ->orderBy('genre_groups.order')
            ->orderBy('genre_groups.title');
    }

    #[Scope]
    protected function visibleOnIndex(Builder $query): void
    {
        $query->where('genre_groups.hidden_on_index', false);
    }

    #[Scope]
    protected function hiddenOnIndex(Builder $query): void
    {
        $query->where('genre_groups.hidden_on_index', true);
    }
}
