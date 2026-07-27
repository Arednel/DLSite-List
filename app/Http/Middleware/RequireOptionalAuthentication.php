<?php

namespace App\Http\Middleware;

use App\Models\Option;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireOptionalAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Option::userAuthenticationEnabled()) {
            return $next($request);
        }

        $this->clearInactiveRecoveryState();

        if (User::query()->doesntExist()) {
            if ($request->routeIs('admin.setup', 'admin.setup.store', 'password.help')) {
                return $next($request);
            }

            return redirect()->guest(route('admin.setup'));
        }

        if ($this->passwordRecoveryEnabled()) {
            if ($request->routeIs('admin.recovery', 'admin.recovery.store', 'password.help')) {
                return $next($request);
            }

            return redirect()->route('admin.recovery');
        }

        if ($request->routeIs('login', 'login.authenticate', 'password.help')) {
            return $next($request);
        }

        if (Auth::guard('web')->guest()) {
            return redirect()->guest(route('login'));
        }

        return $next($request);
    }

    private function passwordRecoveryEnabled(): bool
    {
        return (bool) config('auth.admin_password_reset');
    }

    private function clearInactiveRecoveryState(): void
    {
        if (! $this->passwordRecoveryEnabled() && Option::adminPasswordResetConsumed()) {
            Option::setAdminPasswordResetConsumed(false);
        }
    }
}
