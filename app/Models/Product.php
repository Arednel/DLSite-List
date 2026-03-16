<?php

namespace App\Models;

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
        return $this->belongsToMany(Genre::class)->withTimestamps();
    }

    public function japaneseGenres(): BelongsToMany
    {
        return $this->genres()->where('genres.type', Genre::TYPE_AUTO_GENERATED_JAPANESE);
    }

    public function englishGenres(): BelongsToMany
    {
        return $this->genres()->where('genres.type', Genre::TYPE_AUTO_GENERATED_ENGLISH);
    }

    public function customGenres(): BelongsToMany
    {
        return $this->genres()->where('genres.type', Genre::TYPE_CUSTOM);
    }
}
