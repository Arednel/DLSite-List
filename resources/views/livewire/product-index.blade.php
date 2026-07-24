<div style="--index-table-width: {{ $tableWidthCss }}">
    <div class="header"></div>

    <x-list-menu-float :quick-add-url="$quickAddUrl" :product-form-modal-enabled="$productFormModalEnabled" :product-form-modal-completion-action="$productFormModalCompletionAction" />

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
                    'on' => $activeProgress === null,
                ])>
                    {{ __('All ASMR') }}</a>
                <a href="{{ route('index', array_merge($allProgressQuery, ['progress' => 'Listening']), false) }}"
                    @class([
                        'progress-button',
                        'progress-listening',
                        'on' => $activeProgress === 'Listening',
                    ])>
                    {{ __('Currently Listening') }}</a>
                <a href="{{ route('index', array_merge($allProgressQuery, ['progress' => 'Completed']), false) }}"
                    @class([
                        'progress-button',
                        'progress-completed',
                        'on' => $activeProgress === 'Completed',
                    ])>
                    {{ __('Completed') }}</a>
                <a href="{{ route('index', array_merge($allProgressQuery, ['progress' => 'Plan to Listen']), false) }}"
                    @class([
                        'progress-button',
                        'progress-plan-to-listen',
                        'on' => $activeProgress === 'Plan to Listen',
                    ])>
                    {{ __('Plan to Listen') }}</a>

                <div class="search-container">
                    <form wire:submit.prevent="applySearch" class="search-form">
                        <input type="text" name="search" wire:model="searchInput"
                            placeholder="{{ __('Search...') }}" class="search-input">
                        <button type="submit" class="search-button" aria-label="{{ __('Search') }}">
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
                        @forelse ($productRows as $product)
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
                                                <a href="{{ $product->dlsiteWorkUrl }}" class="product-link" target="_blank">
                                                    <img src="{{ $product->workImage }}" class="image"></a>
                                            @break

                                            @case('title')
                                                <a href="{{ $product->dlsiteWorkUrl }}" class="product-link"
                                                    target="_blank">{{ $product->id }} -
                                                    {{ $product->workName }}</a>
                                                <div class="notes">
                                                    <div class="note-text">
                                                        @if ($product->workName != $product->workNameEnglish && $product->workNameEnglish)
                                                            <a href="{{ $product->dlsiteWorkUrl }}" class="product-link"
                                                                target="_blank">
                                                                {{ $product->id }} - {{ $product->workNameEnglish }}</a>
                                                        @endif
                                                    </div>
                                                </div>

                                                <div class="notes">
                                                    <div class="user-note-text">{{ $product->notes }}</div>
                                                </div>
                                            @break

                                            @case('score')
                                                <span class="cell-value">{{ $product->score ?? '-' }}</span>
                                            @break

                                            @case('series')
                                                <span class="cell-value">
                                                    @if ($product->seriesUrl === null)
                                                        -
                                                    @else
                                                        <a href="{{ $product->seriesUrl }}">
                                                            {{ $product->series }}
                                                        </a>
                                                    @endif
                                                </span>
                                            @break

                                            @case('age_category')
                                                {{ $filterOptions['age_categories'][$product->ageCategory] ?? ($product->ageCategory ?? '-') }}
                                            @break

                                            @case('progress')
                                                <div class="progress">
                                                    <span>{{ $filterOptions['progress'][$product->progress] ?? $product->progress }}</span>
                                                </div>
                                            @break

                                            @case('notes')
                                                <div class="notes">
                                                    <div class="user-note-text">{{ $product->notes ?: '-' }}</div>
                                                </div>
                                            @break

                                            @case('start_date')
                                                {{ $productDisplayValues[$product->id][$column['field']] ?? '-' }}
                                            @break

                                            @case('end_date')
                                                {{ $productDisplayValues[$product->id][$column['field']] ?? '-' }}
                                            @break

                                            @case('num_re_listen_times')
                                                {{ $productDisplayValues[$product->id][$column['field']] ?? '-' }}
                                            @break

                                            @case('re_listen_value')
                                                {{ $productDisplayValues[$product->id][$column['field']] ?? '-' }}
                                            @break

                                            @case('priority')
                                                {{ $productDisplayValues[$product->id][$column['field']] ?? '-' }}
                                            @break

                                            @case('circle')
                                                @forelse ($product->contributors[$column['contributor_role']] ?? [] as $contributor)
                                                    <a href="{{ $contributor->indexUrl }}">
                                                        {{ $contributor->name }}</a>
                                                    @if ($contributor->makerId)
                                                        <span class="metadata-note">({{ $contributor->makerId }})</span>
                                                    @endif{{ !$loop->last ? ',' : '' }}
                                                @empty
                                                    @if ($product->circle)
                                                        <a href="{{ $product->circleUrl }}">
                                                            {{ $product->circle }}</a>
                                                        @if ($product->makerId)
                                                            <span class="metadata-note">({{ $product->makerId }})</span>
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
                                                    @forelse ($product->contributors[$column['contributor_role']] ?? [] as $contributor)
                                                        <a href="{{ $contributor->indexUrl }}">
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
                                                        @if ($product->descriptionEnglish)
                                                            <div>{{ $product->descriptionEnglish }}</div>
                                                        @else
                                                            -
                                                        @endif
                                                    </div>
                                                @break

                                                @case('tags')
                                                    <div class="tags">
                                                        @foreach ($productGenres[$product->id] ?? [] as $genre)
                                                            @if (($genre->has_background_color ?? false) || ($genre->has_font_color ?? false))
                                                                <a @class([
                                                                    'index-tag-chip',
                                                                    'index-tag-chip--background-colored' =>
                                                                        ($genre->has_background_color ?? false) === true,
                                                                    'index-tag-chip--text-colored' =>
                                                                        ($genre->has_font_color ?? false) === true,
                                                                ])
                                                                    @if (filled($genre->color_style ?? null)) style="{{ $genre->color_style }}" @endif
                                                                    href="{{ $tagHrefPrefix }}genre={{ $genre->id }}">
                                                                    {{ $genre->title }}</a>{{ !$loop->last ? ',' : '' }}
                                                            @else
                                                                <a
                                                                    href="{{ $tagHrefPrefix }}genre={{ $genre->id }}">{{ $genre->title }}</a>{{ !$loop->last ? ',' : '' }}
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                @break
                                            @endswitch
                                        </td>
                                    @endforeach
                                    <td class="data actions" data-label="{{ __('Actions') }}">
                                        <div class="row-actions">
                                            <span class="edit-action">
                                                <a href="{{ $product->editUrl }}" class="product-edit-link"
                                                    data-work-form-modal-link
                                                    data-work-form-modal-title="{{ __('Edit Work') }}">{{ __('Edit') }}</a>
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                    <tr class="list-table-empty-row">
                                        <td class="list-table-empty" colspan="{{ 3 + count($indexColumns) }}">
                                            {{ __('Nothing found for the current filters.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>

                        @if (!$isUnlimited && $products->total() > 0)
                            {{ $products->links('livewire.index-pagination-links') }}
                        @elseif ($isUnlimited)
                            <div class="index-pagination">
                                <div class="index-pagination__summary">
                                    {{ __('Showing all :count works', ['count' => $totalProducts]) }}
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
