@props(['offer'])
@php
    $offerTotal = $offer->offerTotal();
    $regularTotal = $offer->regularTotal();
    $savings = $offer->savingsAmount();
    $savingsPercent = $offer->savingsPercent();
    $endsAt = $offer->ends_at?->toIso8601String();
@endphp
<article
    {{ $attributes->class(['offer-spotlight group']) }}
    @if($endsAt)
        x-data="{
            endsAt: new Date(@js($endsAt)).getTime(),
            remaining: '',
            init() {
                this.tick();
                setInterval(() => this.tick(), 1000);
            },
            tick() {
                const diff = this.endsAt - Date.now();
                if (diff <= 0) { this.remaining = ''; return; }
                const d = Math.floor(diff / 86400000);
                const h = Math.floor((diff % 86400000) / 3600000);
                const m = Math.floor((diff % 3600000) / 60000);
                const s = Math.floor((diff % 60000) / 1000);
                this.remaining = d > 0
                    ? `${d}d ${h}h ${m}m`
                    : h > 0
                        ? `${h}h ${m}m ${s}s`
                        : `${m}m ${s}s`;
            }
        }"
    @endif
>
    <a href="{{ route('offers.show', $offer->slug) }}" class="offer-spotlight__visual">
        <x-offer-product-stack :offer="$offer" size="lg" class="offer-spotlight__stack" />
        @if($savingsPercent > 0)
            <div class="offer-savings-ring">
                <span class="offer-savings-ring__value">{{ $savingsPercent }}%</span>
                <span class="offer-savings-ring__label">off</span>
            </div>
        @endif
    </a>

    <div class="offer-spotlight__body">
        <div class="flex flex-wrap items-center gap-2 mb-3">
            <span class="offer-pill">Hot offer</span>
            @if($endsAt)
                <span x-show="remaining" x-cloak class="offer-pill offer-pill--urgent">
                    Ends in <span x-text="remaining"></span>
                </span>
            @endif
        </div>

        <a href="{{ route('offers.show', $offer->slug) }}" class="font-display text-3xl sm:text-4xl leading-tight block mb-2 group-hover:text-blush-deep transition-colors">
            {{ $offer->title }}
        </a>

        @if($offer->description)
            <p class="text-sm text-taupe mb-4 line-clamp-2">{{ strip_tags($offer->description) }}</p>
        @endif

        <div class="flex flex-wrap items-center gap-3 mb-4">
            <span class="font-display text-2xl">{{ money($offerTotal) }}</span>
            @if($regularTotal > $offerTotal)
                <span class="text-taupe line-through text-sm">{{ money($regularTotal) }}</span>
            @endif
            @if($savings > 0)
                <span class="text-sm text-blush-deep font-medium">You save {{ money($savings) }}</span>
            @endif
        </div>

        <p class="text-xs text-taupe mb-5">
            {{ $offer->includedUnitCount() }} {{ \Illuminate\Support\Str::plural('piece', $offer->includedUnitCount()) }}
        </p>

        <a href="{{ route('offers.show', $offer->slug) }}" class="btn btn-gold">
            Shop the bundle
        </a>
    </div>
</article>
