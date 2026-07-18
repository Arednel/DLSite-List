<!DOCTYPE html>

<html lang="{{ app()->getLocale() }}">

<head>
    <title>{{ __('Refetch Tags') }}</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet"
        href="{{ asset('css/content-page.css') }}?v={{ filemtime(public_path('css/content-page.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/options.css') }}?v={{ filemtime(public_path('css/options.css')) }}">
    <link rel="stylesheet"
        href="{{ asset('css/list-menu-float.css') }}?v={{ filemtime(public_path('css/list-menu-float.css')) }}">
    <x-title-tooltip-assets />

    @livewireStyles
</head>

<body class="options-page">
    <x-list-menu-float :quick-add-url="route('products.create', [], false)" :product-form-modal-enabled="$productFormModalEnabled" :product-form-modal-completion-action="$productFormModalCompletionAction" />

    <main class="options-shell">
        <div class="options-container">
            <header class="options-header">
                <h1>{{ __('Refetch Tags') }}</h1>
            </header>

            <livewire:options-refetch-progress :run="$run" />

            @if ($run->hasReviewResults())
                <section class="panel options-panel">
                    <h2>{{ __('Review') }}</h2>
                    <div class="summary-grid">
                        <div>{{ __('New JP') }} <strong>{{ $summary['added_japanese'] }}</strong></div>
                        <div>{{ __('New EN') }} <strong>{{ $summary['added_english'] }}</strong></div>
                        <div>{{ __('Stale JP') }} <strong>{{ $summary['stale_japanese'] }}</strong></div>
                        <div>{{ __('Stale EN') }} <strong>{{ $summary['stale_english'] }}</strong></div>
                        <div>{{ __('Custom->Fetched') }} <strong>{{ $summary['custom_to_fetched'] }}</strong></div>
                        <div>{{ __('Skipped') }} <strong>{{ $summary['skipped'] }}</strong></div>
                    </div>

                    @if ($errors->any())
                        <div class="notice notice--error">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    @if ($canApply)
                        <form method="POST" action="{{ route('options.refetch-tags.apply', $run) }}" class="stack">
                            @csrf
                            <div class="review-actions">
                                <label>
                                    {{ __('New JP') }}
                                    <select name="global_added_japanese_action">
                                        <option value="{{ $addAction }}" selected>{{ __('Add as fetched') }}
                                        </option>
                                        <option value="{{ $ignoreAction }}">{{ __('Ignore') }}</option>
                                    </select>
                                </label>
                                <label>
                                    {{ __('New EN') }}
                                    <select name="global_added_english_action">
                                        <option value="{{ $addAction }}" selected>{{ __('Add as fetched') }}
                                        </option>
                                        <option value="{{ $ignoreAction }}">{{ __('Ignore') }}</option>
                                    </select>
                                </label>
                                <label>
                                    {{ __('Stale JP') }}
                                    <select name="global_japanese_action">
                                        <option value="{{ $moveAction }}" selected>{{ __('Move to custom tags') }}
                                        </option>
                                        <option value="{{ $removeAction }}">{{ __('Remove') }}</option>
                                    </select>
                                </label>
                                <label>
                                    {{ __('Stale EN') }}
                                    <select name="global_english_action">
                                        <option value="{{ $moveAction }}" selected>{{ __('Move to custom tags') }}
                                        </option>
                                        <option value="{{ $removeAction }}">{{ __('Remove') }}</option>
                                    </select>
                                </label>
                                <label>
                                    {{ __('Custom->Fetched') }}
                                    <select name="global_custom_to_fetched_action">
                                        <option value="{{ $promoteCustomAction }}" selected>
                                            {{ __('Promote to fetched') }}</option>
                                        <option value="{{ $keepCustomAction }}">{{ __('Keep custom') }}</option>
                                    </select>
                                </label>
                                <button type="submit" class="tag tag--gradient tag--lg is-clickable">
                                    {{ __('Apply Changes') }}
                                </button>
                            </div>

                            <x-options.refetch-results :run="$run" :move-action="$moveAction" :remove-action="$removeAction"
                                :add-action="$addAction" :ignore-action="$ignoreAction" :promote-custom-action="$promoteCustomAction" :keep-custom-action="$keepCustomAction"
                                :show-controls="true" :tag-rows="$tagRows" />
                        </form>
                    @else
                        <div class="notice">
                            @if ($run->isApplied())
                                {{ __('This refetch run was applied.') }}
                            @else
                                {{ __('A newer refetch run exists. This run is read-only.') }}
                            @endif
                        </div>
                        <x-options.refetch-results :run="$run" :move-action="$moveAction" :remove-action="$removeAction"
                            :add-action="$addAction" :ignore-action="$ignoreAction" :promote-custom-action="$promoteCustomAction" :keep-custom-action="$keepCustomAction"
                            :show-controls="false" :tag-rows="$tagRows" />
                    @endif
                </section>
            @endif

        </div>

        <div class="option-actions option-actions--footer">
            <a class="tag tag--soft tag--md is-clickable"
                href="{{ route('options.index') }}">{{ __('Back to Options') }}</a>
        </div>
    </main>

    @livewireScripts
</body>

</html>
