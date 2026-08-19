@extends('layouts.storefront')
@section('title', 'Hot offers — '.config('aura.name'))
@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 py-10 sm:py-16">
    <p class="text-xs uppercase tracking-[0.18em] text-taupe mb-2">Limited deals</p>
    <h1 class="font-display text-4xl sm:text-5xl mb-3">Hot offers</h1>
    <p class="text-taupe max-w-2xl mb-10">Special prices on grouped products. Shop an offer to see every item included.</p>

    @if($offers->isEmpty())
        <x-empty-state title="No hot offers right now" message="Check back soon for grouped product deals." :action="route('shop')" actionLabel="Browse shop" />
    @else
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($offers as $offer)
                <a href="{{ route('offers.show', $offer->slug) }}" class="block border border-beige bg-[#FFFCFA] p-6 hover:border-gold transition">
                    <div class="text-[11px] uppercase tracking-[0.16em] text-blush">Hot offer</div>
                    <h2 class="font-display text-3xl mt-2">{{ $offer->title }}</h2>
                    @if($offer->description)
                        <p class="text-sm text-taupe mt-2 line-clamp-3">{{ $offer->description }}</p>
                    @endif
                    <p class="text-xs uppercase tracking-widest text-taupe mt-4">{{ $offer->products->count() }} {{ Str::plural('product', $offer->products->count()) }}</p>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
