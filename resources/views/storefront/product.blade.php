@extends('layouts.storefront')
@php
    $images = app(\App\Services\ImageService::class);
    $firstVariant = $product->activeVariants->first();
    $defaultImage = $product->has_variants
        ? $images->url($firstVariant?->image_path)
        : $images->url($product->primaryImagePath());
    $seoTitle = $product->meta_title ?: ($product->name.' — '.config('aura.name'));
    $seoDescription = $product->meta_description
        ?: ($product->short_description ?: Str::limit(strip_tags((string) $product->description), 160));
@endphp
@section('title', $seoTitle)
@section('meta_description', $seoDescription)
@section('og_title', $seoTitle)
@section('og_type', 'product')
@section('og_image', $defaultImage)
@section('canonical', route('products.show', $product->slug))
@section('content')
@php
    $variantsPayload = $product->activeVariants->map(function ($variant) use ($images, $product) {
        $image = $variant->image_path
            ? $images->url($variant->image_path)
            : $images->url(null);

        return [
            'id' => (string) $variant->id,
            'name' => $variant->displayName(),
            'price' => (float) $product->effectivePrice($variant),
            'priceLabel' => money($product->effectivePrice($variant)),
            'compareAt' => $product->compareAtPrice($variant) !== null
                ? money($product->compareAtPrice($variant))
                : null,
            'stock' => $variant->availableStock(),
            'threshold' => (int) $variant->low_stock_threshold,
            'image' => $image,
            'purchasable' => ! $product->track_inventory || $variant->availableStock() > 0,
        ];
    })->values();

    $galleryPayload = $product->has_variants && $product->activeVariants->isNotEmpty()
        ? $product->activeVariants->map(function ($variant) use ($images, $product) {
            return [
                'id' => 'variant-'.$variant->id,
                'variantId' => (string) $variant->id,
                'image' => $variant->image_path ? $images->url($variant->image_path) : $images->url(null),
                'label' => $variant->displayName(),
                'purchasable' => ! $product->track_inventory || $variant->availableStock() > 0,
            ];
        })->values()
        : $product->images->map(function ($image, $index) use ($images, $product) {
            return [
                'id' => 'image-'.$image->id,
                'variantId' => null,
                'image' => $images->url($image->path),
                'label' => $image->alt ?: ($product->name.' photo '.($index + 1)),
                'purchasable' => true,
            ];
        })->values();

    if ($galleryPayload->isEmpty()) {
        $galleryPayload = collect([[
            'id' => 'fallback',
            'variantId' => $product->has_variants ? (string) ($firstVariant?->id ?? '') : null,
            'image' => $defaultImage,
            'label' => $product->name,
            'purchasable' => true,
        ]]);
    }

    $initialVariantId = (string) ($firstVariant?->id ?? '');
    $initialGalleryId = (string) ($galleryPayload->first()['id'] ?? 'fallback');
    $currencySymbol = config('aura.currency_symbol', '$');
@endphp

<div
    class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-10"
    x-data="{
        productId: @js((string) $product->id),
        productName: @js($product->name),
        productPrice: @js((float) $product->effectivePrice()),
        productPriceLabel: @js(money($product->effectivePrice())),
        productCompareAt: @js($product->compareAtPrice() ? money($product->compareAtPrice()) : null),
        productStock: @js($product->availableStock()),
        productThreshold: @js((int) $product->low_stock_threshold),
        productImage: @js($defaultImage),
        productPurchasable: @js($product->isPurchasable()),
        trackInventory: @js((bool) $product->track_inventory),
        currencySymbol: @js($currencySymbol),
        cartStoreUrl: @js(route('cart.store')),
        cartBatchUrl: @js(route('cart.batch')),
        cartIndexUrl: @js(route('cart.index')),
        csrf: @js(csrf_token()),
        variants: @js($variantsPayload),
        gallery: @js($galleryPayload),
        variantId: @js($initialVariantId),
        galleryId: @js($initialGalleryId),
        quantity: 1,
        staged: [],
        stageError: '',
        confirmError: '',
        confirming: false,
        get selected() {
            return this.variants.find((item) => item.id === String(this.variantId)) || this.variants[0] || null;
        },
        get activeGalleryItem() {
            return this.gallery.find((item) => item.id === String(this.galleryId)) || this.gallery[0] || null;
        },
        get activeImage() {
            return this.activeGalleryItem?.image
                || this.selected?.image
                || this.productImage;
        },
        get hasVariants() {
            return this.variants.length > 0;
        },
        get canBuy() {
            if (! this.hasVariants) {
                return this.productPurchasable;
            }
            return !!(this.selected && this.selected.purchasable);
        },
        get maxStock() {
            if (! this.trackInventory) {
                return 9999;
            }
            if (this.hasVariants) {
                return Math.max(0, Number(this.selected?.stock || 0));
            }
            return Math.max(0, Number(this.productStock || 0));
        },
        get stockThreshold() {
            if (this.hasVariants) {
                return Math.max(0, Number(this.selected?.threshold ?? this.productThreshold ?? 0));
            }
            return Math.max(0, Number(this.productThreshold || 0));
        },
        get showStockAmount() {
            if (! this.trackInventory) {
                return false;
            }
            const stock = this.maxStock;
            return stock > 0 && stock <= this.stockThreshold;
        },
        stockIsLow(stock, threshold) {
            const available = Math.max(0, Number(stock || 0));
            const limit = Math.max(0, Number(threshold || 0));
            return available > 0 && available <= limit;
        },
        get stagedCount() {
            return this.staged.reduce((sum, item) => sum + Number(item.quantity || 0), 0);
        },
        formatMoney(amount) {
            const value = Number(amount || 0);
            return this.currencySymbol + value.toFixed(2);
        },
        lineTotal(item) {
            return Number(item.unitPrice || 0) * Number(item.quantity || 0);
        },
        selectGallery(id) {
            const item = this.gallery.find((entry) => entry.id === String(id));
            if (! item) return;
            this.galleryId = String(item.id);
            if (item.variantId) {
                this.variantId = String(item.variantId);
                this.stageError = '';
            }
            this.$nextTick(() => this.scrollPreviewIntoView());
        },
        selectVariant(id) {
            this.variantId = String(id);
            this.stageError = '';
            const match = this.gallery.find((item) => String(item.variantId) === String(id));
            if (match) {
                this.galleryId = String(match.id);
            }
            this.$nextTick(() => this.scrollPreviewIntoView());
        },
        scrollPreviewIntoView() {
            const preview = this.$refs.variantPreview;
            if (! preview) return;
            const active = preview.querySelector('[aria-selected=true]');
            active?.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
        },
        stagedKey(variantId) {
            return variantId ? String(variantId) : 'product';
        },
        addToStaged() {
            this.stageError = '';
            if (! this.canBuy) {
                this.stageError = 'This item is out of stock.';
                return;
            }

            let qty = Math.floor(Number(this.quantity) || 0);
            if (qty < 1) {
                this.stageError = 'Quantity must be at least 1.';
                return;
            }

            const stock = this.maxStock;
            if (stock < 1) {
                this.stageError = 'This item is out of stock.';
                return;
            }

            const variantId = this.hasVariants ? String(this.variantId) : null;
            const key = this.stagedKey(variantId);
            const existing = this.staged.find((item) => item.key === key);
            const already = existing ? Number(existing.quantity) : 0;
            const nextQty = Math.min(stock, already + qty);

            if (nextQty <= already) {
                this.stageError = 'No more stock available for this option.';
                return;
            }

            const name = this.hasVariants
                ? (this.selected?.name || 'Option')
                : this.productName;
            const unitPrice = this.hasVariants
                ? Number(this.selected?.price || 0)
                : Number(this.productPrice || 0);
            const priceLabel = this.hasVariants
                ? (this.selected?.priceLabel || this.formatMoney(unitPrice))
                : this.productPriceLabel;
            const image = this.hasVariants
                ? (this.selected?.image || this.activeImage || this.productImage)
                : (this.activeImage || this.productImage);

            if (existing) {
                existing.quantity = nextQty;
            } else {
                this.staged.push({
                    key,
                    variantId,
                    name,
                    quantity: nextQty,
                    unitPrice,
                    priceLabel,
                    image,
                });
            }

            this.quantity = 1;
        },
        removeStaged(index) {
            this.staged.splice(index, 1);
            this.confirmError = '';
        },
        async confirmToBag() {
            if (! this.staged.length || this.confirming) {
                return;
            }

            this.confirming = true;
            this.confirmError = '';

            try {
                const body = new FormData();
                body.append('_token', this.csrf);
                body.append('product_id', this.productId);

                this.staged.forEach((item, index) => {
                    if (item.variantId) {
                        body.append(`items[${index}][product_variant_id]`, item.variantId);
                    }
                    body.append(`items[${index}][quantity]`, String(item.quantity));
                });

                const response = await fetch(this.cartBatchUrl, {
                    method: 'POST',
                    body,
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                let payload = null;
                try {
                    payload = await response.json();
                } catch (e) {
                    payload = null;
                }

                if (! response.ok || payload?.ok === false) {
                    const message = payload?.message
                        || payload?.errors?.items?.[0]
                        || payload?.errors?.quantity?.[0]
                        || 'Could not add to bag. Please try again.';
                    throw new Error(message);
                }

                this.staged = [];
                window.location.href = payload?.redirect || this.cartIndexUrl;
            } catch (error) {
                this.confirmError = error?.message || 'Could not add to bag. Please try again.';
                this.confirming = false;
            }
        },
        wishlistSaving: false,
        wishlistSaved: false,
        async saveWishlist() {
            if (this.wishlistSaving || this.wishlistSaved) return;
            this.wishlistSaving = true;
            try {
                const body = new FormData();
                body.append('product_id', this.productId);
                if (this.hasVariants && this.variantId) {
                    body.append('product_variant_id', this.variantId);
                }
                const data = await window.auraHttp(@js(route('wishlist.store')), {
                    method: 'POST',
                    body,
                });
                this.wishlistSaved = true;
                window.dispatchEvent(new CustomEvent('aura:toast', {
                    detail: { message: data.message || 'Saved to wishlist.', type: 'success' },
                }));
            } catch (error) {
                window.dispatchEvent(new CustomEvent('aura:toast', {
                    detail: { message: error?.message || 'Could not save to wishlist.', type: 'error' },
                }));
            } finally {
                this.wishlistSaving = false;
            }
        }
    }"
>
    <div class="grid lg:grid-cols-2 gap-6 lg:gap-10">
        <div class="w-full min-w-0 space-y-3">
            <div class="flex min-h-[280px] w-full items-center justify-center bg-beige/40 sm:min-h-[360px]">
                <img
                    :src="activeImage"
                    alt="{{ $product->name }}"
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
                    aria-label="Image preview"
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
                                    :class="item.purchasable === false ? 'opacity-40 grayscale' : ''"
                                    loading="lazy"
                                    decoding="async"
                                >
                            </span>
                            <span
                                x-show="item.purchasable === false"
                                x-cloak
                                class="absolute inset-x-0 bottom-0 bg-charcoal/70 px-1 py-0.5 text-center text-[10px] leading-tight text-ivory"
                            >Sold out</span>
                        </button>
                    </template>
                </div>
            </div>
        </div>
        <div class="w-full min-w-0">
            @if($product->brand)
                <div class="text-xs uppercase tracking-[0.18em] text-taupe mb-2">{{ $product->brand->name }}</div>
            @endif
            <h1 class="font-display text-4xl sm:text-5xl mb-3">{{ $product->name }}</h1>
            @if($product->gender)
                <p class="text-xs uppercase tracking-[0.16em] text-taupe mb-3">{{ $product->gender->label() }}</p>
            @endif
            @if($product->sizeLabel() && ! $product->has_variants)
                <p class="text-sm text-taupe mb-3">Size: {{ $product->sizeLabel() }}</p>
            @endif

            <div class="mb-4 flex flex-wrap items-baseline gap-3">
                <p class="text-xl" x-text="selected?.priceLabel || productPriceLabel"></p>
                <p
                    class="text-sm text-taupe line-through"
                    x-show="selected?.compareAt || (!hasVariants && productCompareAt)"
                    x-cloak
                    x-text="selected?.compareAt || productCompareAt"
                ></p>
                @if($product->hasActiveOffer())
                    <a href="{{ route('offers.index') }}" class="text-[11px] uppercase tracking-[0.14em] text-blush">In a hot offer</a>
                @endif
            </div>

            @if(($productOffers ?? collect())->isNotEmpty())
                <div class="mb-6 border border-beige bg-[#FFFCFA] p-4">
                    <div class="text-[11px] uppercase tracking-[0.16em] text-blush">Sold as a set</div>
                    <p class="mt-1 text-sm">
                        Buy this product together with the rest of
                        @foreach($productOffers as $offer)
                            <a class="underline" href="{{ route('offers.show', $offer->slug) }}">{{ $offer->title }}</a>@if(! $loop->last)<span class="text-taupe"> or </span>@endif
                        @endforeach
                        to get the offer price. Buying it on its own uses the regular price.
                    </p>
                    <a href="{{ route('offers.show', $productOffers->first()->slug) }}" class="mt-2 inline-block text-xs text-taupe">View the full offer</a>
                </div>
            @endif

            <p class="text-taupe mb-6">{{ $product->short_description }}</p>

            <p class="text-sm mb-6">
                <x-badge>
                    <span x-text="!canBuy ? 'Out of stock' : (showStockAmount ? 'Low stock' : 'In stock')"></span>
                </x-badge>
                <span class="text-taupe" x-show="showStockAmount" x-cloak>
                    · Only
                    <span x-text="maxStock"></span>
                    left
                </span>
            </p>

            <div class="space-y-5 max-w-lg">
                @if($product->has_variants && $product->activeVariants->isNotEmpty())
                    <div>
                        <div class="flex items-end justify-between gap-3 mb-3">
                            <label class="label mb-0">Choose an option</label>
                            <p class="text-xs text-taupe" x-show="selected" x-cloak>
                                Selected: <span class="text-charcoal" x-text="selected?.name"></span>
                            </p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" role="listbox" aria-label="Product options">
                            <template x-for="variant in variants" :key="variant.id">
                                <button
                                    type="button"
                                    role="option"
                                    class="text-start border p-3 transition"
                                    :class="variantId === variant.id
                                        ? 'border-charcoal bg-ivory'
                                        : 'border-beige bg-[#FFFCFA] hover:border-gold'"
                                    :aria-selected="variantId === variant.id"
                                    :disabled="!variant.purchasable"
                                    @click="selectVariant(variant.id)"
                                >
                                    <div class="flex gap-3">
                                        <div class="h-14 w-14 shrink-0 overflow-hidden border border-beige bg-beige/30">
                                            <img :src="variant.image" alt="" class="h-full w-full object-cover">
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="font-medium leading-snug" x-text="variant.name"></div>
                                            <div class="mt-1 text-sm" x-text="variant.priceLabel"></div>
                                            <div class="mt-1 text-xs text-taupe">
                                                <span
                                                    x-show="variant.purchasable && stockIsLow(variant.stock, variant.threshold)"
                                                    x-cloak
                                                    x-text="'Only ' + variant.stock + ' left'"
                                                ></span>
                                                <span x-show="!variant.purchasable" x-cloak>Out of stock</span>
                                            </div>
                                        </div>
                                    </div>
                                </button>
                            </template>
                        </div>
                    </div>
                @endif

                <div class="max-w-sm">
                    <label class="label" for="quantity">Quantity</label>
                    <input
                        id="quantity"
                        type="number"
                        x-model.number="quantity"
                        min="1"
                        :max="Math.max(1, maxStock)"
                        class="input"
                    >
                </div>

                <p x-show="stageError" x-cloak class="text-sm text-blush" x-text="stageError"></p>

                <button
                    class="btn btn-primary w-full max-w-sm"
                    type="button"
                    :disabled="!canBuy"
                    @click="addToStaged()"
                >
                    Add to bag
                </button>

                <div
                    x-show="staged.length > 0"
                    x-cloak
                    class="max-w-sm border border-beige bg-[#FFFCFA] p-4 space-y-4"
                >
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="font-display text-xl">Your selection</h2>
                        <span class="text-xs uppercase tracking-widest text-taupe" x-text="stagedCount + ' item' + (stagedCount === 1 ? '' : 's')"></span>
                    </div>

                    <ul class="space-y-3">
                        <template x-for="(item, index) in staged" :key="item.key">
                            <li class="flex gap-3 border-b border-beige pb-3 last:border-0 last:pb-0">
                                <div class="h-14 w-14 shrink-0 overflow-hidden border border-beige bg-beige/30">
                                    <img :src="item.image" alt="" class="h-full w-full object-cover">
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="font-medium leading-snug" x-text="item.name"></div>
                                    <div class="mt-1 text-sm text-taupe">
                                        Qty <span x-text="item.quantity"></span>
                                        · <span x-text="formatMoney(lineTotal(item))"></span>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="shrink-0 h-8 w-8 flex items-center justify-center text-taupe hover:text-charcoal transition"
                                    aria-label="Remove from selection"
                                    @click="removeStaged(index)"
                                >
                                    <span aria-hidden="true" class="text-xl leading-none">&times;</span>
                                </button>
                            </li>
                        </template>
                    </ul>

                    <p x-show="confirmError" x-cloak class="text-sm text-blush" x-text="confirmError"></p>

                    <button
                        class="btn btn-gold w-full"
                        type="button"
                        :disabled="confirming || staged.length === 0"
                        @click="confirmToBag()"
                    >
                        <span x-show="!confirming">Confirm to bag</span>
                        <span x-show="confirming" x-cloak>Adding…</span>
                    </button>
                    <p class="text-xs text-taupe leading-snug">
                        Items stay on this page until you confirm. Confirm sends them to your real bag.
                    </p>
                </div>
            </div>

            @auth
                <div class="mt-3 max-w-sm">
                    <button
                        class="btn btn-secondary w-full"
                        type="button"
                        :disabled="wishlistSaving || wishlistSaved"
                        @click="saveWishlist()"
                    >
                        <span x-show="!wishlistSaving && !wishlistSaved">Save to wishlist</span>
                        <span x-show="wishlistSaving" x-cloak>Saving…</span>
                        <span x-show="wishlistSaved && !wishlistSaving" x-cloak>Saved to wishlist</span>
                    </button>
                </div>
            @endauth

            <div class="mt-10 space-y-6 text-sm leading-relaxed">
                @if($product->description)
                    <div><h2 class="font-display text-2xl mb-2">Details</h2><div class="text-taupe whitespace-pre-line">{{ $product->description }}</div></div>
                @endif
                @if($product->ingredients)
                    <div><h2 class="font-display text-2xl mb-2">Ingredients</h2><div class="text-taupe whitespace-pre-line">{{ $product->ingredients }}</div></div>
                @endif
                @if($product->how_to_use)
                    <div><h2 class="font-display text-2xl mb-2">How to use</h2><div class="text-taupe whitespace-pre-line">{{ $product->how_to_use }}</div></div>
                @endif
            </div>
        </div>
    </div>

    @if(($offerProducts ?? collect())->isNotEmpty())
        <section class="mt-20">
            <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
                <h2 class="font-display text-3xl">Also in this set</h2>
                @if(($productOffers ?? collect())->isNotEmpty())
                    <a href="{{ route('offers.show', $productOffers->first()->slug) }}" class="text-sm text-taupe">View offer</a>
                @endif
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-5">
                @foreach($offerProducts as $item)
                    <x-product-card :product="$item" />
                @endforeach
            </div>
        </section>
    @endif

    @if($related->isNotEmpty())
        <section class="mt-20">
            <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
                <h2 class="font-display text-3xl">You may also like</h2>
                <p class="text-sm text-taupe">Ranked by how closely each product matches this one</p>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-5">
                @foreach($related as $item)
                    <x-product-card
                        :product="$item"
                        :match-reason="$item->match_reason ?? null"
                    />
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
