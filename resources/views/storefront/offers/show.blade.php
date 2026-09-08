@extends('layouts.storefront')
@php
    $regularTotal = $offer->regularTotal();
    $offerTotal = $offer->offerTotal();
    $galleryPayload = $offer->galleryItems();
    $defaultImage = (string) ($galleryPayload->first()['image'] ?? $offer->imageUrl());
    $canBuy = $offer->isPurchasable();
    $maxStock = $offer->availableQuantity();
    $seoDescription = $offer->localized('description')
        ? Str::limit(strip_tags((string) $offer->localized('description')), 160)
        : 'Buy this set together at a better price.';
@endphp
@section('title', $offer->localized('title').' — '.config('aura.name'))
@section('meta_description', $seoDescription)
@section('og_title', $offer->localized('title'))
@section('og_type', 'product')
@section('og_image', $defaultImage)
@section('canonical', route('offers.show', $offer->slug))
@section('content')
<div
    class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-10"
    x-data="{
        gallery: @js($galleryPayload),
        galleryId: @js((string) ($galleryPayload->first()['id'] ?? 'main')),
        get activeGalleryItem() {
            return this.gallery.find((item) => item.id === String(this.galleryId)) || this.gallery[0] || null;
        },
        get activeImage() {
            return this.activeGalleryItem?.image || @js($defaultImage);
        },
        selectGallery(id) {
            const item = this.gallery.find((entry) => entry.id === String(id));
            if (! item) return;
            this.galleryId = String(item.id);
            this.$nextTick(() => this.scrollPreviewIntoView());
        },
        scrollPreviewIntoView() {
            const preview = this.$refs.variantPreview;
            if (! preview) return;
            const active = preview.querySelector('[aria-selected=true]');
            active?.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
        }
    }"
>
    <div class="grid lg:grid-cols-2 gap-6 lg:gap-10">
        <div class="w-full min-w-0 space-y-3">
            <div class="flex min-h-[280px] w-full items-center justify-center bg-beige/40 sm:min-h-[360px]">
                <img
                    src="{{ $defaultImage }}"
                    :src="activeImage"
                    alt="{{ $offer->localized('title') }}"
                    class="mx-auto block h-auto max-h-[75vh] w-auto max-w-full object-contain"
                    decoding="async"
                >
            </div>

            <div
                x-show="gallery.length > 1"
                x-cloak
                class="w-full min-w-0"
            >
                <div
                    x-ref="variantPreview"
                    class="product-gallery-scroll flex w-full gap-2 overflow-x-auto overscroll-x-contain touch-pan-x snap-x snap-mandatory pb-1"
                    role="listbox"
                    aria-label="{{ __('storefront.offer_images') }}"
                >
                    <template x-for="item in gallery" :key="item.id">
                        <button
                            type="button"
                            role="option"
                            class="group relative shrink-0 snap-start overflow-hidden border bg-beige/30 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-gold"
                            :class="galleryId === item.id
                                ? 'border-charcoal ring-1 ring-charcoal'
                                : 'border-beige hover:border-gold'"
                            :aria-selected="galleryId === item.id"
                            :aria-label="item.label"
                            :title="item.label"
                            @click="selectGallery(item.id)"
                        >
                            <span class="block h-16 w-16 sm:h-20 sm:w-20">
                                <img
                                    :src="item.image"
                                    :alt="item.label"
                                    class="h-full w-full object-cover"
                                    loading="lazy"
                                    decoding="async"
                                >
                            </span>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <div class="w-full min-w-0">
            <div class="text-xs uppercase tracking-[0.18em] text-blush mb-2">{{ __('storefront.hot_offer_badge') }}</div>
            <h1 class="font-display text-4xl sm:text-5xl mb-3">{{ $offer->localized('title') }}</h1>
            <p class="text-xs uppercase tracking-[0.16em] text-taupe mb-3">{{ __('storefront.offer_pieces_in', ['count' => $offer->includedUnitCount(), 'pieces' => trans_choice('storefront.piece', $offer->includedUnitCount())]) }}</p>

            <div class="mb-4 flex flex-wrap items-baseline gap-3">
                <p class="text-xl">{{ money($offerTotal) }}</p>
                @if($regularTotal > $offerTotal)
                    <p class="text-sm text-taupe line-through">{{ money($regularTotal) }}</p>
                @endif
            </div>

            @if($offer->description)
                <p class="text-taupe mb-6">{{ $offer->description }}</p>
            @else
                <p class="text-taupe mb-6">{{ __('storefront.offer_add_desc') }}</p>
            @endif

            <p class="text-sm mb-6">
                <x-badge>{{ $canBuy ? __('storefront.in_stock') : __('storefront.out_of_stock') }}</x-badge>
            </p>

            <div class="space-y-5 max-w-lg">
                <div>
                    <div class="label mb-3">{{ __('storefront.included') }}</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach($offer->products as $product)
                            @php
                                $productImage = app(\App\Services\ImageService::class)->url($product->primaryImagePath());
                                $qty = $offer->lineQuantity($product);
                            @endphp
                            <div class="text-start border border-beige bg-[#FFFCFA] p-3">
                                <div class="flex gap-3">
                                    <div class="h-14 w-14 shrink-0 overflow-hidden border border-beige bg-beige/30">
                                        <img src="{{ $productImage }}" alt="{{ $product->localized('name') }}" class="h-full w-full object-cover">
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ route('products.show', $product->slug) }}" class="font-medium leading-snug block hover:text-gold transition">{{ $product->localized('name') }}</a>
                                        <div class="text-xs text-taupe mt-0.5">{{ __('storefront.offer_qty_included', ['qty' => $qty]) }}</div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-2 text-xs text-taupe">{{ __('storefront.offer_price_note') }}</p>
                </div>

                <form method="POST" action="{{ route('offers.cart', $offer->slug) }}" class="space-y-5">
                    @csrf
                    <div class="max-w-sm">
                        <label class="label" for="quantity">{{ __('storefront.quantity') }}</label>
                        <input
                            id="quantity"
                            type="number"
                            name="quantity"
                            value="1"
                            min="1"
                            @if($canBuy) max="{{ max(1, $maxStock) }}" @endif
                            class="input"
                            @disabled(! $canBuy)
                        >
                    </div>

                    <button
                        class="btn btn-primary w-full max-w-sm"
                        type="submit"
                        @disabled(! $canBuy)
                    >
                        {{ __('storefront.add_to_bag') }}
                    </button>
                    <p class="text-xs text-taupe leading-snug max-w-sm">{{ __('storefront.offer_bag_note') }}</p>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
