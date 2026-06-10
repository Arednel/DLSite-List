<!doctype html>
<html lang="en">

<head>
    <title>Tag Library</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet"
        href="{{ asset('css/content-page.css') }}?v={{ filemtime(public_path('css/content-page.css')) }}">
    <link rel="stylesheet"
        href="{{ asset('css/tag-library.css') }}?v={{ filemtime(public_path('css/tag-library.css')) }}">
    <link rel="stylesheet"
        href="{{ asset('css/list-menu-float.css') }}?v={{ filemtime(public_path('css/list-menu-float.css')) }}">

    @livewireStyles
</head>

<body class="tag-library-page">
    <div class="tag-library-shell">
        <x-list-menu-float :quick-add-url="route('products.create', [], false)" />

        <main id="list-container" class="list-container tag-library-container">
            <div class="list-block tag-library-block">
                <div class="list-unit tag-library-unit">
                    <div class="list-status-title">
                        <span class="progress-heading">Tag Library</span>
                    </div>

                    <livewire:tag-library-manager />
                </div>
            </div>
        </main>
    </div>

    @livewireScripts
</body>

</html>
