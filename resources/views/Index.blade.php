<!DOCTYPE html>

<head>
    <title>DLSite List</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="{{ asset('css/index.css') }}?v={{ filemtime(public_path('css/index.css')) }}">
    <link rel="stylesheet"
        href="{{ asset('css/list-menu-float.css') }}?v={{ filemtime(public_path('css/list-menu-float.css')) }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

    @livewireStyles
</head>

<body class="ownlist anime" data-work="anime">
    <livewire:product-index />

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
    @livewireScripts
</body>

</html>
