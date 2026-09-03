@props(['offers'])
@php
    $offers = collect($offers);
    $campaign = $offers->first();
    $more = $offers->slice(1)->values();
    $offerTotal = $campaign?->offerTotal() ?? 0;
    $regularTotal = $campaign?->regularTotal() ?? 0;
    $savings = $campaign?->savingsAmount() ?? 0;
    $savingsPercent = $campaign?->savingsPercent() ?? 0;
    $pieceCount = $campaign?->includedUnitCount() ?? 0;
    $endsAt = $campaign?->ends_at?->toIso8601String();
@endphp
@if($campaign)
<section {{ $attributes->class(['hot-offers-home rise-in']) }} aria-labelledby="hot-offers-heading">
    <article
        class="hot-offers-banner"
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
                    this.remaining = d > 0 ? `${d}d ${h}h` : `${h}h ${m}m`;
                }
            }"
        @endif
    >
        <div class="hot-offers-banner__media" aria-hidden="true">
            <img src="{{ $campaign->imageUrl() }}" alt="" class="hot-offers-banner__photo">
            <div class="hot-offers-banner__veil"></div>
            <x-offer-product-stack :offer="$campaign" size="lg" class="hot-offers-banner__stack" />
        </div>

        <div class="hot-offers-banner__copy">
            <h2 class="hot-offers-banner__eyebrow" id="hot-offers-heading">Hot offers</h2>
            <p class="hot-offers-banner__kicker">This week's offer</p>
            <h3 class="hot-offers-banner__title">{{ $campaign->title }}</h3>
            <p class="hot-offers-banner__lede">
                {{ $pieceCount }} {{ \Illuminate\Support\Str::plural('piece', $pieceCount) }} — one price, no extra checkout steps.
            </p>

            <div class="hot-offers-banner__price">
                <span class="hot-offers-banner__now">{{ money($offerTotal) }}</span>
                @if($regularTotal > $offerTotal)
                    <span class="hot-offers-banner__was">{{ money($regularTotal) }} separately</span>
                @endif
            </div>

            <div class="hot-offers-banner__meta">
                @if($savingsPercent > 0)
                    <span class="hot-offers-banner__chip">Save {{ $savingsPercent }}% · {{ money($savings) }}</span>
                @endif
                @if($endsAt)
                    <span x-show="remaining" x-cloak class="hot-offers-banner__chip hot-offers-banner__chip--time">
                        Ends in <span x-text="remaining"></span>
                    </span>
                @endif
            </div>

            <div class="hot-offers-banner__actions">
                <a href="{{ route('offers.show', $campaign->slug) }}" class="btn btn-gold">Shop this set</a>
                <a href="{{ route('offers.index') }}" class="hot-offers-banner__ghost">All offers</a>
            </div>
        </div>
    </article>

    @if($more->isNotEmpty())
        <details class="hot-offers-more">
            <summary class="hot-offers-more__summary">
                <span>More sets this week</span>
                <span class="hot-offers-more__count">{{ $more->count() }}</span>
            </summary>
            <div class="hot-offers-more__grid">
                @foreach($more as $offer)
                    <x-offer-showcase-card :offer="$offer" class="!w-auto" />
                @endforeach
            </div>
        </details>
    @endif
</section>
@endif
