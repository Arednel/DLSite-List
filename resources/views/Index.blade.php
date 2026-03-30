<!DOCTYPE html>

<head>
    <title>DLSite List</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="{{ asset('css/index.css') }}?v={{ filemtime(public_path('css/index.css')) }}">
    <link rel="stylesheet"
        href="{{ asset('css/list-menu-float.css') }}?v={{ filemtime(public_path('css/list-menu-float.css')) }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
</head>

<body class="ownlist anime" data-work="anime">
    <div class="header"></div>

    <x-list-menu-float :quick-add-url="route('products.create', ['return_route' => 'index', 'return_query' => $filterQuery], false)" />

    <div id="list-container" class="list-container">
        <div class="cover-block">
            <div id="cover-image-container" class="image-container">
                <img id="cover-image" src="{{ asset('images/sakura.png') }}">
            </div>
        </div>

        <div id="progress-menu" class="progress-menu-container">
            <div class="progress-menu">
                <a href="{{ route('index', $allProgressQuery, false) }}"
                    class="progress-button all_anime {{ $progress == 'All ASMR' ? 'on' : '' }}">
                    All ASMR</a>
                <a href="{{ route('index', array_merge($allProgressQuery, ['progress' => 'Listening']), false) }}"
                    class="progress-button watching {{ $progress == 'Listening' ? 'on' : '' }}">
                    Currently Listening</a>
                <a href="{{ route('index', array_merge($allProgressQuery, ['progress' => 'Completed']), false) }}"
                    class="progress-button completed {{ $progress == 'Completed' ? 'on' : '' }}">
                    Completed</a>
                <a href="{{ route('index', array_merge($allProgressQuery, ['progress' => 'Plan to Listen']), false) }}"
                    class="progress-button plantowatch {{ $progress == 'Plan to Listen' ? 'on' : '' }}">
                    Plan to Listen</a>

                <!-- Search -->
                <div class="search-container">
                    <form method="GET" action="{{ route('index') }}" class="search-form">
                        <input type="text" name="search" value="{{ $filters->search }}" placeholder="Search..."
                            class="search-input">
                        <button type="submit" class="search-button">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>

                        @foreach ($searchFormQuery as $queryKey => $queryValue)
                            <input type="hidden" name="{{ $queryKey }}" value="{{ $queryValue }}">
                        @endforeach
                    </form>
                </div>
            </div>
        </div>

        <div class="list-block">
            <div class="list-unit onhold">

                <div class="list-status-title">
                    <span class="text">
                        {{ $progress }}
                    </span>
                    <x-index.advanced-filters :filters="$filters" :filter-options="$filterOptions" />
                </div>

                <table class="list-table">
                    <tbody>
                        <tr class="list-table-header">
                            <th class="header-title status"></th>
                            <th class="header-title number"></th>
                            <th class="header-title image">Image</th>
                            <th class="header-title title" data-column="Title">Title <span class="sort-icon">⇅</span>
                            </th>
                            <th class="header-title score" data-column="Score">Score <span class="sort-icon">⇅</span>
                            </th>
                            <th class="header-title Series" data-column="Series">Series <span class="sort-icon">⇅</span>
                            </th>
                            <th class="header-title type" data-column="Age">Age <span class="sort-icon">⇅</span></th>
                            <th class="header-title progress" data-column="Progress">Progress <span
                                    class="sort-icon">⇅</span></th>
                            <th class="header-title tags">Tags</th>
                            <th class="header-title score"></th>
                        </tr>
                    </tbody>

                    <tbody class="list-item">
                        @forelse ($products as $product)
                            <tr class="list-table-data" id="{{ $product->id }}">
                                <td
                                    class="data status {{ $product->progress == 'Listening' ? 'watching' : '' }} {{ $product->progress == 'Completed' ? 'completed' : '' }} {{ $product->progress == 'Plan to Listen' ? 'plantowatch' : '' }}">
                                </td>
                                <td class="data number"></td>
                                <td class="data image" data-label="Image"><a
                                        href="https://www.dlsite.com/maniax/work/=/product_id/{{ $product->id }}.html"
                                        class="link sort" target="_blank">
                                        <img src="{{ $product->work_image }}" class="image"></a>
                                </td>
                                <td class="data title clearfix" data-label="Title">
                                    {{-- Japanese title --}}
                                    <a href="https://www.dlsite.com/maniax/work/=/product_id/{{ $product->id }}.html"
                                        class="link sort" target="_blank">{{ $product->id }} -
                                        {{ $product->work_name }}</a>
                                    {{-- English title --}}
                                    <div class="notes">
                                        <div class="text notes">
                                            @if ($product->work_name != $product->work_name_english && $product->work_name_english)
                                                {{-- English title --}}
                                                <a href="https://www.dlsite.com/maniax/work/=/product_id/{{ $product->id }}.html"
                                                    class="link sort" target="_blank">
                                                    {{ $product->id }} - {{ $product->work_name_english }}</a>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Notes --}}
                                    <div class="notes">
                                        <div class="text notes">
                                            {!! nl2br(e($product->notes)) !!}
                                        </div>
                                    </div>
                                </td>

                                <td class="data score" data-label="Score">
                                    <span class="score-label score-na">
                                        @if ($product->score == null)
                                            -
                                        @endif
                                        {{ $product->score }}
                                    </span>
                                </td>

                                <td class="data series" data-label="Series">
                                    <span class="score-label score-na">
                                        @if ($product->series == null)
                                            -
                                        @else
                                            <a href="{{ route('index', 'series=' . $product->series) }}">
                                                {{ $product->series }}
                                            </a>
                                        @endif
                                    </span>
                                </td>

                                <td class="data type" data-label="Age">
                                    @if ($product->age_category == 'ALL_AGES')
                                        All Ages
                                    @else
                                        {{ $product->age_category }}
                                    @endif
                                </td>
                                <td class="data progress" data-label="Progress">
                                    <div class="progress"><span>{{ $product->progress }}</span> </div>
                                </td>

                                <td id="tags" class="data tags" data-label="Tags">
                                    <div class="tags">
                                        @foreach ($productGenres->get($product->id, collect()) as $genre)
                                            <a
                                                href="{{ route('index', array_merge($filterQuery, ['genre' => $genre->id]), false) }}">
                                                {{ $genre->title }}</a>{{ !$loop->last ? ',' : '' }}
                                        @endforeach
                                    </div>
                                </td>
                                <td class="data actions" data-label="Actions">
                                    <div class="add-edit-more">
                                        <span class="edit">
                                            <a href="{{ route('products.edit', ['id' => $product->id, 'return_route' => 'index', 'return_query' => $filterQuery, 'return_fragment' => $product->id], false) }}"
                                                class="List_LightBox">Edit</a>
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr class="list-table-empty-row">
                                <td class="list-table-empty" colspan="10">
                                    Nothing found for the current filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <footer>
        <div id="footer-block">
            <div id="copyright">
                My site
            </div>
        </div>
    </footer>

    <script src="{{ asset('scripts/tableSort.js') }}?v={{ filemtime(public_path('scripts/tableSort.js')) }}"></script>
</body>

</html>
