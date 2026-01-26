<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    public $incrementing = false;

    protected $fillable = [
        'id',
        'maker_id',
        'work_name',
        'work_name_english',
        'age_category',
        'circle',
        'work_image',
        'genre',
        'genre_english',
        'genre_custom',
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
        'genre_custom' => 'array',
        'start_date' => 'array',
        'end_date' => 'array',
    ];
}
