@props(['offers', 'showHeader' => true, 'layout' => 'scroll'])
@php
    $offers = collect($offers);
    $featured = $offers->first();
    $rest = $offers->slice(1)->values();
@endphp
<section {{ $attributes->class(['hot-offers-showcase rise-in']) }}>
    @if($showHeader)
        <div class="flex items-end justify-between mb-8">
            <div>
                <p class="text-xs uppercase tracking-[0.18em] text-taupe mb-1">Bundle &amp; save</p>
                <h2 class="font-display text-4xl">Hot offers</h2>
            </div>
            <a href="{{ route('offers.index') }}" class="text-sm text-taupe hover:text-charcoal transition-colors">View all</a>
        </div>
    @endif

    @if($featured)
        <x-offer-spotlight :offer="$featured" class="mb-8" />
    @endif

    @if($rest->isNotEmpty())
        @if($layout === 'grid')
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-5">
                @foreach($rest as $offer)
                    <x-offer-showcase-card :offer="$offer" class="!w-auto" />
                @endforeach
            </div>
        @else
            <div class="relative">
                <div class="hot-offers-scroll flex gap-4 overflow-x-auto overscroll-x-contain touch-pan-x snap-x snap-mandatory pb-2 -mx-4 px-4 sm:-mx-6 sm:px-6">
                    @foreach($rest as $offer)
                        <x-offer-showcase-card :offer="$offer" />
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</section>
