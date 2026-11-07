<?php

namespace App\Models;

use App\Enums\BulkImportItemStatus;
use App\Support\DLSite\DLSiteFetchResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkImportItem extends Model
{
    protected $fillable = [
        'bulk_import_run_id',
        'position',
        'status',
        'product_id',
        'warning',
        'error',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => BulkImportItemStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(BulkImportRun::class, 'bulk_import_run_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function displayError(): ?string
    {
        return $this->error === null || $this->error === ''
            ? $this->error
            : __('errors.' . $this->error);
    }

    public function displayWarning(): ?string
    {
        return DLSiteFetchResult::displayImageFailureMessage($this->warning);
    }
}
