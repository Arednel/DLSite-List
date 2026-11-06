<?php

namespace App\Livewire;

use App\Livewire\Concerns\ConfirmsOptionReset;
use App\Support\UserStylesheet;
use App\Support\UserStylesheetPromotion;
use Illuminate\View\View;
use Livewire\Component;

class CustomCssSettings extends Component
{
    use ConfirmsOptionReset;

    private const NOTICE_SESSION_KEY = 'custom_css_notice';

    public string $css = '';

    public function mount(UserStylesheet $stylesheet): void
    {
        $this->css = $stylesheet->contents();
    }

    public function render(): View
    {
        return view('livewire.custom-css-settings');
    }

    public function save(UserStylesheetPromotion $promotion): void
    {
        $this->validate([
            'css' => ['string'],
        ]);

        $promotion->save($this->css);

        session()->flash(self::NOTICE_SESSION_KEY, 'Custom CSS saved.');

        $this->redirectRoute('options.index', ['tab' => 'display']);
    }

    public function resetToDefault(UserStylesheetPromotion $promotion): void
    {
        $promotion->reset();

        session()->flash(self::NOTICE_SESSION_KEY, 'Custom CSS reset to default.');

        $this->redirectRoute('options.index', ['tab' => 'display']);
    }
}
