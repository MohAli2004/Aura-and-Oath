@props(['offer'])
@php
    $offerTotal = $offer->offerTotal();
    $regularTotal = $offer->regularTotal();
@endphp
<article class="product-card group">
    <a href="{{ route('offers.show', $offer->slug) }}" class="relative block overflow-hidden bg-beige/40 aspect-[4/5]">
        <img src="{{ $offer->imageUrl() }}" alt="{{ $offer->title }}" class="w-full h-full object-cover">
        <span class="absolute top-3 start-3 bg-blush text-[#FFFCFA] text-[10px] uppercase tracking-[0.14em] px-2 py-1">Hot offer</span>
    </a>
    <div class="pt-3 space-y-1">
        <div class="text-[11px] uppercase tracking-[0.16em] text-taupe">Hot offer</div>
        <a href="{{ route('offers.show', $offer->slug) }}" class="font-display text-xl leading-tight block">{{ $offer->title }}</a>
        <div class="flex items-baseline gap-2 text-sm">
            <span>{{ money($offerTotal) }}</span>
            @if($regularTotal > $offerTotal)
                <span class="text-taupe line-through text-xs">{{ money($regularTotal) }}</span>
            @endif
        </div>
        <div class="text-xs text-taupe">{{ $offer->products->count() }} {{ \Illuminate\Support\Str::plural('product', $offer->products->count()) }} together</div>
    </div>
</article>
