@props(['product', 'matchReason' => null, 'bundlePrice' => null])
@php
    $imgUrl = app(\App\Services\ImageService::class)->url($product->primaryImagePath());
    $reason = $matchReason ?? ($product->match_reason ?? null);
    $inBundle = $bundlePrice !== null;
    $displayPrice = $inBundle ? (float) $bundlePrice : $product->regularPrice();
    $comparePrice = $inBundle ? $product->regularPrice() : $product->compareAtPrice();
@endphp
<article class="product-card group">
    <a href="{{ route('products.show', $product->slug) }}" class="relative block overflow-hidden bg-beige/40 aspect-[4/5]">
        <img src="{{ $imgUrl }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
        @if($inBundle || $product->hasActiveOffer())
            <span class="absolute top-3 start-3 bg-blush text-[#FFFCFA] text-[10px] uppercase tracking-[0.14em] px-2 py-1">Hot offer</span>
        @endif
    </a>
    <div class="pt-3 space-y-1">
        @if($product->brand)
            <div class="text-[11px] uppercase tracking-[0.16em] text-taupe">{{ $product->brand->name }}</div>
        @endif
        @if($product->gender)
            <div class="text-[11px] uppercase tracking-[0.14em] text-taupe">{{ $product->gender->label() }}</div>
        @endif
        <a href="{{ route('products.show', $product->slug) }}" class="font-display text-xl leading-tight block">{{ $product->name }}</a>
        <div class="flex items-baseline gap-2 text-sm">
            <span>{{ money($displayPrice) }}</span>
            @if($comparePrice && (float) $comparePrice > (float) $displayPrice)
                <span class="text-taupe line-through text-xs">{{ money($comparePrice) }}</span>
            @endif
            @if($product->hasActiveOffer() && ! $inBundle)
                <a href="{{ route('offers.index') }}" class="text-[10px] uppercase tracking-[0.14em] text-blush">In a set</a>
            @endif
        </div>
        @if($reason)
            <div class="text-xs text-taupe capitalize">{{ $reason }}</div>
        @else
            <div class="text-xs text-taupe">{{ $product->stock_status?->label() }}</div>
        @endif
    </div>
</article>
