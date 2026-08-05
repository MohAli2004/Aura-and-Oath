@props(['product'])
@php
    $imgUrl = app(\App\Services\ImageService::class)->url($product->primaryImagePath());
@endphp
<article class="product-card group">
    <a href="{{ route('products.show', $product->slug) }}" class="block overflow-hidden bg-beige/40 aspect-[4/5]">
        <img src="{{ $imgUrl }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
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
            <span>{{ money($product->effectivePrice()) }}</span>
            @if($product->compare_at_price)
                <span class="text-taupe line-through text-xs">{{ money($product->compare_at_price) }}</span>
            @endif
        </div>
        <div class="text-xs text-taupe">{{ $product->stock_status?->label() }}</div>
    </div>
</article>
