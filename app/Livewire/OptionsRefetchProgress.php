<?php

namespace App\Livewire;

use App\Models\RefetchRun;
use Illuminate\View\View;
use Livewire\Component;

class OptionsRefetchProgress extends Component
{
    public int $runId;

    public function mount(RefetchRun $run): void
    {
        $this->runId = (int) $run->getKey();
    }

    public function refreshProgress(): void
    {
        $run = $this->run();

        if ($run->hasReviewResults()) {
            $this->redirectRoute('options.refetch.show', ['run' => $this->runId]);
        }
    }

    public function render(): View
    {
        $run = $this->run();

        return view('livewire.options-refetch-progress', [
            'run' => $run,
            'progressPercent' => $this->progressPercent($run),
        ]);
    }

    private function run(): RefetchRun
    {
        return RefetchRun::query()->findOrFail($this->runId);
    }

    private function progressPercent(RefetchRun $run): int
    {
        if ($run->total_count === 0) {
            return 0;
        }

        return (int) round(($run->processed_count / $run->total_count) * 100);
    }
}
