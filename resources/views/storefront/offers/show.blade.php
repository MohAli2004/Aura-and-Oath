@extends('layouts.storefront')
@section('title', $offer->title.' — '.config('aura.name'))
@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 py-10 sm:py-16">
    <a href="{{ route('offers.index') }}" class="text-sm text-taupe">← All hot offers</a>
    <p class="text-xs uppercase tracking-[0.18em] text-blush mt-6 mb-2">Hot offer</p>
    <h1 class="font-display text-4xl sm:text-5xl mb-3">{{ $offer->title }}</h1>
    @if($offer->description)
        <p class="text-taupe max-w-2xl mb-10">{{ $offer->description }}</p>
    @else
        <div class="mb-10"></div>
    @endif

    <div class="grid grid-cols-2 md:grid-cols-4 gap-5">
        @foreach($offer->products as $product)
            <x-product-card :product="$product" />
        @endforeach
    </div>
</div>
@endsection
