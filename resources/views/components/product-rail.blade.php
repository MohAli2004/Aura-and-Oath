@props([
    'title',
    'products',
    'moreUrl' => null,
    'moreHint' => null,
    'empty' => null,
    'pageSize' => 8,
    'maxGrids' => 3,
])

@php
    $products = collect($products);
    $pageSize = max(1, (int) $pageSize);
    $maxGrids = max(1, (int) $maxGrids);
    $pages = $products->chunk($pageSize)->take($maxGrids)->values();
    $hasShowMoreSlide = filled($moreUrl) && $products->isNotEmpty();
    $pageCount = max(1, $pages->count() + ($hasShowMoreSlide ? 1 : 0));
    $canSlide = $pageCount > 1;
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

            <div class="min-w-0 flex-1 overflow-hidden [container-type:inline-size] {{ $canSlide ? 'cursor-grab touch-pan-y active:cursor-grabbing' : '' }}" data-product-rail>
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
                    @if($hasShowMoreSlide)
                        <div
                            class="flex shrink-0 flex-col items-center justify-center gap-4 px-6 text-center"
                            style="flex: 0 0 100cqw; width: 100cqw; min-height: 22rem;"
                            data-rail-show-more
                        >
                            <p class="max-w-md text-sm leading-relaxed text-taupe">{{ $moreHint ?: __('storefront.show_more_hint') }}</p>
                            <a href="{{ $moreUrl }}" class="btn btn-secondary">{{ __('storefront.show_more') }}</a>
                        </div>
                    @endif
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
                    var viewport = root.querySelector('[data-product-rail]');
                    var prevButtons = root.querySelectorAll('[data-rail-prev]');
                    var nextButtons = root.querySelectorAll('[data-rail-next]');
                    var dragging = false;
                    var didDrag = false;
                    var startX = 0;
                    var startY = 0;
                    var deltaX = 0;
                    var width = 0;

                    function isRtl() {
                        return document.documentElement.getAttribute('dir') === 'rtl';
                    }

                    function update() {
                        if (! track) {
                            return;
                        }

                        track.style.transition = '';
                        var offset = (isRtl() ? page : -page) * 100;
                        track.style.transform = 'translate3d(' + offset + 'cqw, 0, 0)';

                        prevButtons.forEach(function (button) {
                            button.disabled = page === 0;
                        });
                        nextButtons.forEach(function (button) {
                            button.disabled = page >= pages - 1;
                        });
                    }

                    function goTo(nextPage) {
                        page = Math.max(0, Math.min(pages - 1, nextPage));
                        update();
                    }

                    prevButtons.forEach(function (button) {
                        button.addEventListener('click', function (event) {
                            event.preventDefault();
                            goTo(page - 1);
                        });
                    });

                    nextButtons.forEach(function (button) {
                        button.addEventListener('click', function (event) {
                            event.preventDefault();
                            goTo(page + 1);
                        });
                    });

                    function interactiveTarget(target) {
                        return target && target.closest && target.closest('button, a.btn, input, select, textarea, [data-rail-prev], [data-rail-next]');
                    }

                    function onPointerDown(event) {
                        if (pages < 2 || ! track || ! viewport) {
                            return;
                        }

                        if (event.pointerType === 'mouse' && event.button !== 0) {
                            return;
                        }

                        if (interactiveTarget(event.target)) {
                            return;
                        }

                        dragging = true;
                        didDrag = false;
                        startX = event.clientX;
                        startY = event.clientY;
                        deltaX = 0;
                        width = viewport.getBoundingClientRect().width || 1;

                        try {
                            viewport.setPointerCapture(event.pointerId);
                        } catch (error) {}
                    }

                    function onPointerMove(event) {
                        if (! dragging) {
                            return;
                        }

                        deltaX = event.clientX - startX;
                        var deltaY = event.clientY - startY;

                        if (! didDrag && Math.abs(deltaX) < 10 && Math.abs(deltaY) < 10) {
                            return;
                        }

                        if (! didDrag && Math.abs(deltaY) > Math.abs(deltaX)) {
                            dragging = false;
                            update();
                            return;
                        }

                        didDrag = true;
                        event.preventDefault();
                        track.style.transition = 'none';

                        var limited = deltaX;
                        var pullingPrev = isRtl() ? deltaX < 0 : deltaX > 0;
                        var pullingNext = isRtl() ? deltaX > 0 : deltaX < 0;

                        if ((page === 0 && pullingPrev) || (page >= pages - 1 && pullingNext)) {
                            limited = deltaX * 0.28;
                        }

                        var base = (isRtl() ? page : -page) * width;
                        track.style.transform = 'translate3d(' + (base + limited) + 'px, 0, 0)';
                    }

                    function onPointerUp() {
                        if (! dragging && ! didDrag) {
                            return;
                        }

                        dragging = false;
                        track.style.transition = '';

                        var threshold = Math.max(48, width * 0.18);
                        var rtl = isRtl();
                        var goNext = rtl ? deltaX > threshold : deltaX < -threshold;
                        var goPrev = rtl ? deltaX < -threshold : deltaX > threshold;

                        if (didDrag && goNext) {
                            goTo(page + 1);
                        } else if (didDrag && goPrev) {
                            goTo(page - 1);
                        } else {
                            update();
                        }

                        deltaX = 0;

                        if (didDrag) {
                            setTimeout(function () {
                                didDrag = false;
                            }, 0);
                        }
                    }

                    if (viewport && pages > 1) {
                        viewport.addEventListener('pointerdown', onPointerDown);
                        viewport.addEventListener('pointermove', onPointerMove);
                        viewport.addEventListener('pointerup', onPointerUp);
                        viewport.addEventListener('pointercancel', onPointerUp);
                        viewport.addEventListener('click', function (event) {
                            if (! didDrag) {
                                return;
                            }

                            event.preventDefault();
                            event.stopPropagation();
                            didDrag = false;
                        }, true);
                    }

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
