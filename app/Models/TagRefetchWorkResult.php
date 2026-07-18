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

    public const ADDED_ACTION_ADD = 'add_as_fetched';

    public const ADDED_ACTION_IGNORE = 'ignore';

    public const CUSTOM_TO_FETCHED_ACTION_PROMOTE = 'promote_to_fetched';

    public const CUSTOM_TO_FETCHED_ACTION_KEEP_CUSTOM = 'keep_custom';

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
        'custom_to_fetched_japanese_tags',
        'custom_to_fetched_english_tags',
        'error',
        'added_japanese_action',
        'added_english_action',
        'stale_japanese_action',
        'stale_english_action',
        'custom_to_fetched_action',
    ];

    protected $casts = [
        'fetched_japanese_tags' => 'array',
        'fetched_english_tags' => 'array',
        'added_japanese_tags' => 'array',
        'added_english_tags' => 'array',
        'stale_japanese_tags' => 'array',
        'stale_english_tags' => 'array',
        'custom_to_fetched_japanese_tags' => 'array',
        'custom_to_fetched_english_tags' => 'array',
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

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => __('Pending'),
            self::STATUS_FETCHED => __('Fetched'),
            self::STATUS_SKIPPED => __('Skipped'),
            default => (string) $this->status,
        };
    }

    public function displayError(): ?string
    {
        return match ($this->error) {
            'Refetch was cancelled before this work was fetched.',
            'Product no longer exists.',
            'Custom-only work is skipped.',
            'DLSite tag fetch failed.',
            'DLSite tag fetch returned invalid JSON.',
            'GeoBlocked DLSite work',
            'Deleted or Non-existing DLSite work',
            'Non-existing DLSite work' => __($this->error),
            default => $this->error,
        };
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

    public function hasCustomToFetchedJapaneseTags(): bool
    {
        return $this->hasTags($this->custom_to_fetched_japanese_tags);
    }

    public function hasCustomToFetchedEnglishTags(): bool
    {
        return $this->hasTags($this->custom_to_fetched_english_tags);
    }

    public function hasCustomToFetchedTags(): bool
    {
        return $this->hasCustomToFetchedJapaneseTags()
            || $this->hasCustomToFetchedEnglishTags();
    }

    public function hasTagChanges(): bool
    {
        return $this->hasAddedJapaneseTags()
            || $this->hasAddedEnglishTags()
            || $this->hasStaleJapaneseTags()
            || $this->hasStaleEnglishTags()
            || $this->hasCustomToFetchedTags();
    }

    private function hasTags(?array $tags): bool
    {
        return filled($tags);
    }
}
