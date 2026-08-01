<?php

namespace App\Models;

use App\Enums\RefetchCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RefetchRun extends Model
{
    public const STATUS_RUNNING = 'running';

    public const STATUS_CANCELLING = 'cancelling';

    public const STATUS_REVIEW = 'review';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'batch_id',
        'status',
        'check_images',
        'resolved_tabs',
        'total_count',
        'processed_count',
        'fetched_count',
        'failed_count',
        'started_at',
        'completed_at',
        'cancelled_at',
        'applied_at',
        'rejected_at',
    ];

    protected $casts = [
        'check_images' => 'boolean',
        'resolved_tabs' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'applied_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function results(): HasMany
    {
        return $this->hasMany(RefetchWorkResult::class);
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function isCancelling(): bool
    {
        return $this->status === self::STATUS_CANCELLING;
    }

    public function isActive(): bool
    {
        return $this->isRunning() || $this->isCancelling();
    }

    public function isReview(): bool
    {
        return $this->status === self::STATUS_REVIEW;
    }

    public function isApplied(): bool
    {
        return $this->status === self::STATUS_APPLIED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function hasReviewResults(): bool
    {
        return in_array($this->status, [
            self::STATUS_REVIEW,
            self::STATUS_APPLIED,
            self::STATUS_REJECTED,
        ], true);
    }

    public function canBeCancelled(): bool
    {
        return $this->isRunning();
    }

    public function canBeApplied(): bool
    {
        return $this->isReview()
            && $this->getKey() === self::query()->max($this->getKeyName());
    }

    public function applyUnavailableMessage(): string
    {
        if (! $this->isReview()) {
            return __('This refetch run is not ready to apply.');
        }

        return __('Only the newest refetch run can be applied.');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_RUNNING => __('Running'),
            self::STATUS_CANCELLING => __('Cancelling'),
            self::STATUS_REVIEW => __('Review'),
            self::STATUS_APPLIED => __('Applied'),
            self::STATUS_REJECTED => __('Rejected'),
            default => (string) $this->status,
        };
    }

    public function tabResolved(RefetchCategory|string $category): bool
    {
        $value = $category instanceof RefetchCategory ? $category->value : $category;

        return in_array($value, $this->resolved_tabs ?? [], true);
    }

    public function hasAppliedDecisions(): bool
    {
        return $this->results
            ->contains(fn(RefetchWorkResult $result): bool => ($result->decisions ?? []) !== []);
    }
}
