@extends('layouts.storefront')
@section('title', 'Hot offers — '.config('aura.name'))
@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 py-10 sm:py-16">
    <p class="text-xs uppercase tracking-[0.18em] text-taupe mb-2">Limited deals</p>
    <h1 class="font-display text-4xl sm:text-5xl mb-3">Hot offers</h1>
    <p class="text-taupe max-w-2xl mb-10">Buy a full set of products together at a better price. The offer applies only when every item in the set is in your bag.</p>

    @if($offers->isEmpty())
        <x-empty-state title="No hot offers right now" message="Check back soon for grouped product deals." :action="route('shop')" actionLabel="Browse shop" />
    @else
        <div class="grid grid-cols-2 md:grid-cols-4 gap-5">
            @foreach($offers as $offer)
                <x-offer-card :offer="$offer" />
            @endforeach
        </div>
    @endif
</div>
@endsection
