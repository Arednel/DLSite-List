<!DOCTYPE html>

<html lang="{{ app()->getLocale() }}">

<head>
    <title>DLSite List</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="{{ asset('css/index.css') }}?v={{ filemtime(public_path('css/index.css')) }}">
    <link rel="stylesheet"
        href="{{ asset('css/list-menu-float.css') }}?v={{ filemtime(public_path('css/list-menu-float.css')) }}">
    <link rel="stylesheet"
        href="{{ asset('css/autocomplete.css') }}?v={{ filemtime(public_path('css/autocomplete.css')) }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">

    @livewireStyles
</head>

<body class="product-index-page">
    <main class="product-index-main">
        @session('dlsite_image_warning')
            <div class="image-warning-popup" role="alert" onclick="this.remove()">
                <span>{{ $value }}</span>
                <button type="button" class="image-warning-popup__close" aria-label="{{ __('Close') }}">&times;</button>
            </div>
        @endsession
        <livewire:product-index />
    </main>

    <footer>
        <div id="footer-block">
            <div id="copyright">
                DLSite List
            </div>
        </div>
    </footer>

    <script
        src="{{ asset('scripts/index-advanced-filters.js') }}?v={{ filemtime(public_path('scripts/index-advanced-filters.js')) }}"
        defer></script>
    <script
        src="{{ asset('scripts/autocomplete-text.js') }}?v={{ filemtime(public_path('scripts/autocomplete-text.js')) }}"
        defer></script>
    @livewireScripts
</body>

</html>
