<?php

namespace App\Livewire;

use App\Models\RefetchRun;
use App\Support\Refetch\RefetchCleanupService;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;

class OptionsRefetchActions extends Component
{
    #[Locked]
    public bool $confirmingCleanup = false;

    public string $notice = '';

    public function render(RefetchCleanupService $cleanupService): View
    {
        return view('livewire.options-refetch-actions', [
            'cleanupUnavailable' => $cleanupService->unavailable(),
            'latestRefetchRunId' => RefetchRun::query()->latest('id')->value('id'),
        ]);
    }

    public function askCleanup(RefetchCleanupService $cleanupService): void
    {
        $this->notice = '';
        $this->resetErrorBag('cleanup');

        if ($cleanupService->unavailable()) {
            $this->addError('cleanup', __(RefetchCleanupService::UNAVAILABLE_MESSAGE));

            return;
        }

        $this->confirmingCleanup = true;
    }

    public function cancelCleanup(): void
    {
        $this->confirmingCleanup = false;
    }

    public function cleanup(RefetchCleanupService $cleanupService): void
    {
        if (! $this->confirmingCleanup) {
            return;
        }

        try {
            $cleanupService->cleanup();
        } catch (RuntimeException $exception) {
            $this->confirmingCleanup = false;
            $this->addError('cleanup', __($exception->getMessage()));

            if ($exception->getPrevious() !== null) {
                report($exception);
            }

            return;
        }

        $this->confirmingCleanup = false;
        $this->notice = 'Refetch data cleaned up.';
    }

    public function resetConfirmDelaySeconds(): int
    {
        return 0;
    }
}
