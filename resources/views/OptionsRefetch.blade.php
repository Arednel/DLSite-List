<!DOCTYPE html>

<html lang="{{ app()->getLocale() }}">

<head>
    <title>{{ __('Refetch Works') }}</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet"
        href="{{ asset('css/content-page.css') }}?v={{ filemtime(public_path('css/content-page.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/options.css') }}?v={{ filemtime(public_path('css/options.css')) }}">
    <link rel="stylesheet"
        href="{{ asset('css/list-menu-float.css') }}?v={{ filemtime(public_path('css/list-menu-float.css')) }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <link rel="stylesheet"
        href="{{ asset('css/title-tooltips.css') }}?v={{ filemtime(public_path('css/title-tooltips.css')) }}">
    <script src="{{ asset('scripts/title-tooltips.js') }}?v={{ filemtime(public_path('scripts/title-tooltips.js')) }}"
        defer></script>

    @livewireStyles
</head>

<body class="options-page">
    <x-list-menu-float :quick-add-url="route('products.create', [], false)" :product-form-modal-enabled="$productFormModalEnabled" :product-form-modal-completion-action="$productFormModalCompletionAction" />

    <main class="options-shell">
        <div class="options-container">
            <header class="options-header">
                <h1>{{ __('Refetch Works') }} #{{ $run->getKey() }}</h1>
            </header>

            <livewire:options-refetch-progress :run="$run" />

            @if ($run->hasReviewResults())
                <livewire:options-refetch-review :run="$run" />
            @endif
        </div>

        <div class="option-actions option-actions--footer">
            <a class="tag tag--soft tag--md is-clickable"
                href="{{ route('options.index', ['tab' => 'refetch']) }}">{{ __('Back to Options') }}</a>
        </div>
    </main>

    @livewireScripts
</body>

</html>
