<?php

namespace App\Models;

use App\Enums\BulkImportItemStatus;
use App\Enums\BulkImportRunStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BulkImportRun extends Model
{
    public const LIFECYCLE_LOCK = 'bulk-import-lifecycle';

    public const LIFECYCLE_LOCK_SECONDS = 60;

    protected $fillable = [
        'status',
        'input_snapshot',
        'total_count',
        'processed_count',
        'imported_count',
        'failed_count',
        'skipped_count',
        'started_at',
        'completed_at',
        'failed_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'status' => BulkImportRunStatus::class,
            'input_snapshot' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(BulkImportItem::class);
    }

    public function currentItem(): HasOne
    {
        return $this->hasOne(BulkImportItem::class)
            ->where('status', BulkImportItemStatus::Importing->value);
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function displayError(): ?string
    {
        if ($this->error === null) {
            return null;
        }

        $translated = __($this->error);

        return is_string($translated) ? $translated : $this->error;
    }

    public function remainingCount(): int
    {
        return max(0, $this->total_count - $this->processed_count);
    }

    public function progressPercent(): float
    {
        if ($this->total_count === 0) {
            return 100.0;
        }

        return min(100.0, ($this->processed_count / $this->total_count) * 100);
    }
}
