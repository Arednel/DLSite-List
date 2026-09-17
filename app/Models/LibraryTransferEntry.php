<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryTransferEntry extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'generation' => 'integer',
        'position' => 'integer',
        'fragment_number' => 'integer',
        'fragment_count' => 'integer',
        'bytes' => 'integer',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(LibraryTransferRun::class, 'library_transfer_run_id');
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(LibraryTransferPart::class, 'library_transfer_part_id');
    }
}
