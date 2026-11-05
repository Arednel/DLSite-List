<?php

namespace App\Livewire;

use App\Models\Option;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Livewire\Component;

class AuthenticationSettings extends Component
{
    public bool $enabled = false;

    public string $currentPassword = '';

    public string $newPassword = '';

    public string $newPasswordConfirmation = '';

    public bool $saved = false;

    public string $notice = '';

    public function mount(): void
    {
        $this->enabled = Option::userAuthenticationEnabled();
    }

    public function render(): View
    {
        return view('livewire.authentication-settings', [
            'accountExists' => User::query()->exists(),
        ]);
    }

    public function saveSettings(): void
    {
        $this->validate([
            'enabled' => ['boolean'],
        ]);

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
            'currentPassword' => [
                'bail',
                'required',
                'string',
                'max:' . config('auth.password_max_length'),
                'current_password:web',
            ],
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

        $this->reset('currentPassword', 'newPassword', 'newPasswordConfirmation');

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
