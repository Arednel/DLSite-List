@extends('layouts.auth', [
    'title' => __('Environment Password Recovery'),
    'subtitle' => __('Recovery mode was enabled through ADMIN_PASSWORD_RESET.'),
])

@section('content')
    @if ($consumed)
        <div class="auth-help">
            <div class="auth-success">
                {{ __('The administrator password was reset successfully.') }}
            </div>
            <p>
                {{ __('Remove ADMIN_PASSWORD_RESET from the active environment, then restart the PHP/web process or recreate the Docker app container.') }}
            </p>
            <p class="auth-warning">
                {{ __('DLSite List remains on this page until the variable is removed and the application is restarted.') }}
            </p>
        </div>
    @elseif ($accountCount !== 1)
        <div class="auth-help">
            <div class="auth-error">
                {{ __('Password recovery requires exactly one administrator account.') }}
            </div>
            <p>{{ __('Run php artisan admin:reset to clear unsupported user rows, then remove the recovery variable and restart.') }}
            </p>
        </div>
    @else
        <form method="POST" action="{{ route('admin.recovery.store') }}" class="auth-form">
            @csrf

            <div class="auth-warning">
                {{ __('Anyone who can reach this page can set the administrator password while recovery mode is active.') }}
            </div>

            <label class="auth-field">
                <span>{{ __('New password') }}</span>
                <input type="password" name="password" required autofocus autocomplete="new-password">
            </label>
            @error('password')
                <div class="auth-error">{{ $message }}</div>
            @enderror

            <label class="auth-field">
                <span>{{ __('Confirm new password') }}</span>
                <input type="password" name="password_confirmation" required autocomplete="new-password">
            </label>

            <p class="auth-hint">{{ __('Use at least 8 characters.') }}</p>

            <button type="submit" class="auth-button">{{ __('Reset administrator password') }}</button>


            <a class="auth-help-link" href="{{ route('password.help') }}">{{ __('View password reset help') }}</a>
        </form>
    @endif
@endsection
