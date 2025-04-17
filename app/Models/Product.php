<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $primaryKey = 'dlsite_product_id';
    public $incrementing = false;

    protected $fillable = [
        'dlsite_product_id',
        'maker_id',
        'work_name',
        'work_name_english',
        'age_category',
        'circle',
        'work_image',
        'genre',
        'genre_english',
        'genre_custom',
        'description_english',
        'sample_images',
        'score',
        'progress',
    ];
}
