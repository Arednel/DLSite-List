<!DOCTYPE html>

<html lang="{{ app()->getLocale() }}">

<head>
    <title>{{ __('Work saved') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet"
        href="{{ asset('css/work-form-completed.css') }}?v={{ filemtime(public_path('css/work-form-completed.css')) }}">
</head>

<body class="work-form-completed-page">
    <main class="work-form-completed-card" role="status" aria-labelledby="work-form-completed-title">
        <span class="work-form-completed-mark" aria-hidden="true"></span>
        <h1 id="work-form-completed-title">{{ __('Work change completed') }}</h1>
        <p class="work-form-completed-message">
            {{ __('Your change was saved successfully. You can continue if this window does not close automatically.') }}
        </p>
        <a class="work-form-completed-action" href="{{ $redirectUrl }}" target="_top">{{ __('Continue') }}</a>
    </main>

    <script>
        window.parent.postMessage({
            type: 'work-form-completed',
            redirectUrl: @js($redirectUrl),
        }, window.location.origin);
    </script>
</body>

</html>
