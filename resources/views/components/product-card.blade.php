@props(['product', 'matchReason' => null, 'bundlePrice' => null, 'inWishlist' => null])
@php
    $images = app(\App\Services\ImageService::class);
    $imgUrl = $images->url($product->primaryImagePath());
    $reason = $matchReason ?? ($product->match_reason ?? null);
    $inBundle = $bundlePrice !== null;
    $displayPrice = $inBundle ? (float) $bundlePrice : $product->regularPrice();
    $comparePrice = $inBundle ? $product->regularPrice() : $product->compareAtPrice();
    $quickVariant = $product->defaultVariantForCart();
    $canBuy = $product->isPurchasable();
    $saved = $inWishlist ?? in_array((int) $product->id, $wishlistProductIds ?? [], true);

    $variantCards = $product->has_variants
        ? $product->activeVariants->values()
        : collect();

    $variantsPayload = $variantCards->map(function ($variant) use ($images, $product, $imgUrl) {
        return [
            'id' => (int) $variant->id,
            'name' => $variant->displayName(),
            'image' => filled($variant->primaryImagePath())
                ? $images->url($variant->primaryImagePath())
                : $imgUrl,
            'priceLabel' => money($product->regularPrice($variant)),
            'compareAt' => $product->compareAtPrice($variant) !== null
                && (float) $product->compareAtPrice($variant) > (float) $product->regularPrice($variant)
                ? money($product->compareAtPrice($variant))
                : null,
            'purchasable' => ! $product->track_inventory || $variant->availableStock() > 0,
            'stock' => $product->track_inventory ? $variant->availableStock() : null,
            'stockLabel' => (! $product->track_inventory || $variant->availableStock() > 0)
                ? __('storefront.in_stock')
                : __('storefront.out_of_stock'),
        ];
    })->all();

    $variantIndex = 0;
    if ($quickVariant) {
        foreach ($variantsPayload as $index => $variant) {
            if ((int) $variant['id'] === (int) $quickVariant->id) {
                $variantIndex = $index;
                break;
            }
        }
    }
@endphp
<article
    class="product-card group"
    x-data="productCard({
        productId: {{ (int) $product->id }},
        variants: {{ \Illuminate\Support\Js::from($variantsPayload) }},
        variantIndex: {{ (int) $variantIndex }},
        fallbackImage: {{ \Illuminate\Support\Js::from($imgUrl) }},
        basePriceLabel: {{ \Illuminate\Support\Js::from(money($displayPrice)) }},
        baseCompareAt: {{ \Illuminate\Support\Js::from(($comparePrice && (float) $comparePrice > (float) $displayPrice) ? money($comparePrice) : null) }},
        baseStockLabel: {{ \Illuminate\Support\Js::from($reason ?: ($product->stock_status?->label() ?? '')) }},
        baseCanBuy: {{ $canBuy ? 'true' : 'false' }},
        baseMaxQty: {{ $product->track_inventory ? (int) $product->availableStock() : 'null' }},
        baseVariantId: {{ $quickVariant?->id ? (int) $quickVariant->id : 'null' }},
        authenticated: {{ auth()->check() ? 'true' : 'false' }},
        wished: {{ $saved ? 'true' : 'false' }},
        cartUrl: {{ \Illuminate\Support\Js::from(route('cart.store')) }},
        wishlistUrl: {{ \Illuminate\Support\Js::from(route('wishlist.toggle')) }},
        loginUrl: {{ \Illuminate\Support\Js::from(route('login')) }},
    })"
>
    <div
        class="relative product-card-media"
        @touchstart.passive="onTouchStart($event)"
        @touchend.passive="onTouchEnd($event)"
    >
        <a href="{{ route('products.show', $product->slug) }}" class="relative block overflow-hidden bg-beige/40 aspect-[4/5]">
            <img
                :key="slideKey"
                :src="activeImage"
                alt="{{ $product->localized('name') }}"
                class="product-card-slide w-full h-full object-cover"
                :class="slideDirection === 1 ? 'is-next' : 'is-prev'"
            >
            @if($inBundle || $product->hasActiveOffer())
                <span class="absolute top-3 start-3 z-[1] bg-blush text-[#FFFCFA] text-[10px] uppercase tracking-[0.14em] px-2 py-1">{{ __('storefront.hot_offer_badge') }}</span>
            @endif
        </a>
        <template x-if="hasCarousel">
            <div>
                <button
                    type="button"
                    class="product-card-variant-nav start-2"
                    aria-label="{{ __('storefront.previous_option') }}"
                    @click.stop.prevent="prevVariant()"
                >
                    <x-icon name="chevron-left" class="h-4 w-4" />
                </button>
                <button
                    type="button"
                    class="product-card-variant-nav end-2"
                    aria-label="{{ __('storefront.next_option') }}"
                    @click.stop.prevent="nextVariant()"
                >
                    <x-icon name="chevron-right" class="h-4 w-4" />
                </button>
                <div class="product-card-variant-dots" aria-hidden="true">
                    <template x-for="(variant, index) in variants" :key="variant.id">
                        <span class="product-card-variant-dot" :class="variantIndex === index ? 'is-active' : ''"></span>
                    </template>
                </div>
            </div>
        </template>
        <button
            type="button"
            class="product-card-wish"
            :class="wished ? 'is-saved' : ''"
            aria-label="{{ __('storefront.add_to_wishlist') }}"
            :aria-label="wished ? @js(__('storefront.remove_from_wishlist')) : @js(__('storefront.add_to_wishlist'))"
            :disabled="wishing"
            @click.stop.prevent="toggleWishlist()"
        >
            <x-icon name="wishlist" class="h-4 w-4" />
        </button>
    </div>
    <div class="product-card-body pt-3 space-y-1">
        @if($product->brand)
            <div class="text-[11px] uppercase tracking-[0.16em] text-taupe">{{ $product->brand->localized('name') }}</div>
        @endif
        @if($product->gender)
            <div class="text-[11px] uppercase tracking-[0.14em] text-taupe">{{ $product->gender->label() }}</div>
        @endif
        <a href="{{ route('products.show', $product->slug) }}" class="font-display text-xl leading-tight block">{{ $product->localized('name') }}</a>
        <div class="product-card-variant-name text-xs text-taupe" x-text="currentVariant?.name || ' '"></div>
        <div class="flex items-baseline gap-2 text-sm">
            <span x-text="priceLabel">{{ money($displayPrice) }}</span>
            <span
                class="text-taupe line-through text-xs"
                x-show="compareAt"
                x-cloak
                x-text="compareAt"
            >@if($comparePrice && (float) $comparePrice > (float) $displayPrice){{ money($comparePrice) }}@endif</span>
            @if($product->hasActiveOffer() && ! $inBundle)
                <a href="{{ route('offers.index') }}" class="text-[10px] uppercase tracking-[0.14em] text-blush">{{ __('storefront.in_a_set') }}</a>
            @endif
        </div>
        @if($reason)
            <div class="text-xs text-taupe capitalize">{{ $reason }}</div>
        @else
            <div class="text-xs text-taupe" x-text="stockLabel">{{ $product->stock_status?->label() }}</div>
        @endif
    </div>
    <div class="product-card-actions">
            <input
                class="input product-card-qty"
                type="number"
                min="1"
                step="1"
                value="1"
                inputmode="numeric"
                aria-label="{{ __('storefront.quantity') }}"
                x-model.number="quantity"
                :max="maxQty || null"
                :disabled="!canBuy || adding"
                @click.stop
                @change="normalizeQty()"
            >
            <button
                type="button"
                class="btn btn-primary btn-sm"
                :disabled="!canBuy || adding"
                @click.stop.prevent="addToBag()"
            >
                <span x-show="!adding && canBuy">{{ __('storefront.add_to_bag') }}</span>
                <span x-show="!adding && !canBuy" x-cloak>{{ __('storefront.sold_out') }}</span>
                <span x-show="adding" x-cloak>{{ __('storefront.adding') }}</span>
            </button>
    </div>
</article>
