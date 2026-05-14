@php
    $scrollSelector = $scrollTo === false ? null : $scrollTo ?? 'body';
    $scrollIntoViewJsSnippet =
        $scrollSelector === null
            ? ''
            : "(\$el.closest('{$scrollSelector}') || document.querySelector('{$scrollSelector}'))?.scrollIntoView()";
@endphp

<nav class="index-pagination" role="navigation" aria-label="Pagination Navigation">
    <div class="index-pagination__summary">
        Showing {{ $paginator->firstItem() }}-{{ $paginator->lastItem() }} of {{ $paginator->total() }}
    </div>

    @if ($paginator->hasPages())
        <div class="index-pagination__controls">
            @if ($paginator->onFirstPage())
                <button type="button" disabled>
                    Previous
                </button>
            @else
                <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')"
                    @if ($scrollIntoViewJsSnippet !== '') x-on:click="{{ $scrollIntoViewJsSnippet }}" @endif
                    wire:loading.attr="disabled">
                    Previous
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
                                wire:loading.attr="disabled" aria-label="Go to page {{ $page }}">
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
                    Next
                </button>
            @else
                <button type="button" disabled>
                    Next
                </button>
            @endif
        </div>
    @endif
</nav>
