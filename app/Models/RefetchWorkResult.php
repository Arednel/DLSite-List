<?php

namespace App\Models;

use App\Enums\RefetchCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefetchWorkResult extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_FETCHED = 'fetched';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'refetch_run_id',
        'product_id',
        'status',
        'changes',
        'decisions',
        'warnings',
        'error',
    ];

    protected $casts = [
        'changes' => 'array',
        'decisions' => 'array',
        'warnings' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(RefetchRun::class, 'refetch_run_id');
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

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => __('Pending'),
            self::STATUS_FETCHED => __('Fetched'),
            self::STATUS_FAILED => __('Failed'),
            default => (string) $this->status,
        };
    }

    public function changesFor(RefetchCategory|string $category): array
    {
        $value = $category instanceof RefetchCategory ? $category->value : $category;

        return $this->getAttribute('changes')[$value] ?? [];
    }

    public function hasChangesFor(RefetchCategory|string $category): bool
    {
        return $this->changesFor($category) !== [];
    }

    public function displayError(): ?string
    {
        if ($this->error === null) {
            return null;
        }

        $translated = __($this->error);

        return is_string($translated) ? $translated : $this->error;
    }
}
