@extends('layouts.auth', [
    'title' => __('Password Reset Help'),
    'subtitle' => __('DLSite List does not send password-reset email. Use the server console or trusted recovery mode.'),
])

@section('content')
    <div class="auth-help">
        <section>
            <h2>{{ __('Recommended: console command') }}</h2>
            <p>{{ __('From the project directory, run:') }}</p>
            <code>php artisan admin:reset-password</code>
            <p>{{ __('Enter and confirm the new password at the masked prompts. Existing sessions will be invalidated.') }}
            </p>
        </section>

        <section>
            <h2>{{ __('Reset the administrator account') }}</h2>
            <p>{{ __('To remove administator and reset account setup, run:') }}</p>
            <code>php artisan admin:reset</code>
        </section>

        <section>
            <h2>{{ __('Trusted environment recovery') }}</h2>
            <p>
                {{ __('If console password prompts are unavailable, set ADMIN_PASSWORD_RESET=true, restart the application, and open any application page.') }}
            </p>
            <p class="auth-warning">
                {{ __('This temporarily exposes an unauthenticated password-reset form. Use it only on a trusted local network.') }}
            </p>
            <p>
                {{ __('After resetting, remove ADMIN_PASSWORD_RESET, restart the PHP/web process, or recreate the Docker app container. The application stays blocked until the variable is removed.') }}
            </p>
        </section>

        <a class="auth-button auth-button--link" href="{{ route('login') }}">{{ __('Back to login') }}</a>
    </div>
@endsection
