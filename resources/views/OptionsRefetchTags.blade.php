<!DOCTYPE html>

<html lang="en">

<head>
    <title>Refetch Tags</title>

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
    <x-list-menu-float :quick-add-url="route('products.create', [], false)" />

    <main class="options-shell">
        <div class="options-container">
            <header class="options-header">
                <h1>Refetch Tags</h1>
            </header>

            <livewire:options-refetch-progress :run="$run" />

            @if ($run->hasReviewResults())
                <section class="panel options-panel">
                    <h2>Review</h2>
                    <div class="summary-grid">
                        <div>New JP <strong>{{ $summary['added_japanese'] }}</strong></div>
                        <div>New EN <strong>{{ $summary['added_english'] }}</strong></div>
                        <div>Stale JP <strong>{{ $summary['stale_japanese'] }}</strong></div>
                        <div>Stale EN <strong>{{ $summary['stale_english'] }}</strong></div>
                        <div>Custom -> Fetched <strong>{{ $summary['custom_to_fetched'] }}</strong></div>
                        <div>Skipped <strong>{{ $summary['skipped'] }}</strong></div>
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
                                    New JP
                                    <select name="global_added_japanese_action">
                                        <option value="{{ $addAction }}" selected>Add as fetched</option>
                                        <option value="{{ $ignoreAction }}">Ignore</option>
                                    </select>
                                </label>
                                <label>
                                    New EN
                                    <select name="global_added_english_action">
                                        <option value="{{ $addAction }}" selected>Add as fetched</option>
                                        <option value="{{ $ignoreAction }}">Ignore</option>
                                    </select>
                                </label>
                                <label>
                                    Stale JP
                                    <select name="global_japanese_action">
                                        <option value="{{ $moveAction }}" selected>Move to custom tags</option>
                                        <option value="{{ $removeAction }}">Remove</option>
                                    </select>
                                </label>
                                <label>
                                    Stale EN
                                    <select name="global_english_action">
                                        <option value="{{ $moveAction }}" selected>Move to custom tags</option>
                                        <option value="{{ $removeAction }}">Remove</option>
                                    </select>
                                </label>
                                <label>
                                    Custom -> Fetched
                                    <select name="global_custom_to_fetched_action">
                                        <option value="{{ $promoteCustomAction }}" selected>Promote to fetched</option>
                                        <option value="{{ $keepCustomAction }}">Keep custom</option>
                                    </select>
                                </label>
                                <button type="submit" class="tag tag--gradient tag--lg is-clickable">
                                    Apply Changes
                                </button>
                            </div>

                            <x-options.refetch-results :run="$run" :move-action="$moveAction" :remove-action="$removeAction"
                                :add-action="$addAction" :ignore-action="$ignoreAction" :promote-custom-action="$promoteCustomAction" :keep-custom-action="$keepCustomAction"
                                :show-controls="true" :tag-rows="$tagRows" />
                        </form>
                    @else
                        <div class="notice">
                            @if ($run->isApplied())
                                This refetch run was applied.
                            @else
                                A newer refetch run exists. This run is read-only.
                            @endif
                        </div>
                        <x-options.refetch-results :run="$run" :move-action="$moveAction" :remove-action="$removeAction"
                            :add-action="$addAction" :ignore-action="$ignoreAction" :promote-custom-action="$promoteCustomAction" :keep-custom-action="$keepCustomAction"
                            :show-controls="false" :tag-rows="$tagRows" />
                    @endif
                </section>
            @endif

            <div class="option-actions option-actions--footer">
                <a class="tag tag--soft tag--md is-clickable" href="{{ route('options.index') }}">Back to Options</a>
            </div>
        </div>
    </main>

    @livewireScripts
</body>

</html>
