<!doctype html>
<html lang="en">

<head>
    <title>Options</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet"
        href="{{ asset('css/tag-library.css') }}?v={{ filemtime(public_path('css/tag-library.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/options.css') }}?v={{ filemtime(public_path('css/options.css')) }}">
    <link rel="stylesheet"
        href="{{ asset('css/list-menu-float.css') }}?v={{ filemtime(public_path('css/list-menu-float.css')) }}">

    @livewireStyles
</head>

<body>
    <x-list-menu-float :quick-add-url="route('products.create', [], false)" />

    <main class="page">
        <div class="container">
            <header class="hero">
                <h1>Options</h1>
            </header>

            @php
                $activeTab = old('tab', request('tab', 'options'));
                $activeTab = in_array($activeTab, ['options', 'refetch'], true) ? $activeTab : 'options';
            @endphp

            <nav class="options-tabs" aria-label="Options sections" role="tablist">
                <a class="options-tab {{ $activeTab === 'options' ? 'is-active' : '' }}"
                    href="{{ route('options.index', ['tab' => 'options'], false) }}" role="tab"
                    aria-controls="options-tab-panel" aria-selected="{{ $activeTab === 'options' ? 'true' : 'false' }}">
                    Options
                </a>
                <a class="options-tab {{ $activeTab === 'refetch' ? 'is-active' : '' }}"
                    href="{{ route('options.index', ['tab' => 'refetch'], false) }}" role="tab"
                    aria-controls="refetch-tab-panel"
                    aria-selected="{{ $activeTab === 'refetch' ? 'true' : 'false' }}">
                    Refetch
                </a>
            </nav>

            @if ($activeTab === 'options')
                <section id="options-tab-panel" class="panel" role="tabpanel">
                    <h2>Index Pagination</h2>
                    <p class="option-description">
                        Choose how many works are shown on each Index page.
                    </p>

                    <livewire:index-pagination-settings />
                </section>
            @endif

            @if ($activeTab === 'refetch')
                <section id="refetch-tab-panel" class="panel" role="tabpanel">
                    <h2>Refetch Tags</h2>
                    <p class="option-description">
                        Fetch the latest DLsite genre tags for all works or for only selected works.
                        <br>
                        After that you can review new and stale tags before applying the changes.
                    </p>

                    @if ($errors->any())
                        <div class="notice notice--error">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <div class="option-actions">
                        <form method="POST" action="{{ route('options.refetch-tags.start') }}">
                            @csrf
                            <input type="hidden" name="scope" value="all">
                            <input type="hidden" name="tab" value="refetch">
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
            @endif
        </div>
    </main>

    @livewireScripts
</body>

</html>
