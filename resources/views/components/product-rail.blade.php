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

            <div
                class="min-w-0 flex-1 overflow-hidden {{ $canSlide ? 'cursor-grab active:cursor-grabbing' : '' }}"
                data-product-rail
                @if($canSlide) style="touch-action: pan-y;" @endif
            >
                <div
                    class="flex will-change-transform"
                    data-rail-track
                    style="transform: translate3d(0, 0, 0); transition: transform 0.5s ease-in-out;"
                >
                    @foreach($pages as $page)
                        <div class="grid w-full min-w-full shrink-0 grid-cols-2 items-stretch gap-5 md:grid-cols-4">
                            @foreach($page as $product)
                                <x-product-card :product="$product" />
                            @endforeach
                        </div>
                    @endforeach
                    @if($hasShowMoreSlide)
                        <div
                            class="flex w-full min-w-full shrink-0 flex-col items-center justify-center gap-4 px-6 text-center"
                            style="min-height: 22rem;"
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
                    var dragReset = null;

                    function isRtl() {
                        return document.documentElement.getAttribute('dir') === 'rtl';
                    }

                    function clientX(event) {
                        if (event.touches && event.touches[0]) {
                            return event.touches[0].clientX;
                        }

                        if (event.changedTouches && event.changedTouches[0]) {
                            return event.changedTouches[0].clientX;
                        }

                        return event.clientX;
                    }

                    function clientY(event) {
                        if (event.touches && event.touches[0]) {
                            return event.touches[0].clientY;
                        }

                        if (event.changedTouches && event.changedTouches[0]) {
                            return event.changedTouches[0].clientY;
                        }

                        return event.clientY;
                    }

                    function layout() {
                        if (! viewport || ! track) {
                            return;
                        }

                        width = viewport.getBoundingClientRect().width || 1;
                        Array.prototype.forEach.call(track.children, function (slide) {
                            slide.style.flex = '0 0 ' + width + 'px';
                            slide.style.width = width + 'px';
                            slide.style.maxWidth = width + 'px';
                        });
                        update();
                    }

                    function update() {
                        if (! track) {
                            return;
                        }

                        track.style.transition = 'transform 0.5s ease-in-out';
                        var offset = (isRtl() ? page : -page) * width;
                        track.style.transform = 'translate3d(' + offset + 'px, 0, 0)';

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

                    function beginDrag(event) {
                        if (pages < 2 || ! track || ! viewport) {
                            return false;
                        }

                        if (interactiveTarget(event.target)) {
                            return false;
                        }

                        if (dragReset) {
                            clearTimeout(dragReset);
                            dragReset = null;
                        }

                        dragging = true;
                        didDrag = false;
                        startX = clientX(event);
                        startY = clientY(event);
                        deltaX = 0;
                        width = viewport.getBoundingClientRect().width || 1;

                        return true;
                    }

                    function moveDrag(event) {
                        if (! dragging) {
                            return;
                        }

                        deltaX = clientX(event) - startX;
                        var deltaY = clientY(event) - startY;

                        if (! didDrag && Math.abs(deltaX) < 12 && Math.abs(deltaY) < 12) {
                            return;
                        }

                        if (! didDrag && Math.abs(deltaY) > Math.abs(deltaX) + 6) {
                            dragging = false;
                            update();
                            return;
                        }

                        didDrag = true;

                        if (event.cancelable) {
                            event.preventDefault();
                        }

                        if (event.stopPropagation) {
                            event.stopPropagation();
                        }

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

                    function endDrag() {
                        if (! dragging && ! didDrag) {
                            return;
                        }

                        dragging = false;

                        var threshold = Math.max(36, width * 0.15);
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
                            dragReset = setTimeout(function () {
                                didDrag = false;
                                dragReset = null;
                            }, 400);
                        }
                    }

                    if (viewport && pages > 1) {
                        viewport.addEventListener('touchstart', function (event) {
                            beginDrag(event);
                        }, { passive: true });
                        viewport.addEventListener('touchmove', function (event) {
                            moveDrag(event);
                            if (didDrag) {
                                event.stopPropagation();
                            }
                        }, { passive: false, capture: true });
                        viewport.addEventListener('touchend', function (event) {
                            if (didDrag) {
                                event.stopPropagation();
                            }
                            endDrag();
                        }, { passive: true, capture: true });
                        viewport.addEventListener('touchcancel', endDrag, { passive: true });

                        viewport.addEventListener('pointerdown', function (event) {
                            if (event.pointerType === 'touch') {
                                return;
                            }

                            if (event.button !== 0) {
                                return;
                            }

                            if (! beginDrag(event)) {
                                return;
                            }

                            try {
                                viewport.setPointerCapture(event.pointerId);
                            } catch (error) {}
                        });
                        viewport.addEventListener('pointermove', function (event) {
                            if (event.pointerType === 'touch') {
                                return;
                            }

                            moveDrag(event);
                        });
                        viewport.addEventListener('pointerup', function (event) {
                            if (event.pointerType === 'touch') {
                                return;
                            }

                            endDrag();
                        });
                        viewport.addEventListener('pointercancel', function (event) {
                            if (event.pointerType === 'touch') {
                                return;
                            }

                            endDrag();
                        });

                        viewport.addEventListener('click', function (event) {
                            if (! didDrag) {
                                return;
                            }

                            event.preventDefault();
                            event.stopPropagation();
                        }, true);

                        window.addEventListener('resize', layout);
                    }

                    layout();
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
