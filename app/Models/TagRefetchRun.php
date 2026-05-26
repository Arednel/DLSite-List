<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TagRefetchRun extends Model
{
    use HasFactory;

    public const STATUS_RUNNING = 'running';

    public const STATUS_CANCELLING = 'cancelling';

    public const STATUS_REVIEW = 'review';

    public const STATUS_APPLIED = 'applied';

    protected $fillable = [
        'batch_id',
        'status',
        'selected_product_ids',
        'total_count',
        'processed_count',
        'fetched_count',
        'skipped_count',
        'started_at',
        'completed_at',
        'cancelled_at',
        'applied_at',
    ];

    protected $casts = [
        'selected_product_ids' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    public function results(): HasMany
    {
        return $this->hasMany(TagRefetchWorkResult::class);
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function isReview(): bool
    {
        return $this->status === self::STATUS_REVIEW;
    }

    public function isCancelling(): bool
    {
        return $this->status === self::STATUS_CANCELLING;
    }

    public function isApplied(): bool
    {
        return $this->status === self::STATUS_APPLIED;
    }

    public function isActive(): bool
    {
        return $this->isRunning() || $this->isCancelling();
    }

    public function wasCancelled(): bool
    {
        return ($this->attributes['cancelled_at'] ?? null) !== null;
    }

    public function canBeCancelled(): bool
    {
        return $this->isRunning();
    }

    public function hasReviewResults(): bool
    {
        return $this->isReview() || $this->isApplied();
    }

    public function canBeApplied(): bool
    {
        if (! $this->isReview()) {
            return false;
        }

        return $this->getKey() === self::query()->max($this->getKeyName());
    }

    public function applyUnavailableMessage(): string
    {
        if ($this->isCancelling()) {
            return 'This refetch run is still cancelling.';
        }

        if (! $this->isReview()) {
            return 'This refetch run is not ready to apply.';
        }

        return 'Only the newest refetch run can be applied.';
    }

    /**
     * @return array<string, int>
     */
    public function summary(): array
    {
        $results = $this->results;

        return [
            'added_japanese' => $results->sum(fn(TagRefetchWorkResult $result): int => count($result->added_japanese_tags ?? [])),
            'added_english' => $results->sum(fn(TagRefetchWorkResult $result): int => count($result->added_english_tags ?? [])),
            'stale_japanese' => $results->sum(fn(TagRefetchWorkResult $result): int => count($result->stale_japanese_tags ?? [])),
            'stale_english' => $results->sum(fn(TagRefetchWorkResult $result): int => count($result->stale_english_tags ?? [])),
            'custom_to_fetched' => $results->sum(
                fn(TagRefetchWorkResult $result): int => count($result->custom_to_fetched_japanese_tags ?? [])
                    + count($result->custom_to_fetched_english_tags ?? [])
            ),
            'skipped' => $results->filter->isSkipped()->count(),
        ];
    }
}
