<?php

namespace App\Models;

use App\Enums\LibraryImportDecision;
use App\Enums\LibraryImportItemStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryImportItem extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['decision' => 'ignore', 'status' => 'pending'];

    protected $casts = [
        'status' => LibraryImportItemStatus::class,
        'decision' => LibraryImportDecision::class,
        'baseline' => 'array',
        'incoming' => 'array',
        'baseline_preview' => 'array',
        'incoming_preview' => 'array',
        'metadata' => 'array',
        'result' => 'array',
        'collection' => 'boolean',
        'decision_override' => 'boolean',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(LibraryTransferRun::class, 'library_transfer_run_id');
    }

    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', LibraryImportItemStatus::Pending);
    }

    #[Scope]
    protected function forSectionCategory(Builder $query, ?string $section = null, ?string $category = null): void
    {
        $query
            ->when($section, fn(Builder $query) => $query->where('section', $section))
            ->when($category, fn(Builder $query) => $query->where('category', $category));
    }
}
