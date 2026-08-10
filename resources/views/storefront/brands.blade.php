@extends('layouts.storefront')
@section('title', 'Brands — '.config('aura.name'))
@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8 sm:py-10">
    <div class="mb-8 sm:mb-10">
        <h1 class="font-display text-4xl sm:text-5xl">Brands</h1>
        <p class="text-taupe mt-2">Explore every brand we carry.</p>
    </div>

    @if($brands->isEmpty())
        <x-empty-state title="No brands yet" message="Check back soon for our brand collection." :action="route('shop')" actionLabel="Shop now" />
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 sm:gap-5">
            @foreach($brands as $brand)
                <a
                    href="{{ route('shop', ['brand' => $brand->slug]) }}"
                    class="flex h-full flex-col border border-beige bg-[#FFFCFA] text-center hover:border-gold transition"
                >
                    <span class="aspect-square w-full shrink-0 overflow-hidden border-b border-beige bg-ivory/60 p-4 sm:p-5">
                        <img
                            src="{{ $brand->logoUrl() }}"
                            alt=""
                            class="h-full w-full object-contain"
                            loading="lazy"
                        >
                    </span>
                    <span class="flex flex-1 flex-col items-center gap-2 px-4 py-4 sm:px-5 sm:py-5">
                        <span class="font-display text-xl sm:text-2xl leading-tight">{{ $brand->name }}</span>
                        <span class="mt-auto text-xs uppercase tracking-widest text-taupe">Shop</span>
                    </span>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
