<div style="--index-table-width: {{ $tableWidthCss }}">
    <div class="header"></div>

    <x-list-menu-float :quick-add-url="$quickAddUrl" />

    <div id="list-container" class="list-container">
        <div class="cover-block">
            <div id="cover-image-container" class="image-container">
                <img id="cover-image" src="{{ asset('images/Sakura.png') }}">
            </div>
        </div>

        <div id="progress-menu" class="progress-menu-container">
            <div class="progress-menu">
                <a href="{{ route('index', $allProgressQuery, false) }}" @class([
                    'progress-button',
                    'progress-all',
                    'on' => $progressHeading === 'All ASMR',
                ])>
                    All ASMR</a>
                <a href="{{ route('index', array_merge($allProgressQuery, ['progress' => 'Listening']), false) }}"
                    @class([
                        'progress-button',
                        'progress-listening',
                        'on' => $progressHeading === 'Listening',
                    ])>
                    Currently Listening</a>
                <a href="{{ route('index', array_merge($allProgressQuery, ['progress' => 'Completed']), false) }}"
                    @class([
                        'progress-button',
                        'progress-completed',
                        'on' => $progressHeading === 'Completed',
                    ])>
                    Completed</a>
                <a href="{{ route('index', array_merge($allProgressQuery, ['progress' => 'Plan to Listen']), false) }}"
                    @class([
                        'progress-button',
                        'progress-plan-to-listen',
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
            <div class="list-unit product-list-panel">
                <div class="list-status-title">
                    <span class="progress-heading">
                        {{ $progressHeading }}
                    </span>
                    <x-index.advanced-filters :filter-options="$filterOptions" :filter-active="$filterActive" :has-current-tag-filter="$hasCurrentTagFilter" :filter-fields="$filterFields" />
                </div>

                <table class="list-table">
                    <tbody>
                        <tr class="list-table-header">
                            <th class="header-title status"></th>
                            <th class="header-title number"></th>
                            @foreach ($indexColumns as $column)
                                <th @class([
                                    'header-title',
                                    'configurable' => !in_array($column['field'], ['title', 'image'], true),
                                    $column['class'],
                                ]) data-column="{{ $column['label'] }}">
                                    @if ($column['sort_field'])
                                        <button type="button" class="table-sort-button"
                                            wire:click="sortByHeader('{{ $column['sort_field'] }}')">
                                            {{ $column['label'] }}
                                            <span class="sort-icon">{{ $sortIcons[$column['sort_field']] }}</span>
                                        </button>
                                    @else
                                        {{ $column['label'] }}
                                    @endif
                                </th>
                            @endforeach
                            <th class="header-title actions"></th>
                        </tr>
                    </tbody>

                    <tbody class="list-item">
                        @forelse ($visibleProducts as $product)
                            <tr class="list-table-data" id="{{ $product->id }}"
                                wire:key="product-{{ $product->id }}">
                                <td @class([
                                    'data',
                                    'status',
                                    'progress-listening' => $product->progress === 'Listening',
                                    'progress-completed' => $product->progress === 'Completed',
                                    'progress-plan-to-listen' => $product->progress === 'Plan to Listen',
                                ])>
                                </td>
                                <td class="data number"></td>

                                @foreach ($indexColumns as $column)
                                    <td @class([
                                        'data',
                                        'configurable' => !in_array($column['field'], ['title', 'image'], true),
                                        'clearfix' => $column['field'] === 'title',
                                        $column['class'],
                                    ]) data-label="{{ $column['label'] }}">
                                        @switch($column['field'])
                                            @case('image')
                                                <a href="https://www.dlsite.com/maniax/work/=/product_id/{{ $product->id }}.html"
                                                    class="product-link" target="_blank">
                                                    <img src="{{ $product->work_image }}" class="image"></a>
                                            @break

                                            @case('title')
                                                <a href="https://www.dlsite.com/maniax/work/=/product_id/{{ $product->id }}.html"
                                                    class="product-link" target="_blank">{{ $product->id }} -
                                                    {{ $product->work_name }}</a>
                                                <div class="notes">
                                                    <div class="note-text">
                                                        @if ($product->work_name != $product->work_name_english && $product->work_name_english)
                                                            <a href="https://www.dlsite.com/maniax/work/=/product_id/{{ $product->id }}.html"
                                                                class="product-link" target="_blank">
                                                                {{ $product->id }} - {{ $product->work_name_english }}</a>
                                                        @endif
                                                    </div>
                                                </div>

                                                <div class="notes">
                                                    <div class="note-text">
                                                        {!! nl2br(e($product->notes)) !!}
                                                    </div>
                                                </div>
                                            @break

                                            @case('score')
                                                <span class="cell-value">{{ $product->score ?? '-' }}</span>
                                            @break

                                            @case('series')
                                                <span class="cell-value">
                                                    @if ($product->series == null)
                                                        -
                                                    @else
                                                        <a href="{{ route('index', ['series' => $product->series], false) }}">
                                                            {{ $product->series }}
                                                        </a>
                                                    @endif
                                                </span>
                                            @break

                                            @case('age_category')
                                                {{ $product->age_category === 'ALL_AGES' ? 'All Ages' : $product->age_category ?? '-' }}
                                            @break

                                            @case('progress')
                                                <div class="progress"><span>{{ $product->progress }}</span></div>
                                            @break

                                            @case('notes')
                                                <div class="notes">
                                                    <div class="note-text">
                                                        {!! nl2br(e($product->notes ?: '-')) !!}
                                                    </div>
                                                </div>
                                            @break

                                            @case('start_date')
                                                {{ $productDisplayValues[$product->getKey()][$column['field']] ?? '-' }}
                                            @break

                                            @case('end_date')
                                                {{ $productDisplayValues[$product->getKey()][$column['field']] ?? '-' }}
                                            @break

                                            @case('num_re_listen_times')
                                                {{ $productDisplayValues[$product->getKey()][$column['field']] ?? '-' }}
                                            @break

                                            @case('re_listen_value')
                                                {{ $productDisplayValues[$product->getKey()][$column['field']] ?? '-' }}
                                            @break

                                            @case('priority')
                                                {{ $productDisplayValues[$product->getKey()][$column['field']] ?? '-' }}
                                            @break

                                            @case('circle')
                                                @forelse (($productContributors[$product->getKey()] ?? [])[$column['contributor_role']] ?? [] as $contributor)
                                                    <a href="{{ route('index', ['circle' => $contributor->name], false) }}">
                                                        {{ $contributor->name }}</a>
                                                    @if ($contributor->maker_id)
                                                        <span class="metadata-note">({{ $contributor->maker_id }})</span>
                                                    @endif{{ !$loop->last ? ',' : '' }}
                                                @empty
                                                    @if ($product->circle)
                                                        <a href="{{ route('index', ['circle' => $product->circle], false) }}">
                                                            {{ $product->circle }}</a>
                                                        @if ($product->maker_id)
                                                            <span class="metadata-note">({{ $product->maker_id }})</span>
                                                        @endif
                                                    @else
                                                        -
                                                    @endif
                                                @endforelse
                                                @break

                                                @case('scenario')
                                                @case('illustration')

                                                @case('voice_actor')
                                                @case('author')
                                                    @forelse (($productContributors[$product->getKey()] ?? [])[$column['contributor_role']] ?? [] as $contributor)
                                                        <a
                                                            href="{{ route('index', [$column['field'] => $contributor->name], false) }}">
                                                            {{ $contributor->name }}</a>{{ !$loop->last ? ',' : '' }}
                                                    @empty
                                                        -
                                                    @endforelse
                                                @break

                                                @case('description_japanese')
                                                    <div class="description-cell">
                                                        @if ($product->description)
                                                            <div>{{ $product->description }}</div>
                                                        @else
                                                            -
                                                        @endif
                                                    </div>
                                                @break

                                                @case('description_english')
                                                    <div class="description-cell">
                                                        @if ($product->description_english)
                                                            <div>{{ $product->description_english }}</div>
                                                        @else
                                                            -
                                                        @endif
                                                    </div>
                                                @break

                                                @case('tags')
                                                    <div class="tags">
                                                        @foreach ($productGenres[$product->getKey()] ?? [] as $genre)
                                                            @if (($genre->has_background_color ?? false) || ($genre->has_font_color ?? false))
                                                                <a @class([
                                                                    'index-tag-chip',
                                                                    'index-tag-chip--background-colored' =>
                                                                        ($genre->has_background_color ?? false) === true,
                                                                    'index-tag-chip--text-colored' =>
                                                                        ($genre->has_font_color ?? false) === true,
                                                                ])
                                                                    @if (filled($genre->color_style ?? null)) style="{{ $genre->color_style }}" @endif
                                                                    href="{{ $tagHrefBase }}{{ $tagHrefSeparator }}genre={{ $genre->id }}">
                                                                    {{ $genre->title }}</a>{{ !$loop->last ? ',' : '' }}
                                                            @else
                                                                <a
                                                                    href="{{ $tagHrefBase }}{{ $tagHrefSeparator }}genre={{ $genre->id }}">{{ $genre->title }}</a>{{ !$loop->last ? ',' : '' }}
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                @break
                                            @endswitch
                                        </td>
                                    @endforeach
                                    <td class="data actions" data-label="Actions">
                                        <div class="row-actions">
                                            <span class="edit-action">
                                                <a href="{{ route(
                                                    'products.edit',
                                                    [
                                                        'product' => $product,
                                                        'return_query' => $currentQuery,
                                                        'return_fragment' => $product->id,
                                                    ],
                                                    false,
                                                ) }}"
                                                    class="product-edit-link">Edit</a>
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                    <tr class="list-table-empty-row">
                                        <td class="list-table-empty" colspan="{{ 3 + count($indexColumns) }}">
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
