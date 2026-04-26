<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TagRefetchRun extends Model
{
    use HasFactory;

    public const STATUS_RUNNING = 'running';

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
        'applied_at',
    ];

    protected $casts = [
        'selected_product_ids' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
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

    public function isApplied(): bool
    {
        return $this->status === self::STATUS_APPLIED;
    }

    public function hasReviewResults(): bool
    {
        return $this->isReview() || $this->isApplied();
    }

    public function canBeApplied(): bool
    {
        return $this->isReview()
            && $this->getKey() === self::query()->max($this->getKeyName());
    }

    public function applyUnavailableMessage(): string
    {
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
            'added_japanese' => $results->sum(fn (TagRefetchWorkResult $result): int => count($result->added_japanese_tags ?? [])),
            'added_english' => $results->sum(fn (TagRefetchWorkResult $result): int => count($result->added_english_tags ?? [])),
            'stale_japanese' => $results->sum(fn (TagRefetchWorkResult $result): int => count($result->stale_japanese_tags ?? [])),
            'stale_english' => $results->sum(fn (TagRefetchWorkResult $result): int => count($result->stale_english_tags ?? [])),
            'skipped' => $results->filter->isSkipped()->count(),
        ];
    }
}
