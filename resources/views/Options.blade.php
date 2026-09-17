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
    <link rel="stylesheet"
        href="{{ asset('css/title-tooltips.css') }}?v={{ filemtime(public_path('css/title-tooltips.css')) }}">
    <link rel="stylesheet"
        href="{{ asset('css/transfers.css') }}?v={{ filemtime(public_path('css/transfers.css')) }}">
    <script src="{{ asset('scripts/title-tooltips.js') }}?v={{ filemtime(public_path('scripts/title-tooltips.js')) }}"
        defer></script>
    <script src="{{ asset('scripts/transfer-upload.js') }}?v={{ filemtime(public_path('scripts/transfer-upload.js')) }}"
        defer></script>

    @livewireStyles
</head>

<body class="options-page">
    <x-list-menu-float :quick-add-url="route('products.create', [], false)" :product-form-modal-enabled="$productFormModalEnabled" :product-form-modal-completion-action="$productFormModalCompletionAction" />

    <main class="options-shell">
        <div class="options-container">
            <header class="options-header">
                <h1>{{ __('Options') }}</h1>
            </header>

            <nav class="options-navigation" aria-label="{{ __('Options sections') }}" data-options-navigation
                data-active-options-category="{{ $activeTab }}" x-data="{ open: false }"
                x-on:click.outside="open = false"
                x-on:keydown.escape.window="if (open) { open = false; $refs.toggle.focus() }">
                <button type="button" class="options-navigation-toggle" data-options-navigation-toggle
                    aria-label="{{ __('Options sections') }}: {{ __($optionSections[$activeTab]) }}"
                    aria-controls="options-navigation-list" aria-expanded="false" x-bind:aria-expanded="open.toString()"
                    x-on:click="open = ! open" x-ref="toggle">
                    <span class="options-navigation-toggle-text">{{ __($optionSections[$activeTab]) }}</span>
                    <span class="options-navigation-toggle-icon" aria-hidden="true"></span>
                </button>

                <ul id="options-navigation-list" class="options-navigation-list" data-options-navigation-list>
                    @foreach ($optionSections as $tab => $label)
                        <li class="options-navigation-item">
                            <a class="options-navigation-link {{ $activeTab === $tab ? 'is-active' : '' }}"
                                href="{{ route('options.index', ['tab' => $tab], false) }}"
                                data-options-category-key="{{ $tab }}"
                                @if ($activeTab === $tab) aria-current="page" @endif>
                                {{ __($label) }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            @if ($activeTab === 'general')
                <section id="general-tab-panel" class="panel options-panel">
                    <h2>
                        <i class="fa-solid fa-globe fa-fw options-section-icon" aria-hidden="true"></i>
                        {{ __('UI Language') }}
                    </h2>
                    <p class="option-description">
                        {{ __('Choose which language the application interface uses.') }}
                    </p>

                    <livewire:ui-language-settings />

                    <h2>
                        <i class="fa-solid fa-list-ol fa-fw options-section-icon" aria-hidden="true"></i>
                        {{ __('Index Pagination') }}
                    </h2>
                    <p class="option-description">
                        {{ __('Choose how many works are shown on each Index page.') }}
                    </p>

                    <livewire:index-pagination-settings />

                    <h2>
                        <i class="fa-solid fa-magnifying-glass fa-fw options-section-icon" aria-hidden="true"></i>
                        {{ __('Index Search') }}
                    </h2>
                    <p class="option-description">
                        {{ __('Choose whether general Index search can match hidden description text.') }}
                    </p>

                    <livewire:index-search-settings />

                    <h2>
                        <i class="fa-solid fa-list-check fa-fw options-section-icon" aria-hidden="true"></i>
                        {{ __('Optional Statuses') }}
                    </h2>
                    <p class="option-description">
                        {{ __('Choose whether On Hold and Dropped are shown in forms, Index status controls, and Advanced Filter.') }}
                    </p>

                    <livewire:optional-product-statuses-settings />

                    <h2>
                        <i class="fa-solid fa-images fa-fw options-section-icon" aria-hidden="true"></i>
                        {{ __('Image Viewer') }}
                    </h2>
                    <p class="option-description">
                        {{ __('Choose whether clicking a cover opens saved images here or visits DLSite.') }}
                    </p>

                    <livewire:index-image-viewer-settings />

                    <h2>
                        <i class="fa-solid fa-arrows-left-right fa-fw options-section-icon" aria-hidden="true"></i>
                        {{ __('Index Table Width') }}
                    </h2>
                    <p class="option-description">
                        {{ __('Choose how wide the Index table can be before horizontal scrolling is used.') }}
                    </p>

                    <livewire:index-table-width-settings />

                    <h2>
                        <i class="fa-solid fa-up-right-and-down-left-from-center fa-fw options-section-icon"
                            aria-hidden="true"></i>
                        {{ __('Overflow') }}
                    </h2>
                    <p class="option-description">
                        {{ __('Set height limits for Notes and Tags on the Index. Content that exceeds the limit can be expanded with ‘Show all’.') }}
                    </p>

                    <livewire:index-content-overflow-settings />

                    <h2>
                        <i class="fa-solid fa-layer-group fa-fw options-section-icon" aria-hidden="true"></i>
                        {{ __('Series Metadata') }}
                    </h2>
                    <p class="option-description">
                        {{ __('Choose whether DLSite Create fills Series from the fetched metadata when Series input field is empty.') }}
                    </p>

                    <livewire:auto-series-settings />

                    <h2>
                        <i class="fa-solid fa-link fa-fw options-section-icon" aria-hidden="true"></i>
                        {{ __('DLSite Links') }}
                    </h2>
                    <p class="option-description">
                        {{ __('Choose whether Index work links use the DLSite section appropriate for the saved age category.') }}
                    </p>

                    <livewire:dlsite-link-settings />

                    <h2>
                        <i class="fa-solid fa-palette fa-fw options-section-icon" aria-hidden="true"></i>
                        {{ __('Form Page Theme') }}
                    </h2>
                    <p class="option-description">
                        {{ __('Choose the visual theme for add and edit pages.') }}
                    </p>

                    <livewire:product-form-theme-settings />

                    <h2>
                        <i class="fa-solid fa-window-restore fa-fw options-section-icon" aria-hidden="true"></i>
                        {{ __('Add/Edit Modals') }}
                    </h2>
                    <p class="option-description">
                        {{ __('Choose whether Quick Add and Edit Details open over the current page and what happens after a successful change.') }}
                    </p>

                    <livewire:product-form-modal-settings />

                    <h2>
                        <i class="fa-solid fa-keyboard fa-fw options-section-icon" aria-hidden="true"></i>
                        {{ __('Autocomplete') }}
                    </h2>
                    <p class="option-description">
                        {{ __('Choose how tag and series suggestions are ordered in autocomplete dropdowns.') }}
                    </p>

                    <livewire:autocomplete-settings />

                    <h2>
                        <i class="fa-solid fa-tags fa-fw options-section-icon" aria-hidden="true"></i>
                        {{ __('Tag Library') }}
                    </h2>
                    <p class="option-description">
                        {{ __('Configure Tag Library startup behavior and whether saved tag-group order affects Index tag chips.') }}
                    </p>

                    <livewire:tag-library-display-settings />

                    <livewire:options-reset-defaults :active-tab="$activeTab" />
                </section>
            @endif

            @if ($activeTab === 'field-layouts')
                <section id="field-layouts-tab-panel" class="panel options-panel">
                    <header class="options-panel-intro">
                        <h2>
                            {{ __('Field Layouts') }}
                        </h2>
                        <p class="option-description">
                            {{ __('Choose which fields are visible and editable, and set their order.') }}
                        </p>
                    </header>

                    <livewire:product-field-layout-settings />

                    <livewire:options-reset-defaults :active-tab="$activeTab" />
                </section>
            @endif

            @if ($activeTab === 'authentication')
                <section id="authentication-tab-panel" class="panel options-panel">
                    <h2>
                        <i class="fa-solid fa-shield-halved fa-fw options-section-icon" aria-hidden="true"></i>
                        {{ __('Administrator Authentication') }}
                    </h2>
                    <p class="option-description">
                        {{ __('Protect the application with username and password.') }}
                    </p>

                    <livewire:authentication-settings />
                </section>
            @endif

            @if ($activeTab === 'transfers')
                <section class="panel options-panel">
                    <h2>
                        <i class="fa-solid fa-right-left fa-fw options-section-icon" aria-hidden="true"></i>
                        {{ __('Import / Export') }}
                    </h2>
                    <p class="option-description">
                        {{ __('Export your library to ZIP files, or import an archive and review changes before applying them.') }}
                    </p>

                    <livewire:options-transfers />
                </section>
            @endif

            @if ($activeTab === 'refetch')
                <section id="refetch-tab-panel" class="panel options-panel">
                    <h2>
                        <i class="fa-solid fa-arrows-rotate fa-fw options-section-icon" aria-hidden="true"></i>
                        {{ __('Refetch DLSite Data') }}
                    </h2>
                    <p class="option-description">
                        {{ __('Fetch up-to-date DLSite data for all or only selected works.') }}
                        <br>
                        {{ __('Review each metadata category before applying or ignoring changes.') }}
                    </p>

                    @if ($errors->any())
                        <div class="notice notice--error">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <livewire:options-refetch-actions />

                    <div class="refetch-scope-grid">
                        <section class="refetch-scope-card" aria-labelledby="refetch-all-heading">
                            <header>
                                <h3 id="refetch-all-heading">
                                    <i class="fa-solid fa-layer-group fa-fw" aria-hidden="true"></i>
                                    {{ __('Refetch All Works') }}
                                </h3>
                                <p>{{ __('Fetch every work in the library in one refetch run.') }}</p>
                            </header>

                            <form method="POST" action="{{ route('options.refetch.start') }}" class="stack">
                                @csrf
                                <input type="hidden" name="scope" value="all">
                                <input type="hidden" name="tab" value="refetch">
                                <x-options.switch name="check_images" value="1" :help="__(
                                    'Downloads the current cover and sample images for every selected work and compares Cover and Sample Images separately. This makes the refetch slower. Downloads are staged for review and do not replace saved images unless that category is applied; failed image categories remain unavailable for overwrite.',
                                )">
                                    {{ __('Refetch Images') }}
                                </x-options.switch>
                                <button type="submit" class="tag tag--gradient tag--lg is-clickable">
                                    {{ __('Refetch all works') }}
                                </button>
                            </form>
                        </section>

                        <section class="refetch-scope-card" aria-labelledby="refetch-selected-heading">
                            <header>
                                <h3 id="refetch-selected-heading">
                                    <i class="fa-solid fa-list-check fa-fw" aria-hidden="true"></i>
                                    {{ __('Refetch Selected Works') }}
                                </h3>
                                <p>{{ __('Search and choose works to include in this refetch run.') }}</p>
                            </header>

                            <livewire:options-work-search />
                        </section>
                    </div>
                </section>
            @endif
        </div>
    </main>

    @livewireScripts
</body>

</html>
