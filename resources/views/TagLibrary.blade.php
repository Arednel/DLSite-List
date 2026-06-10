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

                    <section class="tag-library-panel" aria-labelledby="tag-library-heading">
                        <h1 id="tag-library-heading" class="tag-library-section-title">Tags</h1>

                        <div class="tag-library-tags">
                            @forelse ($genres as $genre)
                                <a class="tag-library-tag"
                                    href="{{ route('index', ['age_category' => '', 'progress' => '', 'genre' => $genre->id]) }}">
                                    <span class="tag-library-tag-title">{{ $genre->title }}</span>
                                    <span class="tag-library-tag-count">{{ $genre->products_count }}</span>
                                </a>
                            @empty
                                <p class="tag-library-empty">No English or custom tags yet.</p>
                            @endforelse
                        </div>
                    </section>
                </div>
            </div>
        </main>
    </div>
</body>

</html>
