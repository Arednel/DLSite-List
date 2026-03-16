<!DOCTYPE html>

<head>
    <title>DLSite List</title>

    <meta name="viewport" content="initial-scale=1">

    <link rel="stylesheet" href="{{ asset('css/index.css') }}">
    <link rel="stylesheet" href="{{ asset('css/list-menu-float.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
</head>

<body class="ownlist anime" data-work="anime">

    <div class="header"></div>

    <x-list-menu-float />

    <div id="list-container" class="list-container">
        <div class="cover-block">
            <div id="cover-image-container" class="image-container">
                <img id="cover-image" src="{{ asset('images/sakura.png') }}">
            </div>
        </div>

        <div id="progress-menu" class="progress-menu-container">
            <div class="progress-menu">
                <a href="/?age_category={{ request('age_category') }}"
                    class="progress-button all_anime {{ $progress == 'All ASMR' ? 'on' : '' }}">
                    All ASMR</a>
                <a href="/?age_category={{ request('age_category') }}&progress=Listening"
                    class="progress-button watching {{ $progress == 'Listening' ? 'on' : '' }}">
                    Currently Listening</a>
                <a href="/?age_category={{ request('age_category') }}&progress=Completed"
                    class="progress-button completed {{ $progress == 'Completed' ? 'on' : '' }}">
                    Completed</a>
                <a href="/?age_category={{ request('age_category') }}&progress=Plan to Listen"
                    class="progress-button plantowatch {{ $progress == 'Plan to Listen' ? 'on' : '' }}">
                    Plan to Listen</a>

                <!-- Search -->
                <div class="search-container">
                    <form method="GET" action="/" class="search-form">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search..."
                            class="search-input">
                        <button type="submit" class="search-button">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>

                        <!-- keep filters in URL -->
                        <input type="hidden" name="age_category" value="{{ request('age_category') }}">
                        <input type="hidden" name="progress" value="{{ request('progress') }}">
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
                        @foreach ($products as $product)
                            <tr class="list-table-data" id="{{ $product->id }}">
                                <td
                                    class="data status {{ $product->progress == 'Listening' ? 'watching' : '' }} {{ $product->progress == 'Completed' ? 'completed' : '' }} {{ $product->progress == 'Plan to Listen' ? 'plantowatch' : '' }}">
                                </td>
                                <td class="data number"></td>
                                <td class="data image"><a
                                        href="https://www.dlsite.com/maniax/work/=/product_id/{{ $product->id }}.html"
                                        class="link sort" target="_blank">
                                        <img src="{{ $product->work_image }}" class="image"></a>
                                </td>
                                <td class="data title clearfix">
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

                                <td class="data score">
                                    <span class="score-label score-na">
                                        @if ($product->score == null)
                                            -
                                        @endif
                                        {{ $product->score }}
                                    </span>
                                </td>

                                <td class="data series">
                                    <span class="score-label score-na">
                                        @if ($product->series == null)
                                            -
                                        @endif
                                        <a href="/?series={{ $product->series }}">
                                            {{ $product->series }}
                                        </a>
                                    </span>
                                </td>

                                <td class="data type">
                                    @if ($product->age_category == 'ALL_AGES')
                                        All Ages
                                    @else
                                        {{ $product->age_category }}
                                    @endif
                                </td>
                                <td class="data progress">
                                    <div class="progress"><span>{{ $product->progress }}</span> </div>
                                </td>

                                <td id="tags" class="data tags">
                                    <div class="tags">
                                        @php($visibleGenres = $product->customGenres->concat($product->englishGenres))
                                        @foreach ($visibleGenres as $genre)
                                            <a
                                                href="/?age_category={{ request('age_category') }}&progress={{ request('progress') }}&genre={{ $genre->id }}">
                                                {{ $genre->title }}</a>{{ !$loop->last ? ',' : '' }}
                                        @endforeach
                                    </div>
                                </td>
                                <td class="data">
                                    <div class="add-edit-more">
                                        <span class="edit">
                                            <a href="/edit/{{ $product->id }}?redirect={{ urlencode(request()->fullUrl()) }}#{{ $product->id }}"
                                                class="List_LightBox">Edit</a>
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
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

    <script src="{{ asset('scripts/tableSort.js') }}"></script>
</body>

</html>
