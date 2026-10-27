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
    $canSlide = $pages->count() > 1;
@endphp

<section {{ $attributes->class('max-w-7xl mx-auto px-4 sm:px-6 py-16') }} x-data="productRail({ pages: {{ $pages->count() }} })">
    <div class="mb-8 flex items-end justify-between gap-3">
        <h2 class="font-display text-4xl">{{ $title }}</h2>
        <div class="flex shrink-0 items-center gap-2">
            @if($canSlide)
                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center border border-beige bg-[#FFFCFA] text-charcoal transition hover:border-charcoal disabled:cursor-not-allowed disabled:opacity-30 lg:hidden"
                    @click="prev()"
                    :disabled="!canPrev"
                    aria-label="{{ __('storefront.previous_products') }}"
                >
                    <x-icon name="chevron-left" class="h-5 w-5 rtl:rotate-180" />
                </button>
                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center border border-beige bg-[#FFFCFA] text-charcoal transition hover:border-charcoal disabled:cursor-not-allowed disabled:opacity-30 lg:hidden"
                    @click="next()"
                    :disabled="!canNext"
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
                    class="hidden h-12 w-12 shrink-0 items-center justify-center border border-beige bg-[#FFFCFA] text-charcoal transition hover:border-gold disabled:cursor-not-allowed disabled:opacity-30 lg:inline-flex"
                    data-rail-side-arrow
                    @click="prev()"
                    :disabled="!canPrev"
                    aria-label="{{ __('storefront.previous_products') }}"
                >
                    <x-icon name="chevron-left" class="h-6 w-6 rtl:rotate-180" />
                </button>
            @endif

            <div class="min-w-0 flex-1 overflow-hidden">
                <div
                    class="flex will-change-transform"
                    data-product-rail
                    :style="`transform: translate3d(${trackOffset}%, 0, 0); transition: transform 0.5s ease-in-out;`"
                >
                    @foreach($pages as $page)
                        <div class="grid w-full min-w-full shrink-0 grid-cols-2 items-stretch gap-5 md:grid-cols-4">
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
                    class="hidden h-12 w-12 shrink-0 items-center justify-center border border-beige bg-[#FFFCFA] text-charcoal transition hover:border-gold disabled:cursor-not-allowed disabled:opacity-30 lg:inline-flex"
                    data-rail-side-arrow
                    @click="next()"
                    :disabled="!canNext"
                    aria-label="{{ __('storefront.next_products') }}"
                >
                    <x-icon name="chevron-right" class="h-6 w-6 rtl:rotate-180" />
                </button>
            @endif
        </div>
    @endif
</section>
