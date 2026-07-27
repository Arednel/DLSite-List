<?php

namespace App\Http\Controllers;

use App\Models\Option;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthenticationController extends Controller
{
    private const MAX_LOGIN_ATTEMPTS = 5;

    private const LOGIN_DECAY_SECONDS = 300;

    public function login(): View|RedirectResponse
    {
        if (! Option::userAuthenticationEnabled()) {
            return redirect()->route('index');
        }

        if (User::query()->doesntExist()) {
            return redirect()->route('admin.setup');
        }

        if (Auth::guard('web')->check()) {
            return redirect()->route('index');
        }

        return view('auth.login', $this->themeData());
    }

    public function authenticate(Request $request): RedirectResponse
    {
        if (! Option::userAuthenticationEnabled()) {
            return redirect()->route('index');
        }

        if (User::query()->doesntExist()) {
            return redirect()->route('admin.setup');
        }

        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $limiterKey = $this->loginLimiterKey($request);

        if (RateLimiter::tooManyAttempts($limiterKey, self::MAX_LOGIN_ATTEMPTS)) {
            return back()
                ->withErrors([
                    'username' => __(
                        'Too many login attempts. Try again in :seconds seconds.',
                        ['seconds' => RateLimiter::availableIn($limiterKey)],
                    ),
                ])
                ->onlyInput('username');
        }

        if (! Auth::guard('web')->attemptWhen(
            [
                'username' => $credentials['username'],
                'password' => $credentials['password'],
            ],
            static fn(User $user): bool => $user->username === $credentials['username'],
            $request->boolean('remember'),
        )) {
            RateLimiter::hit($limiterKey, self::LOGIN_DECAY_SECONDS);

            return back()
                ->withErrors(['username' => __('The provided credentials are incorrect.')])
                ->onlyInput('username');
        }

        RateLimiter::clear($limiterKey);
        $request->session()->regenerate();

        return redirect()->intended(route('index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route(
            Option::userAuthenticationEnabled() ? 'login' : 'index',
        );
    }

    public function setup(): View|RedirectResponse
    {
        if (! Option::userAuthenticationEnabled()) {
            return redirect()->route('options.index', ['tab' => 'authentication']);
        }

        if (User::query()->exists()) {
            return redirect()->route(Auth::guard('web')->check() ? 'index' : 'login');
        }

        return view('auth.setup', $this->themeData());
    }

    public function storeAdmin(Request $request): RedirectResponse
    {
        if (! Option::userAuthenticationEnabled()) {
            return redirect()->route('options.index', ['tab' => 'authentication']);
        }

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($validated): ?User {
            Option::query()
                ->where('key', Option::USER_AUTHENTICATION_ENABLED)
                ->lockForUpdate()
                ->first();

            if (User::query()->exists()) {
                return null;
            }

            return User::query()->create([
                'username' => $validated['username'],
                'password' => $validated['password'],
                'user_privilege' => 'Admin',
            ]);
        });

        if (! $user) {
            return redirect()->route(Auth::guard('web')->check() ? 'index' : 'login');
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->route('index');
    }

    public function help(): View
    {
        return view('auth.help', $this->themeData());
    }

    public function recovery(): View|RedirectResponse
    {
        if (! $this->recoveryAvailable()) {
            return redirect()->route(
                Option::userAuthenticationEnabled() ? 'login' : 'index',
            );
        }

        return view('auth.recovery', [
            ...$this->themeData(),
            'consumed' => Option::adminPasswordResetConsumed(),
            'accountCount' => User::query()->count(),
        ]);
    }

    public function resetFromEnvironment(Request $request): RedirectResponse
    {
        if (! $this->recoveryAvailable()) {
            return redirect()->route(
                Option::userAuthenticationEnabled() ? 'login' : 'index',
            );
        }

        if (Option::adminPasswordResetConsumed()) {
            return redirect()->route('admin.recovery');
        }

        if (User::query()->count() !== 1) {
            return back()->withErrors([
                'password' => __('Password recovery requires exactly one administrator account.'),
            ]);
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        DB::transaction(function () use ($validated): void {
            User::query()->sole()->replacePassword($validated['password']);
            Option::setAdminPasswordResetConsumed(true);
        });

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.recovery');
    }

    /**
     * @return array{authTheme: string}
     */
    private function themeData(): array
    {
        return ['authTheme' => Option::authenticationPageTheme()];
    }

    private function recoveryAvailable(): bool
    {
        return Option::userAuthenticationEnabled()
            && (bool) config('auth.admin_password_reset')
            && User::query()->exists();
    }

    private function loginLimiterKey(Request $request): string
    {
        return 'admin-login:' . $request->ip();
    }
}
