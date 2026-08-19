@extends('layouts.storefront')
@section('title', config('aura.name').' — '.config('aura.tagline'))
@section('content')
@php
    $hero = $banners->first();
    $heroBackground = store_home_background_url()
        ?? asset($hero->image_path ?? 'images/home-hero.png');
@endphp
<section class="relative isolate min-h-[70vh] sm:min-h-[88vh] flex items-end hero-fade overflow-hidden">
    <div
        class="pointer-events-none absolute inset-0 z-0"
        aria-hidden="true"
        style="background: url('{{ $heroBackground }}') center / cover no-repeat;"
    ></div>
    <div
        class="pointer-events-none absolute inset-0 z-[1]"
        aria-hidden="true"
        style="background: linear-gradient(120deg, rgba(44,42,40,0.35), rgba(44,42,40,0.15));"
    ></div>
    <div class="relative z-[2] max-w-7xl mx-auto w-full px-4 sm:px-6 pb-20 pt-40 text-[#FFFCFA]">
        <p class="font-display text-5xl sm:text-7xl max-w-3xl leading-[0.95] mb-4">{{ $hero->title ?? config('aura.name') }}</p>
        <p class="text-lg sm:text-xl max-w-xl opacity-90 mb-8">{{ $hero->subtitle ?? config('aura.tagline') }}</p>
        <a href="{{ $hero->link_url ?? route('shop') }}" class="btn btn-gold">{{ $hero->button_text ?? 'Shop now' }}</a>
    </div>
</section>

@if(($hotOffers ?? collect())->isNotEmpty())
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-16 rise-in">
    <div class="flex items-end justify-between mb-8">
        <div>
            <h2 class="font-display text-4xl">Hot offers</h2>
            <p class="mt-2 text-sm text-taupe">Grouped products at special prices.</p>
        </div>
        <a href="{{ route('offers.index') }}" class="text-sm text-taupe">View all</a>
    </div>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-10">
        @foreach($hotOffers as $offer)
            <a href="{{ route('offers.show', $offer->slug) }}" class="block p-6 bg-[#FFFCFA] border border-beige hover:border-gold transition">
                <div class="text-[11px] uppercase tracking-[0.16em] text-blush">Hot offer</div>
                <div class="font-display text-3xl mt-2">{{ $offer->title }}</div>
                <div class="text-xs text-taupe mt-2 uppercase tracking-widest">{{ $offer->products->count() }} {{ \Illuminate\Support\Str::plural('product', $offer->products->count()) }}</div>
            </a>
        @endforeach
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-5">
        @foreach($hotOffers->flatMap->products->unique('id')->take(8) as $product)
            <x-product-card :product="$product" />
        @endforeach
    </div>
</section>
@endif

<section class="max-w-7xl mx-auto px-4 sm:px-6 py-16 rise-in">
    <div class="flex items-end justify-between mb-8">
        <h2 class="font-display text-4xl">Featured</h2>
        <a href="{{ route('shop', ['featured' => 1]) }}" class="text-sm text-taupe">View all</a>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-5">
        @forelse($featured as $product)
            <x-product-card :product="$product" />
        @empty
            <p class="text-taupe col-span-full">No featured products yet.</p>
        @endforelse
    </div>
</section>

<section class="bg-[#F3EEE7] py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="flex items-end justify-between mb-8">
            <div>
                <h2 class="font-display text-4xl">Shop by gender</h2>
                <p class="mt-2 text-sm text-taupe">Browse Women, Men, and Unisex collections.</p>
            </div>
        </div>
        <div class="grid sm:grid-cols-3 gap-4">
            <a href="{{ route('shop', ['gender' => 'women']) }}" class="block p-8 bg-ivory/70 border border-beige/70 hover:border-gold transition">
                <div class="font-display text-3xl">Women</div>
                <div class="text-xs text-taupe mt-2 uppercase tracking-widest">Shop women</div>
            </a>
            <a href="{{ route('shop', ['gender' => 'men']) }}" class="block p-8 bg-ivory/70 border border-beige/70 hover:border-gold transition">
                <div class="font-display text-3xl">Men</div>
                <div class="text-xs text-taupe mt-2 uppercase tracking-widest">Shop men</div>
            </a>
            <a href="{{ route('shop', ['gender' => 'unisex']) }}" class="block p-8 bg-ivory/70 border border-beige/70 hover:border-gold transition">
                <div class="font-display text-3xl">Unisex</div>
                <div class="text-xs text-taupe mt-2 uppercase tracking-widest">Shop unisex</div>
            </a>
        </div>
    </div>
</section>

<section class="max-w-7xl mx-auto px-4 sm:px-6 py-16">
    <div class="flex items-end justify-between mb-8">
        <h2 class="font-display text-4xl">Women</h2>
        <a href="{{ route('shop', ['gender' => 'women']) }}" class="text-sm text-taupe">View all</a>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-5">
        @forelse($womenProducts as $product)
            <x-product-card :product="$product" />
        @empty
            <p class="text-taupe col-span-full">No women’s products yet.</p>
        @endforelse
    </div>
</section>

<section class="bg-[#F3EEE7] py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="flex items-end justify-between mb-8">
            <h2 class="font-display text-4xl">Men</h2>
            <a href="{{ route('shop', ['gender' => 'men']) }}" class="text-sm text-taupe">View all</a>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-5">
            @forelse($menProducts as $product)
                <x-product-card :product="$product" />
            @empty
                <p class="text-taupe col-span-full">No men’s products yet.</p>
            @endforelse
        </div>
    </div>
</section>

<section class="max-w-7xl mx-auto px-4 sm:px-6 py-16">
    <div class="flex items-end justify-between mb-8">
        <h2 class="font-display text-4xl">Unisex</h2>
        <a href="{{ route('shop', ['gender' => 'unisex']) }}" class="text-sm text-taupe">View all</a>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-5">
        @forelse($unisexProducts as $product)
            <x-product-card :product="$product" />
        @empty
            <p class="text-taupe col-span-full">No unisex products yet.</p>
        @endforelse
    </div>
</section>

<section class="bg-[#F3EEE7] py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <h2 class="font-display text-4xl mb-8">Categories</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach($categories as $category)
                <a href="{{ route('shop', ['category' => $category->slug]) }}" class="block p-6 bg-ivory/70 border border-beige/70 hover:border-gold transition">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center">
                        <img src="{{ $category->iconUrl() }}" alt="" class="max-h-full max-w-full object-contain" loading="lazy">
                    </div>
                    <div class="font-display text-2xl">{{ $category->name }}</div>
                    <div class="text-xs text-taupe mt-2 uppercase tracking-widest">Shop</div>
                </a>
            @endforeach
        </div>
    </div>
</section>

<section class="max-w-7xl mx-auto px-4 sm:px-6 py-16">
    <h2 class="font-display text-4xl mb-8">Bestsellers</h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-5">
        @foreach($bestsellers as $product)
            <x-product-card :product="$product" />
        @endforeach
    </div>
</section>

<section class="max-w-7xl mx-auto px-4 sm:px-6 pb-20">
    <h2 class="font-display text-4xl mb-8">New arrivals</h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-5">
        @foreach($newArrivals as $product)
            <x-product-card :product="$product" />
        @endforeach
    </div>
</section>
@endsection
