<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConfirmsOptionReset;
use App\Models\Option;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class ProductFormModalSettings extends Component
{
    use ConfirmsOptionReset;

    public bool $enabled = false;

    public string $completionAction = Option::PRODUCT_FORM_MODAL_COMPLETION_REDIRECT;

    public function mount(): void
    {
        $this->syncFromOptions();
    }

    public function render(): View
    {
        return view('livewire.product-form-modal-settings', [
            'completionOptions' => Option::productFormModalCompletionOptions(),
            'completionHelp' => [
                Option::PRODUCT_FORM_MODAL_COMPLETION_REDIRECT => 'Navigate this browser page to calculated Index URL, preserving the current redirect, filter, page, and work-anchor behavior.',
                Option::PRODUCT_FORM_MODAL_COMPLETION_REFRESH => 'Close the modal and reload the page that opened it. Quick Add from Options or Tag Library stays on that page.',
                Option::PRODUCT_FORM_MODAL_COMPLETION_CLOSE => 'Close the modal without navigating or refreshing. The visible page may remain stale until it is reloaded.',
            ],
        ]);
    }

    public function save(): void
    {
        $this->validate([
            'enabled' => ['boolean'],
            'completionAction' => [
                'required',
                Rule::in(array_keys(Option::PRODUCT_FORM_MODAL_COMPLETION_OPTIONS)),
            ],
        ]);

        Option::setProductFormModalEnabled($this->enabled);
        Option::setProductFormModalCompletionAction($this->completionAction);
        $this->syncFromOptions();
        $this->markSaved('Work form modal settings saved.');
        $this->dispatchSettingsUpdated();
    }

    public function resetToDefault(): void
    {
        Option::resetProductFormModalSettingsToDefault();
        $this->syncFromOptions();
        $this->completeResetWithNotice('Work form modal settings reset to default.');
        $this->dispatchSettingsUpdated();
    }

    #[On('options-defaults-reset')]
    public function refreshFromSettings(): void
    {
        $this->syncFromOptions();
        $this->clearSavedNotice();
        $this->dispatchSettingsUpdated();
    }

    public function updated(string $property): void
    {
        if (! in_array($property, ['enabled', 'completionAction'], true)) {
            return;
        }

        $this->clearSavedNotice();
    }

    private function syncFromOptions(): void
    {
        $this->enabled = Option::productFormModalEnabled();
        $this->completionAction = Option::productFormModalCompletionAction();
    }

    private function dispatchSettingsUpdated(): void
    {
        $this->dispatch(
            'work-form-modal-settings-updated',
            enabled: $this->enabled,
            completionAction: $this->completionAction,
        );
    }
}
