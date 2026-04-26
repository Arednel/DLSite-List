<!doctype html>
<html lang="en">

<head>
    <title>Options</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="{{ asset('css/tag-library.css') }}?v={{ filemtime(public_path('css/tag-library.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/options.css') }}?v={{ filemtime(public_path('css/options.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/list-menu-float.css') }}?v={{ filemtime(public_path('css/list-menu-float.css')) }}">

    @livewireStyles
</head>

<body>
    <x-list-menu-float :quick-add-url="route('products.create', ['return_route' => 'options.index'], false)" />

    <main class="page">
        <div class="container">
            <header class="hero">
                <h1>Options</h1>
            </header>

            <section class="panel">
                <h2>Refetch Tags</h2>

                @if ($errors->any())
                    <div class="notice notice--error">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div class="option-actions">
                    <form method="POST" action="{{ route('options.refetch-tags.start') }}">
                        @csrf
                        <input type="hidden" name="scope" value="all">
                        <button type="submit" class="tag tag--gradient tag--lg is-clickable">
                            Refetch all works
                        </button>
                    </form>

                    @if ($latestRefetchRun)
                        <a class="tag tag--soft tag--lg is-clickable"
                            href="{{ route('options.refetch-tags.show', $latestRefetchRun) }}">
                            Go to latest refetch
                        </a>
                    @endif
                </div>

                <livewire:options-work-search />
            </section>
        </div>
    </main>

    @livewireScripts
</body>

</html>
