@extends('layouts.storefront')
@section('title', 'Search — '.config('aura.name'))
@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 py-10">
    <h1 class="font-display text-5xl mb-2">Search</h1>
    <p class="text-taupe mb-8">Results for “{{ $q }}”</p>
    <form method="GET" class="mb-8 max-w-xl"><input class="input" type="search" name="q" value="{{ $q }}" placeholder="Search products and offers..."></form>

    @if(($offers ?? collect())->isNotEmpty())
        <section class="mb-12">
            <div class="flex items-end justify-between mb-6">
                <h2 class="font-display text-3xl">Matching offers</h2>
                <a href="{{ route('offers.index') }}" class="text-sm text-taupe">All hot offers</a>
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach($offers as $offer)
                    <a href="{{ route('offers.show', $offer->slug) }}" class="block border border-beige bg-[#FFFCFA] p-6 hover:border-gold transition">
                        <div class="text-[11px] uppercase tracking-[0.16em] text-blush">Hot offer</div>
                        <h3 class="font-display text-3xl mt-2">{{ $offer->title }}</h3>
                        <p class="text-xs uppercase tracking-widest text-taupe mt-4">{{ $offer->products->count() }} {{ Str::plural('product', $offer->products->count()) }}</p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if($products->isEmpty() && ($offers ?? collect())->isEmpty())
        <x-empty-state title="No matches" message="Try another keyword." :action="route('shop')" actionLabel="Browse shop" />
    @elseif($products->isNotEmpty())
        <h2 class="font-display text-3xl mb-6">Products</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-5">
            @foreach($products as $product)<x-product-card :product="$product" />@endforeach
        </div>
        <div class="mt-10">{{ $products->links() }}</div>
    @endif
</div>
@endsection
