<?php

namespace App\Livewire\Concerns;

trait ConfirmsOptionReset
{
    public bool $confirmingResetToDefault = false;

    public bool $saved = false;

    public string $notice = '';

    public function askResetToDefault(): void
    {
        $this->confirmingResetToDefault = true;
        $this->resetValidation();
    }

    public function cancelResetToDefault(): void
    {
        $this->confirmingResetToDefault = false;
    }

    public function resetConfirmDelaySeconds(): int
    {
        return 0;
    }

    protected function closeResetConfirmation(): void
    {
        $this->confirmingResetToDefault = false;
    }

    protected function markSaved(string $notice): void
    {
        $this->saved = true;
        $this->notice = $notice;
    }

    protected function clearSavedNotice(): void
    {
        $this->saved = false;
        $this->notice = '';
        $this->resetValidation();
    }

    protected function completeResetWithNotice(string $notice): void
    {
        $this->markSaved($notice);
        $this->resetValidation();
        $this->closeResetConfirmation();
    }
}
