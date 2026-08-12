@extends('layouts.storefront')
@section('title', $product->name.' — '.config('aura.name'))
@section('content')
@php
    $images = app(\App\Services\ImageService::class);
    $firstVariant = $product->activeVariants->first();
    $defaultImage = $product->has_variants
        ? $images->url($firstVariant?->image_path)
        : $images->url($product->primaryImagePath());

    $variantsPayload = $product->activeVariants->map(function ($variant) use ($images, $product) {
        $image = $variant->image_path
            ? $images->url($variant->image_path)
            : $images->url(null);

        return [
            'id' => (string) $variant->id,
            'name' => $variant->displayName(),
            'price' => (float) $variant->effectivePrice(),
            'priceLabel' => money($variant->effectivePrice()),
            'compareAt' => $variant->compare_at_price !== null
                ? money((float) $variant->compare_at_price)
                : ($product->compare_at_price !== null ? money((float) $product->compare_at_price) : null),
            'stock' => $variant->availableStock(),
            'image' => $image,
            'purchasable' => ! $product->track_inventory || $variant->availableStock() > 0,
        ];
    })->values();

    $initialVariantId = (string) ($firstVariant?->id ?? '');
    $currencySymbol = config('aura.currency_symbol', '$');
@endphp

<div
    class="max-w-7xl mx-auto px-4 sm:px-6 py-10"
    x-data="{
        productId: @js((string) $product->id),
        productName: @js($product->name),
        productPrice: @js((float) $product->effectivePrice()),
        productPriceLabel: @js(money($product->effectivePrice())),
        productStock: @js($product->availableStock()),
        productImage: @js($defaultImage),
        productPurchasable: @js($product->isPurchasable()),
        trackInventory: @js((bool) $product->track_inventory),
        currencySymbol: @js($currencySymbol),
        cartStoreUrl: @js(route('cart.store')),
        cartIndexUrl: @js(route('cart.index')),
        csrf: @js(csrf_token()),
        variants: @js($variantsPayload),
        variantId: @js($initialVariantId),
        quantity: 1,
        staged: [],
        stageError: '',
        confirmError: '',
        confirming: false,
        get selected() {
            return this.variants.find((item) => item.id === String(this.variantId)) || this.variants[0] || null;
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
        selectVariant(id) {
            this.variantId = String(id);
            this.stageError = '';
            this.$nextTick(() => {
                const preview = this.$refs.variantPreview;
                if (! preview) return;
                const active = preview.querySelector('[aria-selected=true]');
                active?.scrollIntoView({ behavior: 'smooth', inline: 'nearest', block: 'nearest' });
            });
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
                ? (this.selected?.image || this.productImage)
                : this.productImage;

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
                for (const item of this.staged) {
                    const body = new FormData();
                    body.append('_token', this.csrf);
                    body.append('product_id', this.productId);
                    if (item.variantId) {
                        body.append('product_variant_id', item.variantId);
                    }
                    body.append('quantity', String(item.quantity));

                    const response = await fetch(this.cartStoreUrl, {
                        method: 'POST',
                        body,
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (! response.ok && response.status !== 302) {
                        throw new Error('Cart request failed');
                    }
                }

                this.staged = [];
                window.location.href = this.cartIndexUrl;
            } catch (error) {
                this.confirmError = 'Could not add to bag. Please try again.';
                this.confirming = false;
            }
        }
    }"
>
    <div class="grid lg:grid-cols-2 gap-10">
        <div class="space-y-3">
            <div class="bg-beige/40 aspect-[4/5] overflow-hidden">
                <img
                    :src="selected?.image || @js($defaultImage)"
                    alt="{{ $product->name }}"
                    class="w-full h-full object-cover"
                >
            </div>

            @if($product->has_variants && $product->activeVariants->isNotEmpty())
                <div
                    x-ref="variantPreview"
                    class="flex gap-2 overflow-x-auto pb-1"
                    role="listbox"
                    aria-label="Variant preview"
                >
                    <template x-for="variant in variants" :key="'preview-' + variant.id">
                        <button
                            type="button"
                            role="option"
                            class="group relative shrink-0 overflow-hidden border bg-beige/30 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-gold"
                            :class="variantId === variant.id
                                ? 'border-charcoal ring-1 ring-charcoal'
                                : 'border-beige hover:border-gold'"
                            :aria-selected="variantId === variant.id"
                            :aria-label="variant.name"
                            :title="variant.name"
                            @click="selectVariant(variant.id)"
                        >
                            <span class="block h-16 w-16 sm:h-20 sm:w-20">
                                <img
                                    :src="variant.image"
                                    :alt="variant.name"
                                    class="h-full w-full object-cover transition"
                                    :class="!variant.purchasable ? 'opacity-40 grayscale' : ''"
                                >
                            </span>
                            <span
                                x-show="!variant.purchasable"
                                x-cloak
                                class="absolute inset-x-0 bottom-0 bg-charcoal/70 px-1 py-0.5 text-center text-[10px] leading-tight text-ivory"
                            >Sold out</span>
                        </button>
                    </template>
                </div>
            @endif
        </div>
        <div>
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
                <p class="text-xl" x-text="selected?.priceLabel || @js(money($product->effectivePrice()))"></p>
                <p
                    class="text-sm text-taupe line-through"
                    x-show="selected?.compareAt"
                    x-cloak
                    x-text="selected?.compareAt"
                ></p>
            </div>

            <p class="text-taupe mb-6">{{ $product->short_description }}</p>

            <p class="text-sm mb-6">
                <x-badge>
                    <span x-text="canBuy ? 'In stock' : 'Out of stock'"></span>
                </x-badge>
                <span class="text-taupe">
                    · Available:
                    <span x-text="hasVariants ? (selected?.stock ?? 0) : productStock"></span>
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
                                                <span x-show="variant.purchasable" x-text="variant.stock + ' available'"></span>
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
                <form method="POST" action="{{ route('wishlist.store') }}" class="mt-3 max-w-sm">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <button class="btn btn-secondary w-full" type="submit">Save to wishlist</button>
                </form>
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
                        :match-percent="$item->match_percent ?? null"
                        :match-reason="$item->match_reason ?? null"
                    />
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
