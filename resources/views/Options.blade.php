<!doctype html>
<html lang="en">

<head>
    <title>Options</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet"
        href="{{ asset('css/content-page.css') }}?v={{ filemtime(public_path('css/content-page.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/options.css') }}?v={{ filemtime(public_path('css/options.css')) }}">
    <link rel="stylesheet"
        href="{{ asset('css/list-menu-float.css') }}?v={{ filemtime(public_path('css/list-menu-float.css')) }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">

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
                $activeTab = old('tab', request('tab', 'general'));
                $activeTab = in_array($activeTab, ['general', 'field-layouts', 'refetch'], true)
                    ? $activeTab
                    : 'general';
            @endphp

            <nav class="options-tabs" aria-label="Options sections" role="tablist">
                <a class="options-tab {{ $activeTab === 'general' ? 'is-active' : '' }}"
                    href="{{ route('options.index', ['tab' => 'general'], false) }}" role="tab"
                    aria-controls="general-tab-panel" aria-selected="{{ $activeTab === 'general' ? 'true' : 'false' }}">
                    General
                </a>
                <a class="options-tab {{ $activeTab === 'field-layouts' ? 'is-active' : '' }}"
                    href="{{ route('options.index', ['tab' => 'field-layouts'], false) }}" role="tab"
                    aria-controls="field-layouts-tab-panel"
                    aria-selected="{{ $activeTab === 'field-layouts' ? 'true' : 'false' }}">
                    Field Layouts
                </a>
                <a class="options-tab {{ $activeTab === 'refetch' ? 'is-active' : '' }}"
                    href="{{ route('options.index', ['tab' => 'refetch'], false) }}" role="tab"
                    aria-controls="refetch-tab-panel"
                    aria-selected="{{ $activeTab === 'refetch' ? 'true' : 'false' }}">
                    Refetch
                </a>
            </nav>

            @if ($activeTab === 'general')
                <section id="general-tab-panel" class="panel" role="tabpanel">
                    <h2>Index Pagination</h2>
                    <p class="option-description">
                        Choose how many works are shown on each Index page.
                    </p>

                    <livewire:index-pagination-settings />

                    <h2>Index Table Width</h2>
                    <p class="option-description">
                        Choose how wide the Index table can be before horizontal scrolling is used.
                    </p>

                    <livewire:index-table-width-settings />

                    <h2>Series Metadata</h2>
                    <p class="option-description">
                        Choose whether DLSite Create fills Series from the fetched title name when Series is empty.
                    </p>

                    <livewire:auto-series-settings />

                    <h2>Autocomplete</h2>
                    <p class="option-description">
                        Choose how tag and series suggestions are ordered in autocomplete dropdowns.
                    </p>

                    <livewire:autocomplete-settings />

                    <livewire:options-reset-defaults />
                </section>
            @endif

            @if ($activeTab === 'field-layouts')
                <section id="field-layouts-tab-panel" class="panel" role="tabpanel">
                    <h2>Field Layouts</h2>
                    <p class="option-description">
                        Choose which product fields are visible, editable, and how configurable columns are ordered.
                    </p>

                    <livewire:product-field-layout-settings />

                    <livewire:options-reset-defaults />
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
