<!DOCTYPE html>

<html lang="{{ app()->getLocale() }}">

<head>
    <title>{{ __('Tag Library') }}</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet"
        href="{{ asset('css/content-page.css') }}?v={{ filemtime(public_path('css/content-page.css')) }}">
    <link rel="stylesheet"
        href="{{ asset('css/autocomplete.css') }}?v={{ filemtime(public_path('css/autocomplete.css')) }}">
    <link rel="stylesheet"
        href="{{ asset('css/tag-library.css') }}?v={{ filemtime(public_path('css/tag-library.css')) }}">
    <link rel="stylesheet"
        href="{{ asset('css/list-menu-float.css') }}?v={{ filemtime(public_path('css/list-menu-float.css')) }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <x-title-tooltip-assets />

    @livewireStyles
</head>

<body class="tag-library-page">
    <div class="tag-library-shell">
        <x-list-menu-float :quick-add-url="route('products.create', [], false)" :product-form-modal-enabled="$productFormModalEnabled" :product-form-modal-completion-action="$productFormModalCompletionAction" />

        <main id="list-container" class="list-container tag-library-container">
            <div class="list-block tag-library-block">
                <div class="list-unit tag-library-unit">
                    <div class="list-status-title">
                        <span class="progress-heading">{{ __('Tag Library') }}</span>
                    </div>

                    <livewire:tag-library-manager />
                </div>
            </div>
        </main>
    </div>

    @livewireScripts
    <script
        src="{{ asset('scripts/autocomplete-text.js') }}?v={{ filemtime(public_path('scripts/autocomplete-text.js')) }}">
    </script>
</body>

</html>
