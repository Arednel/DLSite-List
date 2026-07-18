<!DOCTYPE html>

<html lang="{{ app()->getLocale() }}">

<head>
    <title>{{ __('Options') }}</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet"
        href="{{ asset('css/content-page.css') }}?v={{ filemtime(public_path('css/content-page.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/options.css') }}?v={{ filemtime(public_path('css/options.css')) }}">
    <link rel="stylesheet"
        href="{{ asset('css/list-menu-float.css') }}?v={{ filemtime(public_path('css/list-menu-float.css')) }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <x-title-tooltip-assets />

    @livewireStyles
</head>

<body class="options-page">
    <x-list-menu-float :quick-add-url="route('products.create', [], false)" :product-form-modal-enabled="$productFormModalEnabled" :product-form-modal-completion-action="$productFormModalCompletionAction" />

    <main class="options-shell">
        <div class="options-container">
            <header class="options-header">
                <h1>{{ __('Options') }}</h1>
            </header>

            @php
                $activeTab = old('tab', request('tab', 'general'));
                $activeTab = in_array($activeTab, ['general', 'field-layouts', 'refetch'], true)
                    ? $activeTab
                    : 'general';
            @endphp

            <nav class="options-tabs options-tab-list" aria-label="{{ __('Options sections') }}" role="tablist">
                <a class="options-tab {{ $activeTab === 'general' ? 'is-active' : '' }}"
                    href="{{ route('options.index', ['tab' => 'general'], false) }}" role="tab"
                    aria-controls="general-tab-panel"
                    aria-selected="{{ $activeTab === 'general' ? 'true' : 'false' }}">
                    {{ __('General') }}
                </a>
                <a class="options-tab {{ $activeTab === 'field-layouts' ? 'is-active' : '' }}"
                    href="{{ route('options.index', ['tab' => 'field-layouts'], false) }}" role="tab"
                    aria-controls="field-layouts-tab-panel"
                    aria-selected="{{ $activeTab === 'field-layouts' ? 'true' : 'false' }}">
                    {{ __('Field Layouts') }}
                </a>
                <a class="options-tab {{ $activeTab === 'refetch' ? 'is-active' : '' }}"
                    href="{{ route('options.index', ['tab' => 'refetch'], false) }}" role="tab"
                    aria-controls="refetch-tab-panel"
                    aria-selected="{{ $activeTab === 'refetch' ? 'true' : 'false' }}">
                    {{ __('Refetch') }}
                </a>
            </nav>

            @if ($activeTab === 'general')
                <section id="general-tab-panel" class="panel options-panel" role="tabpanel">
                    <h2>
                        <i class="fa-solid fa-globe options-section-icon" aria-hidden="true"></i>
                        {{ __('UI Language') }}
                    </h2>
                    <p class="option-description">
                        {{ __('Choose which language the application interface uses.') }}
                    </p>

                    <livewire:ui-language-settings />

                    <h2>{{ __('Index Pagination') }}</h2>
                    <p class="option-description">
                        {{ __('Choose how many works are shown on each Index page.') }}
                    </p>

                    <livewire:index-pagination-settings />

                    <h2>{{ __('Index Search') }}</h2>
                    <p class="option-description">
                        {{ __('Choose whether general Index search can match hidden description text.') }}
                    </p>

                    <livewire:index-search-settings />

                    <h2>{{ __('Index Table Width') }}</h2>
                    <p class="option-description">
                        {{ __('Choose how wide the Index table can be before horizontal scrolling is used.') }}
                    </p>

                    <livewire:index-table-width-settings />

                    <h2>{{ __('Series Metadata') }}</h2>
                    <p class="option-description">
                        {{ __('Choose whether DLSite Create fills Series from the fetched title name when Series is empty.') }}
                    </p>

                    <livewire:auto-series-settings />

                    <h2>{{ __('DLSite Links') }}</h2>
                    <p class="option-description">
                        {{ __('Choose whether Index work links use the DLSite section appropriate for the saved age category.') }}
                    </p>

                    <livewire:dlsite-link-settings />

                    <h2>{{ __('Form Page Theme') }}</h2>
                    <p class="option-description">
                        {{ __('Choose the visual theme for Add Work, Add Custom Work, and Edit Work pages.') }}
                    </p>

                    <livewire:product-form-theme-settings />

                    <h2>{{ __('Work Form Modals') }}</h2>
                    <p class="option-description">
                        {{ __('Choose whether Quick Add and Edit Work open over the current page and what happens after a successful change.') }}
                    </p>

                    <livewire:product-form-modal-settings />

                    <h2>{{ __('Autocomplete') }}</h2>
                    <p class="option-description">
                        {{ __('Choose how tag and series suggestions are ordered in autocomplete dropdowns.') }}
                    </p>

                    <livewire:autocomplete-settings />

                    <h2>{{ __('Tag Library') }}</h2>
                    <p class="option-description">
                        {{ __('Configure Tag Library startup behavior and whether saved tag-group order affects Index tag chips.') }}
                    </p>

                    <livewire:tag-library-display-settings />

                    <livewire:options-reset-defaults :active-tab="$activeTab" />
                </section>
            @endif

            @if ($activeTab === 'field-layouts')
                <section id="field-layouts-tab-panel" class="panel options-panel" role="tabpanel">
                    <h2>{{ __('Field Layouts') }}</h2>
                    <p class="option-description">
                        {{ __('Choose which product fields are visible, editable, and how configurable columns are ordered.') }}
                    </p>

                    <livewire:product-field-layout-settings />

                    <livewire:options-reset-defaults :active-tab="$activeTab" />
                </section>
            @endif

            @if ($activeTab === 'refetch')
                <section id="refetch-tab-panel" class="panel options-panel" role="tabpanel">
                    <h2>{{ __('Refetch Tags') }}</h2>
                    <p class="option-description">
                        {{ __('Fetch the latest DLsite genre tags for all works or for only selected works.') }}
                        <br>
                        {{ __('After that you can review new and stale tags before applying the changes.') }}
                    </p>

                    @if ($errors->any())
                        <div class="notice notice--error">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <div class="option-actions option-actions--primary">
                        <form method="POST" action="{{ route('options.refetch-tags.start') }}">
                            @csrf
                            <input type="hidden" name="scope" value="all">
                            <input type="hidden" name="tab" value="refetch">
                            <button type="submit" class="tag tag--gradient tag--lg is-clickable">
                                {{ __('Refetch all works') }}
                            </button>
                        </form>

                        @if ($latestRefetchRun)
                            <a class="tag tag--soft tag--lg is-clickable"
                                href="{{ route('options.refetch-tags.show', $latestRefetchRun) }}">
                                {{ __('Go to latest refetch') }}
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
