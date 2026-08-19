@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}">
        <ul class="flex flex-wrap items-center justify-center gap-1">
            <li>
                @if ($paginator->onFirstPage())
                    <span class="inline-flex min-h-9 min-w-9 items-center justify-center border border-beige px-2 text-sm text-taupe/60">Prev</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex min-h-9 min-w-9 items-center justify-center border border-beige bg-[#FFFCFA] px-2 text-sm hover:border-charcoal">Prev</a>
                @endif
            </li>

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="inline-flex min-h-9 min-w-9 items-center justify-center px-2 text-sm text-taupe">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li>
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="inline-flex min-h-9 min-w-9 items-center justify-center border border-charcoal bg-charcoal px-2 text-sm text-ivory">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="inline-flex min-h-9 min-w-9 items-center justify-center border border-beige bg-[#FFFCFA] px-2 text-sm hover:border-charcoal" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach

            <li>
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex min-h-9 min-w-9 items-center justify-center border border-beige bg-[#FFFCFA] px-2 text-sm hover:border-charcoal">Next</a>
                @else
                    <span class="inline-flex min-h-9 min-w-9 items-center justify-center border border-beige px-2 text-sm text-taupe/60">Next</span>
                @endif
            </li>
        </ul>
    </nav>
@endif
