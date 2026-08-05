@extends('layouts.storefront')
@section('title', 'Shop — '.config('aura.name'))
@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8 sm:py-10" x-data="{ filtersOpen: false }" @keydown.escape.window="filtersOpen = false">
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-6 sm:mb-8">
        <div>
            <h1 class="font-display text-4xl sm:text-5xl">Shop</h1>
            <p class="text-taupe mt-2">{{ $products->total() }} products</p>
        </div>
        <button type="button" class="btn btn-secondary lg:hidden w-full sm:w-auto inline-flex items-center justify-center gap-2" @click="filtersOpen = true">
            <x-icon name="filter" class="w-5 h-5" />
            Filters & sort
        </button>
    </div>

    <div class="grid lg:grid-cols-[240px_1fr] gap-6 lg:gap-8">
        {{-- Mobile filter drawer --}}
        <div class="fixed inset-0 z-[99999] lg:hidden" x-show="filtersOpen" x-cloak>
            <div class="absolute inset-0 z-[99998] bg-charcoal/40" @click="filtersOpen = false" x-transition.opacity></div>
            <aside
                class="absolute inset-y-0 left-0 z-[99999] w-[min(20rem,92vw)] bg-[#FFFCFA] border-r border-beige p-5 overflow-y-auto shadow-lg"
                x-show="filtersOpen"
                x-transition:enter="transition transform ease-out duration-250"
                x-transition:enter-start="-translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transition transform ease-in duration-200"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="-translate-x-full"
            >
                <div class="flex items-center justify-between mb-6">
                    <h2 class="font-display text-2xl">Filters</h2>
                    <button type="button" class="btn btn-secondary px-3 py-2" @click="filtersOpen = false">Close</button>
                </div>
                @include('storefront.partials.shop-filters', ['filters' => $filters, 'categories' => $categories, 'brands' => $brands])
            </aside>
        </div>

        {{-- Desktop filters --}}
        <aside class="hidden lg:block space-y-6">
            @include('storefront.partials.shop-filters', ['filters' => $filters, 'categories' => $categories, 'brands' => $brands])
        </aside>

        <div class="min-w-0">
            @if($products->isEmpty())
                <x-empty-state title="No products found" message="Try adjusting your filters." :action="route('shop')" actionLabel="Reset" />
            @else
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3 sm:gap-5">
                    @foreach($products as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>
                <div class="mt-8 sm:mt-10 overflow-x-auto">{{ $products->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
