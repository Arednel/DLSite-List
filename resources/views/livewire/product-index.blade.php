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
                <div class="progress-links">
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
                    @if ($optionalProductStatuses['on_hold'])
                        <a href="{{ route('index', array_merge($allProgressQuery, ['progress' => 'On Hold']), false) }}"
                            @class([
                                'progress-button',
                                'progress-on-hold',
                                'on' => $activeProgress === 'On Hold',
                            ])>
                            {{ __('On Hold') }}</a>
                    @endif
                    @if ($optionalProductStatuses['dropped'])
                        <a href="{{ route('index', array_merge($allProgressQuery, ['progress' => 'Dropped']), false) }}"
                            @class([
                                'progress-button',
                                'progress-dropped',
                                'on' => $activeProgress === 'Dropped',
                            ])>
                            {{ __('Dropped') }}</a>
                    @endif
                    <a href="{{ route('index', array_merge($allProgressQuery, ['progress' => 'Plan to Listen']), false) }}"
                        @class([
                            'progress-button',
                            'progress-plan-to-listen',
                            'on' => $activeProgress === 'Plan to Listen',
                        ])>
                        {{ __('Plan to Listen') }}</a>
                </div>

                <x-index.search class="search-container--desktop" data-index-search="desktop" />
            </div>
        </div>

        <div class="list-block">
            <div class="list-unit product-list-panel">
                <div class="list-status-title">
                    <span class="progress-heading">
                        {{ $progressHeading }}
                    </span>
                    <x-index.search class="search-container--mobile" data-index-search="mobile" />
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
                                    'progress-on-hold' => $product->progress === 'On Hold',
                                    'progress-dropped' => $product->progress === 'Dropped',
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
                                                @if ($imageViewerEnabled)
                                                    <button type="button" class="product-link index-image-viewer-trigger"
                                                        data-index-image-viewer-product="{{ $product->id }}"
                                                        data-index-image-viewer-title="{{ $product->id }} - {{ $product->workName }}"
                                                        aria-label="{{ __('View saved images for :title', ['title' => $product->workName]) }}"
                                                        aria-haspopup="dialog" aria-controls="index-image-viewer-dialog">
                                                        <img src="{{ $product->workImage }}" class="image" loading="lazy"
                                                            alt="">
                                                    </button>
                                                @else
                                                    <a href="{{ $product->dlsiteWorkUrl }}" class="product-link"
                                                        target="_blank">
                                                        <img src="{{ $product->workImage }}" class="image" loading="lazy"
                                                            alt="">
                                                    </a>
                                                @endif
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
                                                    <span>{{ __($product->progress) }}</span>
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

            @if ($imageViewerEnabled)
                <dialog id="index-image-viewer-dialog" class="index-image-viewer" aria-labelledby="index-image-viewer-title"
                    wire:ignore data-image-label="{{ __('Image :current of :total for :title') }}">
                    <div class="index-image-viewer__panel">
                        <header class="index-image-viewer__header">
                            <h2 id="index-image-viewer-title" data-index-image-viewer-title>{{ __('Work images') }}</h2>
                            <button type="button" class="index-image-viewer__close" data-index-image-viewer-close
                                aria-label="{{ __('Close image viewer') }}">
                                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                            </button>
                        </header>

                        <div class="index-image-viewer__stage">
                            <button type="button"
                                class="index-image-viewer__navigation index-image-viewer__navigation--previous"
                                data-index-image-viewer-previous aria-label="{{ __('Previous image') }}">
                                <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                            </button>

                            <div class="index-image-viewer__media">
                                <img data-index-image-viewer-image hidden alt="">
                                <div class="index-image-viewer__placeholder" data-index-image-viewer-placeholder hidden
                                    role="status">
                                    <i class="fa-regular fa-image" aria-hidden="true"></i>
                                    <span>{{ __('No image') }}</span>
                                </div>
                                <p class="index-image-viewer__loading" data-index-image-viewer-loading role="status">
                                    {{ __('Loading images...') }}</p>
                            </div>

                            <button type="button" class="index-image-viewer__navigation index-image-viewer__navigation--next"
                                data-index-image-viewer-next aria-label="{{ __('Next image') }}">
                                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                            </button>
                        </div>

                        <footer class="index-image-viewer__footer">
                            <output class="index-image-viewer__counter" data-index-image-viewer-counter
                                aria-live="polite"></output>
                            <a class="index-image-viewer__view-full" data-index-image-viewer-full target="_blank"
                                rel="noopener noreferrer" hidden>
                                {{ __('View in full') }}
                                <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                            </a>
                        </footer>
                    </div>
                </dialog>

                @assets
                    <script
                        src="{{ asset('scripts/index-image-viewer.js') }}?v={{ filemtime(public_path('scripts/index-image-viewer.js')) }}"
                        defer></script>
                @endassets
            @endif
        </div>
