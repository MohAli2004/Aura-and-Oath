@props([
    'title',
    'products',
    'moreUrl' => null,
    'empty' => null,
    'pageSize' => 8,
])

@php
    $products = collect($products);
    $pages = $products->chunk(max(1, (int) $pageSize))->values();
    $pageCount = max(1, $pages->count());
    $canSlide = $pages->count() > 1;
@endphp

<section
    {{ $attributes->class('max-w-7xl mx-auto px-4 sm:px-6 py-16') }}
    data-product-rail-root
    data-pages="{{ $pageCount }}"
>
    <div class="mb-8 flex items-end justify-between gap-3">
        <h2 class="font-display text-4xl">{{ $title }}</h2>
        <div class="flex shrink-0 items-center gap-2">
            @if($canSlide)
                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center border border-beige bg-[#FFFCFA] text-charcoal transition hover:border-charcoal disabled:cursor-not-allowed disabled:opacity-30 lg:hidden"
                    data-rail-prev
                    aria-label="{{ __('storefront.previous_products') }}"
                >
                    <x-icon name="chevron-left" class="h-5 w-5 rtl:rotate-180" />
                </button>
                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center border border-beige bg-[#FFFCFA] text-charcoal transition hover:border-charcoal disabled:cursor-not-allowed disabled:opacity-30 lg:hidden"
                    data-rail-next
                    aria-label="{{ __('storefront.next_products') }}"
                >
                    <x-icon name="chevron-right" class="h-5 w-5 rtl:rotate-180" />
                </button>
            @endif
            @if($moreUrl)
                <a href="{{ $moreUrl }}" class="ms-1 text-sm text-taupe">{{ __('storefront.show_more') }}</a>
            @endif
        </div>
    </div>

    @if($products->isEmpty())
        <p class="text-taupe">{{ $empty }}</p>
    @else
        <div class="flex items-center gap-3 lg:gap-4">
            @if($canSlide)
                <button
                    type="button"
                    class="relative z-10 hidden h-12 w-12 shrink-0 items-center justify-center border border-beige bg-[#FFFCFA] text-charcoal transition hover:border-gold disabled:cursor-not-allowed disabled:opacity-30 lg:inline-flex"
                    data-rail-prev
                    aria-label="{{ __('storefront.previous_products') }}"
                >
                    <x-icon name="chevron-left" class="h-6 w-6 rtl:rotate-180" />
                </button>
            @endif

            <div class="min-w-0 flex-1 overflow-hidden [container-type:inline-size]" data-product-rail>
                <div
                    class="flex transition-transform duration-500 ease-in-out will-change-transform"
                    data-rail-track
                    style="transform: translate3d(0, 0, 0);"
                >
                    @foreach($pages as $page)
                        <div class="grid shrink-0 grid-cols-2 items-stretch gap-5 md:grid-cols-4" style="flex: 0 0 100cqw; width: 100cqw;">
                            @foreach($page as $product)
                                <x-product-card :product="$product" />
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>

            @if($canSlide)
                <button
                    type="button"
                    class="relative z-10 hidden h-12 w-12 shrink-0 items-center justify-center border border-beige bg-[#FFFCFA] text-charcoal transition hover:border-gold disabled:cursor-not-allowed disabled:opacity-30 lg:inline-flex"
                    data-rail-next
                    aria-label="{{ __('storefront.next_products') }}"
                >
                    <x-icon name="chevron-right" class="h-6 w-6 rtl:rotate-180" />
                </button>
            @endif
        </div>
    @endif
</section>

@once
    <script nonce="{{ csp_nonce() }}">
        (function () {
            function bindProductRails() {
                document.querySelectorAll('[data-product-rail-root]').forEach(function (root) {
                    if (root.getAttribute('data-rail-bound') === '1') {
                        return;
                    }

                    root.setAttribute('data-rail-bound', '1');

                    var page = 0;
                    var pages = Math.max(1, Number(root.getAttribute('data-pages') || 1));
                    var track = root.querySelector('[data-rail-track]');
                    var prevButtons = root.querySelectorAll('[data-rail-prev]');
                    var nextButtons = root.querySelectorAll('[data-rail-next]');

                    function update() {
                        if (! track) {
                            return;
                        }

                        var rtl = document.documentElement.getAttribute('dir') === 'rtl';
                        var offset = (rtl ? page : -page) * 100;
                        track.style.transform = 'translate3d(' + offset + 'cqw, 0, 0)';

                        prevButtons.forEach(function (button) {
                            button.disabled = page === 0;
                        });
                        nextButtons.forEach(function (button) {
                            button.disabled = page >= pages - 1;
                        });
                    }

                    prevButtons.forEach(function (button) {
                        button.addEventListener('click', function (event) {
                            event.preventDefault();
                            if (page > 0) {
                                page -= 1;
                                update();
                            }
                        });
                    });

                    nextButtons.forEach(function (button) {
                        button.addEventListener('click', function (event) {
                            event.preventDefault();
                            if (page < pages - 1) {
                                page += 1;
                                update();
                            }
                        });
                    });

                    update();
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bindProductRails);
            } else {
                bindProductRails();
            }
        })();
    </script>
@endonce
