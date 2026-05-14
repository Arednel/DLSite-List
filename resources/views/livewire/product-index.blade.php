<div>
    <div class="header"></div>

    <x-list-menu-float :quick-add-url="$quickAddUrl" />

    <div id="list-container" class="list-container">
        <div class="cover-block">
            <div id="cover-image-container" class="image-container">
                <img id="cover-image" src="{{ asset('images/sakura.png') }}">
            </div>
        </div>

        <div id="progress-menu" class="progress-menu-container">
            <div class="progress-menu">
                <a href="{{ route('index', $allProgressQuery, false) }}" @class([
                    'progress-button',
                    'all_anime',
                    'on' => $progressHeading === 'All ASMR',
                ])>
                    All ASMR</a>
                <a href="{{ route('index', array_merge($allProgressQuery, ['progress' => 'Listening']), false) }}"
                    @class([
                        'progress-button',
                        'watching',
                        'on' => $progressHeading === 'Listening',
                    ])>
                    Currently Listening</a>
                <a href="{{ route('index', array_merge($allProgressQuery, ['progress' => 'Completed']), false) }}"
                    @class([
                        'progress-button',
                        'completed',
                        'on' => $progressHeading === 'Completed',
                    ])>
                    Completed</a>
                <a href="{{ route('index', array_merge($allProgressQuery, ['progress' => 'Plan to Listen']), false) }}"
                    @class([
                        'progress-button',
                        'plantowatch',
                        'on' => $progressHeading === 'Plan to Listen',
                    ])>
                    Plan to Listen</a>

                <div class="search-container">
                    <form wire:submit.prevent="applySearch" class="search-form">
                        <input type="text" name="search" wire:model="searchInput" placeholder="Search..."
                            class="search-input">
                        <button type="submit" class="search-button">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="list-block">
            <div class="list-unit onhold">
                <div class="list-status-title">
                    <span class="text">
                        {{ $progressHeading }}
                    </span>
                    <x-index.advanced-filters :filter-options="$filterOptions" :filter-active="$filterActive" :has-current-tag-filter="$hasCurrentTagFilter" />
                </div>

                <table class="list-table">
                    <tbody>
                        <tr class="list-table-header">
                            <th class="header-title status"></th>
                            <th class="header-title number"></th>
                            <th class="header-title image">Image</th>
                            <th class="header-title title" data-column="Title">
                                <button type="button" class="table-sort-button" wire:click="sortByHeader('rj')">
                                    Title <span class="sort-icon">{{ $sortIcons['rj'] }}</span>
                                </button>
                            </th>
                            <th class="header-title score" data-column="Score">
                                <button type="button" class="table-sort-button" wire:click="sortByHeader('score')">
                                    Score <span class="sort-icon">{{ $sortIcons['score'] }}</span>
                                </button>
                            </th>
                            <th class="header-title Series" data-column="Series">
                                <button type="button" class="table-sort-button" wire:click="sortByHeader('series')">
                                    Series <span class="sort-icon">{{ $sortIcons['series'] }}</span>
                                </button>
                            </th>
                            <th class="header-title type" data-column="Age">
                                <button type="button" class="table-sort-button"
                                    wire:click="sortByHeader('age_category')">
                                    Age <span class="sort-icon">{{ $sortIcons['age_category'] }}</span>
                                </button>
                            </th>
                            <th class="header-title progress" data-column="Progress">
                                <button type="button" class="table-sort-button" wire:click="sortByHeader('progress')">
                                    Progress <span class="sort-icon">{{ $sortIcons['progress'] }}</span>
                                </button>
                            </th>
                            <th class="header-title tags">Tags</th>
                            <th class="header-title score"></th>
                        </tr>
                    </tbody>

                    <tbody class="list-item">
                        @forelse ($products as $product)
                            <tr class="list-table-data" id="{{ $product->id }}"
                                wire:key="product-{{ $product->id }}">
                                <td @class([
                                    'data',
                                    'status',
                                    'watching' => $product->progress === 'Listening',
                                    'completed' => $product->progress === 'Completed',
                                    'plantowatch' => $product->progress === 'Plan to Listen',
                                ])>
                                </td>
                                <td class="data number"></td>
                                <td class="data image" data-label="Image"><a
                                        href="https://www.dlsite.com/maniax/work/=/product_id/{{ $product->id }}.html"
                                        class="link sort" target="_blank">
                                        <img src="{{ $product->work_image }}" class="image"></a>
                                </td>
                                <td class="data title clearfix" data-label="Title">
                                    <a href="https://www.dlsite.com/maniax/work/=/product_id/{{ $product->id }}.html"
                                        class="link sort" target="_blank">{{ $product->id }} -
                                        {{ $product->work_name }}</a>
                                    <div class="notes">
                                        <div class="text notes">
                                            @if ($product->work_name != $product->work_name_english && $product->work_name_english)
                                                <a href="https://www.dlsite.com/maniax/work/=/product_id/{{ $product->id }}.html"
                                                    class="link sort" target="_blank">
                                                    {{ $product->id }} - {{ $product->work_name_english }}</a>
                                            @endif
                                        </div>
                                    </div>

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
                                            <a href="{{ route('index', ['series' => $product->series], false) }}">
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
                                        @foreach ($productGenres->get($product->id) ?? [] as $genre)
                                            <a
                                                href="{{ route('index', array_merge($tagBaseQuery, ['genre' => $genre->id]), false) }}">
                                                {{ $genre->title }}</a>{{ !$loop->last ? ',' : '' }}
                                        @endforeach
                                    </div>
                                </td>
                                <td class="data actions" data-label="Actions">
                                    <div class="add-edit-more">
                                        <span class="edit">
                                            <a href="{{ route(
                                                'products.edit',
                                                [
                                                    'id' => $product->id,
                                                    'return_route' => 'index',
                                                    'return_query' => $currentQuery,
                                                    'return_fragment' => $product->id,
                                                ],
                                                false,
                                            ) }}"
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

                @if (!$isUnlimited && $products->total() > 0)
                    {{ $products->links('livewire.index-pagination-links', data: ['scrollTo' => '#progress-menu']) }}
                @elseif ($isUnlimited)
                    <div class="index-pagination">
                        <div class="index-pagination__summary">
                            Showing all {{ $totalProducts }} works
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
