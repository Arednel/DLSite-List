<?php

namespace App\Models;

use App\Enums\LibraryTransferDirection;
use App\Enums\LibraryTransferOperation;
use App\Enums\LibraryTransferRunStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LibraryTransferRun extends Model
{
    public const LIFECYCLE_LOCK = 'library-transfer-lifecycle';

    protected $guarded = ['id'];

    protected $attributes = ['generation' => 1];

    protected $casts = [
        'direction' => LibraryTransferDirection::class,
        'status' => LibraryTransferRunStatus::class,
        'settings' => 'array',
        'warnings' => 'array',
        'generation' => 'integer',
        'job_token' => 'integer',
        'processed' => 'integer',
        'total' => 'integer',
    ];

    public function parts(): HasMany
    {
        return $this->hasMany(LibraryTransferPart::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(LibraryImportItem::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LibraryTransferEntry::class);
    }

    public function directory(): string
    {
        return "Transfers/{$this->id}";
    }

    public function busy(): bool
    {
        return $this->status->isBusy();
    }

    public function acceptsNewParts(): bool
    {
        return $this->direction === LibraryTransferDirection::Import
            && in_array($this->status, [LibraryTransferRunStatus::Uploading, LibraryTransferRunStatus::WaitingForParts], true);
    }

    public function acceptsReplacementParts(): bool
    {
        return $this->direction === LibraryTransferDirection::Import
            && ! in_array($this->status, [LibraryTransferRunStatus::Cancelled, LibraryTransferRunStatus::Failed, LibraryTransferRunStatus::Applying], true);
    }

    public function reviewable(): bool
    {
        return $this->direction === LibraryTransferDirection::Import && $this->status === LibraryTransferRunStatus::Review;
    }

    public function reviewVisible(): bool
    {
        return $this->direction === LibraryTransferDirection::Import && $this->status->isReviewVisible();
    }

    #[Scope]
    protected function activeForCleanup(Builder $query): void
    {
        $query->whereIn('status', array_filter(
            LibraryTransferRunStatus::cases(),
            fn(LibraryTransferRunStatus $status): bool => $status->isCleanupActive(),
        ));
    }

    /** @return list<LibraryTransferRunStatus> */
    public static function supersedableStatuses(LibraryTransferDirection $direction): array
    {
        return $direction->supersedableStatuses();
    }

    public function retryOperation(): ?LibraryTransferOperation
    {
        $operation = $this->settings['retry_operation'] ?? null;

        return is_string($operation) ? LibraryTransferOperation::tryFrom($operation) : null;
    }

    public function retryStatus(): ?LibraryTransferRunStatus
    {
        $status = $this->settings['retry_status'] ?? null;

        return is_string($status) ? LibraryTransferRunStatus::tryFrom($status) : null;
    }
}
