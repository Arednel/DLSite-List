<?php

namespace App\Livewire;

use App\Models\Option;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Livewire\Component;

class AuthenticationSettings extends Component
{
    public bool $enabled = false;

    public string $theme = Option::AUTHENTICATION_PAGE_THEME_CHERRY;

    public string $newPassword = '';

    public string $newPasswordConfirmation = '';

    public bool $saved = false;

    public string $notice = '';

    public function mount(): void
    {
        $this->enabled = Option::userAuthenticationEnabled();
        $this->theme = Option::authenticationPageTheme();
    }

    public function render(): View
    {
        return view('livewire.authentication-settings', [
            'themeOptions' => Option::authenticationPageThemeOptions(),
            'accountExists' => User::query()->exists(),
        ]);
    }

    public function saveSettings(): void
    {
        $this->validate([
            'enabled' => ['boolean'],
            'theme' => ['required', Rule::in(array_keys(Option::AUTHENTICATION_PAGE_THEME_OPTIONS))],
        ]);

        Option::setAuthenticationPageTheme($this->theme);
        Option::setUserAuthenticationEnabled($this->enabled);

        if ($this->enabled && User::query()->doesntExist()) {
            $this->redirectRoute('admin.setup');

            return;
        }

        if ($this->enabled && Auth::guard('web')->guest()) {
            $this->redirectRoute('login');

            return;
        }

        $this->saved = true;
        $this->notice = 'Authentication settings saved.';
    }

    public function changePassword(): void
    {
        abort_unless(Auth::guard('web')->check(), 403);

        $this->validate([
            'newPassword' => [
                'required',
                'same:newPasswordConfirmation',
                Password::defaults(),
            ],
            'newPasswordConfirmation' => ['required'],
        ]);

        /** @var User $user */
        $user = Auth::guard('web')->user();
        $user->replacePassword($this->newPassword);

        $this->reset('newPassword', 'newPasswordConfirmation');

        Auth::guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirectRoute(
            Option::userAuthenticationEnabled() ? 'login' : 'options.index',
            Option::userAuthenticationEnabled() ? [] : ['tab' => 'authentication'],
        );
    }

    public function updated(): void
    {
        $this->saved = false;
        $this->notice = '';
    }
}
