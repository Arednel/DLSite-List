<?php

namespace App\Livewire;

use App\Models\TagRefetchRun;
use Illuminate\View\View;
use Livewire\Component;

class OptionsRefetchProgress extends Component
{
    public int $runId;

    public function mount(TagRefetchRun $run): void
    {
        $this->runId = (int) $run->getKey();
    }

    public function refreshProgress(): void
    {
        $run = $this->run();

        if ($run->hasReviewResults()) {
            $this->redirectRoute('options.refetch-tags.show', ['run' => $this->runId]);
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

    private function run(): TagRefetchRun
    {
        return TagRefetchRun::query()->findOrFail($this->runId);
    }

    private function progressPercent(TagRefetchRun $run): int
    {
        if ($run->total_count === 0) {
            return 0;
        }

        return (int) round(($run->processed_count / $run->total_count) * 100);
    }
}
