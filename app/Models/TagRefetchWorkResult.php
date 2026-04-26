<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TagRefetchWorkResult extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_FETCHED = 'fetched';

    public const STATUS_SKIPPED = 'skipped';

    public const STALE_ACTION_MOVE_TO_CUSTOM = 'move_to_custom';

    public const STALE_ACTION_REMOVE = 'remove';

    protected $fillable = [
        'tag_refetch_run_id',
        'product_id',
        'status',
        'fetched_japanese_tags',
        'fetched_english_tags',
        'added_japanese_tags',
        'added_english_tags',
        'stale_japanese_tags',
        'stale_english_tags',
        'error',
        'stale_japanese_action',
        'stale_english_action',
    ];

    protected $casts = [
        'fetched_japanese_tags' => 'array',
        'fetched_english_tags' => 'array',
        'added_japanese_tags' => 'array',
        'added_english_tags' => 'array',
        'stale_japanese_tags' => 'array',
        'stale_english_tags' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(TagRefetchRun::class, 'tag_refetch_run_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isFetched(): bool
    {
        return $this->status === self::STATUS_FETCHED;
    }

    public function isSkipped(): bool
    {
        return $this->status === self::STATUS_SKIPPED;
    }

    public function hasAddedJapaneseTags(): bool
    {
        return $this->hasTags($this->added_japanese_tags);
    }

    public function hasAddedEnglishTags(): bool
    {
        return $this->hasTags($this->added_english_tags);
    }

    public function hasStaleJapaneseTags(): bool
    {
        return $this->hasTags($this->stale_japanese_tags);
    }

    public function hasStaleEnglishTags(): bool
    {
        return $this->hasTags($this->stale_english_tags);
    }

    public function hasTagChanges(): bool
    {
        return $this->hasAddedJapaneseTags()
            || $this->hasAddedEnglishTags()
            || $this->hasStaleJapaneseTags()
            || $this->hasStaleEnglishTags();
    }

    private function hasTags(?array $tags): bool
    {
        return count($tags ?? []) > 0;
    }
}
