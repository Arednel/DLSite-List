@extends('layouts.auth', [
    'title' => __('Create Administrator Account'),
    'subtitle' => __('Authentication is enabled. Create the single administrator account to continue.'),
])

@section('content')
    <form method="POST" action="{{ route('admin.setup.store') }}" class="auth-form">
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
            <input type="password" name="password" required autocomplete="new-password">
        </label>
        @error('password')
            <div class="auth-error">{{ $message }}</div>
        @enderror

        <label class="auth-field">
            <span>{{ __('Confirm password') }}</span>
            <input type="password" name="password_confirmation" required autocomplete="new-password">
        </label>

        <p class="auth-hint">{{ __('Use at least 8 characters.') }}</p>

        <button type="submit" class="auth-button">{{ __('Create administrator') }}</button>
    </form>
@endsection
