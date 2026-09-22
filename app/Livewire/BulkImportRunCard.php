<?php

namespace App\Livewire;

use App\Models\BulkImportItem;
use App\Models\BulkImportRun;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Isolate;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Isolate]
class BulkImportRunCard extends Component
{
    #[Locked]
    public int $runId;

    #[Locked]
    public bool $highlighted = false;

    #[Locked]
    public bool $active = false;

    public function mount(int $runId, bool $highlighted = false): void
    {
        $this->runId = $runId;
        $this->highlighted = $highlighted;
        $this->active = BulkImportRun::query()->findOrFail($runId)->isActive();
    }

    public function refreshRun(): void
    {
        $run = BulkImportRun::query()->find($this->runId);
        $isActive = $run?->isActive() ?? false;
        $wasActive = $this->active;

        $this->active = $isActive;

        if ($wasActive && ! $isActive) {
            $this->dispatch('bulk-import-run-finished');
        }
    }

    public function render(): View
    {
        $run = BulkImportRun::query()
            ->with('currentItem')
            ->findOrFail($this->runId);
        $issues = $this->issuesForRun($run);

        return view('livewire.bulk-import-run-card', [
            'run' => $run,
            'issues' => $issues,
            'issueCount' => $issues->count() + ($run->error ? 1 : 0),
        ]);
    }

    /** @return Collection<int, BulkImportItem> */
    private function issuesForRun(BulkImportRun $run): Collection
    {
        return $run->items()
            ->where(function ($query): void {
                $query->whereNotNull('error')
                    ->orWhereNotNull('warning');
            })
            ->orderBy('position')
            ->get();
    }
}
