<?php

namespace App\Models;

use App\Enums\LibraryTransferPartStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LibraryTransferPart extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['status' => 'pending'];

    protected $casts = [
        'status' => LibraryTransferPartStatus::class,
        'manifest' => 'array',
        'candidate' => 'array',
        'bytes' => 'integer',
        'validation_token' => 'integer',
        'number' => 'integer',
        'entry_count' => 'integer',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(LibraryTransferRun::class, 'library_transfer_run_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LibraryTransferEntry::class);
    }

    #[Scope]
    protected function awaitingInspection(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query->where('status', LibraryTransferPartStatus::Pending)->orWhereNotNull('candidate');
        });
    }
}
