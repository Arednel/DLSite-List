<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductLocalization extends Model
{
    public const LOCALE_JAPANESE = 'ja';

    public const LOCALE_ENGLISH = 'en';

    public const SEARCHABLE_TITLE_LOCALES = [
        self::LOCALE_ENGLISH,
    ];

    protected $touches = ['product'];

    protected $fillable = [
        'locale',
        'title',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $localization): void {
            if ($localization->isEmpty()) {
                $localization->delete();
            }
        });
    }

    public function isEmpty(): bool
    {
        return blank($this->title);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
