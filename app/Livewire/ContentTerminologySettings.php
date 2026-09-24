<?php

namespace App\Livewire;

use App\Enums\ContentFocus;
use App\Livewire\Concerns\ConfirmsOptionReset;
use App\Models\Option;
use App\Support\ContentTerminology;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

class ContentTerminologySettings extends Component
{
    use ConfirmsOptionReset;

    private const NOTICE_SESSION_KEY = 'content_terminology_notice';

    public string $focus = ContentFocus::General->value;

    public function mount(): void
    {
        $this->focus = Option::contentFocus()->value;
    }

    public function render(): View
    {
        $selectedFocus = ContentFocus::tryFrom($this->focus) ?? Option::contentFocus();
        $terminology = new ContentTerminology($selectedFocus);

        return view('livewire.content-terminology-settings', [
            'focusOptions' => ContentFocus::options(),
            'previewRows' => $terminology->previewRows(),
        ]);
    }

    public function save(): void
    {
        $this->validate([
            'focus' => ['required', Rule::enum(ContentFocus::class)],
        ]);

        Option::setContentFocus($this->focus);

        session()->flash(
            self::NOTICE_SESSION_KEY,
            'UI wording saved.',
        );

        $this->redirectRoute('options.index', ['tab' => 'general']);
    }

    public function resetToDefault(): void
    {
        Option::resetContentFocusToDefault();

        session()->flash(
            self::NOTICE_SESSION_KEY,
            'UI wording reset to default.',
        );

        $this->redirectRoute('options.index', ['tab' => 'general']);
    }
}
