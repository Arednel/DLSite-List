@php
    $scrollSelector = $scrollTo === false ? null : $scrollTo ?? 'body';
    $scrollIntoViewJsSnippet =
        $scrollSelector === null
            ? ''
            : "(\$el.closest('{$scrollSelector}') || document.querySelector('{$scrollSelector}'))?.scrollIntoView()";
@endphp

<nav class="index-pagination" role="navigation" aria-label="{{ __('Pagination Navigation') }}">
    <div class="index-pagination__summary">
        {{ __('Showing :first-:last of :total', [
            'first' => $paginator->firstItem(),
            'last' => $paginator->lastItem(),
            'total' => $paginator->total(),
        ]) }}
    </div>

    @if ($paginator->hasPages())
        <div class="index-pagination__controls">
            @if ($paginator->onFirstPage())
                <button type="button" disabled>
                    {{ __('Previous') }}
                </button>
            @else
                <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')"
                    @if ($scrollIntoViewJsSnippet !== '') x-on:click="{{ $scrollIntoViewJsSnippet }}" @endif
                    wire:loading.attr="disabled">
                    {{ __('Previous') }}
                </button>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="index-pagination__ellipsis">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page === $paginator->currentPage())
                            <span class="index-pagination__page is-active" aria-current="page">
                                {{ $page }}
                            </span>
                        @else
                            <button type="button" wire:key="index-page-{{ $page }}"
                                wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                                @if ($scrollIntoViewJsSnippet !== '') x-on:click="{{ $scrollIntoViewJsSnippet }}" @endif
                                wire:loading.attr="disabled"
                                aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                {{ $page }}
                            </button>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')"
                    @if ($scrollIntoViewJsSnippet !== '') x-on:click="{{ $scrollIntoViewJsSnippet }}" @endif
                    wire:loading.attr="disabled">
                    {{ __('Next') }}
                </button>
            @else
                <button type="button" disabled>
                    {{ __('Next') }}
                </button>
            @endif
        </div>
    @endif
</nav>
