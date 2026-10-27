@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}">
        <ul class="flex flex-wrap items-center justify-center gap-1.5">
            <li>
                @if ($paginator->onFirstPage())
                    <span class="inline-flex min-h-10 min-w-10 items-center justify-center border border-beige px-3 text-sm text-taupe/60">&laquo;</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex min-h-10 min-w-10 items-center justify-center border border-beige bg-[#FFFCFA] px-3 text-sm hover:border-charcoal" aria-label="{{ __('pagination.previous') }}">&laquo;</a>
                @endif
            </li>

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="inline-flex min-h-10 min-w-10 items-center justify-center px-2 text-sm text-taupe">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li>
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="inline-flex min-h-10 min-w-10 items-center justify-center border border-charcoal bg-charcoal px-2 text-sm text-ivory">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="inline-flex min-h-10 min-w-10 items-center justify-center border border-beige bg-[#FFFCFA] px-2 text-sm hover:border-charcoal" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach

            <li>
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex min-h-10 min-w-10 items-center justify-center border border-beige bg-[#FFFCFA] px-3 text-sm hover:border-charcoal" aria-label="{{ __('pagination.next') }}">&raquo;</a>
                @else
                    <span class="inline-flex min-h-10 min-w-10 items-center justify-center border border-beige px-3 text-sm text-taupe/60">&raquo;</span>
                @endif
            </li>
        </ul>
    </nav>
@endif
