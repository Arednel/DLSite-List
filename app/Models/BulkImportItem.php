<?php

namespace App\Models;

use App\Enums\BulkImportItemStatus;
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

    public function displayError(): ?string
    {
        if ($this->error === null) {
            return null;
        }

        $translated = __($this->error);

        return is_string($translated) ? $translated : $this->error;
    }
}
