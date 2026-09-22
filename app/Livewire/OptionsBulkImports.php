<?php

namespace App\Livewire;

use App\Models\BulkImportRun;
use App\Support\BulkImport\BulkImportCleanupService;
use Illuminate\View\View;
use Livewire\Attributes\Isolate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

#[Isolate]
class OptionsBulkImports extends Component
{
    use WithPagination;

    private const RUNS_PER_PAGE = 10;

    #[Locked]
    public bool $confirmingCleanup = false;

    #[Locked]
    public ?int $highlightedRunId = null;

    public string $cleanupNotice = '';

    public function mount(): void
    {
        $runId = request()->integer('bulk_import_run');

        $this->highlightedRunId = $runId > 0 ? $runId : null;
    }

    public function render(BulkImportCleanupService $cleanupService): View
    {
        $runs = BulkImportRun::query()
            ->latest('id')
            ->paginate(self::RUNS_PER_PAGE, ['*'], 'bulkImportsPage');

        $cleanupUnavailable = $cleanupService->unavailable();

        return view('livewire.options-bulk-imports', [
            'runs' => $runs,
            'cleanupUnavailable' => $cleanupUnavailable,
        ]);
    }

    #[On('bulk-import-run-finished')]
    public function refreshAfterRunFinished(): void
    {
        // Receiving the child event rerenders this parent so cleanup availability stays current.
    }

    public function askCleanup(BulkImportCleanupService $cleanupService): void
    {
        $this->cleanupNotice = '';
        $this->resetErrorBag('cleanup');

        if ($cleanupService->unavailable()) {
            $this->addError('cleanup', __(BulkImportCleanupService::UNAVAILABLE_MESSAGE));

            return;
        }

        $this->confirmingCleanup = true;
    }

    public function cancelCleanup(): void
    {
        $this->confirmingCleanup = false;
    }

    public function cleanup(BulkImportCleanupService $cleanupService): void
    {
        if (! $this->confirmingCleanup) {
            return;
        }

        try {
            $cleanupService->cleanup();
        } catch (RuntimeException $exception) {
            $this->confirmingCleanup = false;
            $this->addError('cleanup', __($exception->getMessage()));

            return;
        }

        $this->confirmingCleanup = false;
        $this->cleanupNotice = 'Bulk Import history cleaned up.';
        $this->resetPage('bulkImportsPage');
    }

    public function resetConfirmDelaySeconds(): int
    {
        return 0;
    }
}
