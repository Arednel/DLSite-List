<!doctype html>
<html lang="en">

<head>
    <title>Tag Library</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="{{ asset('css/tag-library.css') }}">
    <link rel="stylesheet" href="{{ asset('css/list-menu-float.css') }}">
</head>

<body>
    <x-list-menu-float :quick-add-url="route('products.create', ['return_route' => 'tags.index'], false)" />

    <main class="page">
        <div class="container">
            <header class="hero">
                <h1>Tag Library</h1>
            </header>

            <section class="panel">
                <h2>Tags</h2>
                <div class="stack">
                    <div>
                        <div class="tag-row">
                            @forelse ($genres as $genre)
                                <a class="tag tag--soft tag--md is-clickable"
                                    href="{{ route('index', ['age_category' => '', 'progress' => '', 'genre' => $genre->id]) }}">
                                    {{ $genre->title }}
                                </a>
                            @empty
                                <p class="empty-state">No English or custom tags yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </section>

            {{-- <section class="panel">
                <h2>Tag Groups</h2>
                <div class="stack">
                    <div>
                        <h3>Group 1</h3>
                        <div class="tag-row">
                            <span class="tag tag--soft tag--md is-clickable">tag 1</span>
                        </div>
                    </div>
                <p class="helper-text">Placeholder for future tag group management.</p>
            </section>

                    <div>
                        <h3>Group 2</h3>
                        <div class="tag-row">
                            <button class="tag tag--soft tag--md tag--removable is-clickable" type="button">
                                tag 2
                                <span class="tag__remove">x</span>
                            </button>
                            <button class="tag tag--soft tag--md tag--removable is-clickable" type="button">
                                tag 3
                                <span class="tag__remove">x</span>
                            </button>
                        </div>
                    </div>
                </div>
            </section> --}}

            {{-- <section class="panel">
            <section class="panel">
                <h2>Tag Input</h2>
                <div class="stack">
                    <div>
                        <label class="field-label" for="tag-input-a">Create new tag (Press Enter to add)</label>
                        <div class="tag-input">
                            <span class="tag tag--soft tag--sm tag--removable">
                                tag 1
                                <span class="tag__remove">x</span>
                            </span>
                            <span class="tag tag--soft tag--sm tag--removable">
                                tag 2
                                <span class="tag__remove">x</span>
                            </span>
                            <input id="tag-input-a" type="text" placeholder="Type a tag and press Enter...">
                        </div>
                    </div>

                    <div>
                        <label class="field-label" for="tag-input-b">There should be dropdown menu to add tag to tag
                            group and create tag group etc</label>
                        <div class="tag-input">
                            <span class="tag tag--soft tag--sm tag--removable">
                                tag 1
                                <span class="tag__remove">x</span>
                            </span>
                            <span class="tag tag--soft tag--sm tag--removable">
                                tag 2
                                <span class="tag__remove">x</span>
                            </span>
                            <input id="tag-input-b" type="text" placeholder="Maximum 5 tags...">
                        </div>
                    </div>
                </div>
            </section> --}}
        </div>
    </main>
</body>

</html>
