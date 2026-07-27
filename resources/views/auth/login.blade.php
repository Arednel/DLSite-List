@extends('layouts.auth', [
    'title' => __('Sign in to continue'),
])

@section('content')
    <form method="POST" action="{{ route('login.authenticate') }}" class="auth-form">
        @csrf

        <label class="auth-field">
            <span>{{ __('Username') }}</span>
            <input type="text" name="username" value="{{ old('username') }}" required autofocus autocomplete="username"
                maxlength="50">
        </label>
        @error('username')
            <div class="auth-error">{{ $message }}</div>
        @enderror

        <label class="auth-field">
            <span>{{ __('Password') }}</span>
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        @error('password')
            <div class="auth-error">{{ $message }}</div>
        @enderror

        <label class="auth-remember">
            <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
            <span>{{ __('Remember Me') }}</span>
        </label>

        <button type="submit" class="auth-button">{{ __('Sign in') }}</button>

        <a class="auth-help-link" href="{{ route('password.help') }}">
            {{ __('Forgot your password?') }}
        </a>
    </form>
@endsection
