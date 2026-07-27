<!DOCTYPE html>

<html lang="{{ app()->getLocale() }}">

<head>
    <title>{{ $title }}</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}">
</head>

<body class="auth-page auth-theme-{{ $authTheme }}">
    <main class="auth-shell">
        <section class="auth-card">
            <header class="auth-header">
                <h1>{{ $title }}</h1>
                @isset($subtitle)
                    <p>{{ $subtitle }}</p>
                @endisset
            </header>

            @yield('content')
        </section>
    </main>

    <footer class="auth-footer">
        DLSite List
    </footer>
</body>

</html>
