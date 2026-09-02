@props(['offer'])
@php
    $offerTotal = $offer->offerTotal();
    $regularTotal = $offer->regularTotal();
    $savingsPercent = $offer->savingsPercent();
@endphp
<article {{ $attributes->class(['offer-showcase-card group shrink-0 snap-start']) }}>
    <a href="{{ route('offers.show', $offer->slug) }}" class="block">
        <div class="offer-showcase-card__visual">
            <x-offer-product-stack :offer="$offer" size="sm" />
            @if($savingsPercent > 0)
                <span class="offer-showcase-card__badge">Save {{ $savingsPercent }}%</span>
            @endif
        </div>
        <div class="pt-3">
            <div class="text-[10px] uppercase tracking-[0.16em] text-taupe mb-1">Bundle deal</div>
            <h3 class="font-display text-lg leading-snug mb-1.5 group-hover:text-blush-deep transition-colors">{{ $offer->title }}</h3>
            <div class="flex items-baseline gap-2 text-sm">
                <span>{{ money($offerTotal) }}</span>
                @if($regularTotal > $offerTotal)
                    <span class="text-taupe line-through text-xs">{{ money($regularTotal) }}</span>
                @endif
            </div>
        </div>
    </a>
</article>
