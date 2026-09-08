@extends('layouts.storefront')
@php
    $activeCategory = ! empty($filters['category'])
        ? $categories->firstWhere('slug', $filters['category'])
        : null;
    $activeBrand = ! empty($filters['brand'])
        ? $brands->firstWhere('slug', $filters['brand'])
        : null;
    $genderKey = match ($filters['gender'] ?? null) {
        'women' => 'gender_women',
        'men' => 'gender_men',
        'unisex' => 'gender_unisex',
        default => null,
    };

    if ($activeCategory) {
        $seoTitle = $activeCategory->localized('name').' — '.config('aura.name');
        $seoDescription = __('storefront.shop_meta_category', ['category' => $activeCategory->localized('name')]);
        $shopCanonical = route('shop', ['category' => $activeCategory->slug]);
    } elseif ($activeBrand) {
        $seoTitle = $activeBrand->localized('name').' — '.config('aura.name');
        $seoDescription = __('storefront.shop_meta_brand', ['brand' => $activeBrand->localized('name')]);
        $shopCanonical = route('shop', ['brand' => $activeBrand->slug]);
    } elseif ($genderKey) {
        $seoTitle = __('storefront.'.$genderKey).' — '.config('aura.name');
        $seoDescription = __('storefront.shop_meta_gender', ['gender' => __('storefront.'.$genderKey)]);
        $shopCanonical = route('shop', ['gender' => $filters['gender']]);
    } else {
        $seoTitle = __('storefront.page_title_shop', ['name' => config('aura.name')]);
        $seoDescription = __('storefront.shop_meta_description');
        $shopCanonical = route('shop');
    }

    $breadcrumbItems = [
        ['name' => config('aura.name'), 'url' => url('/')],
        ['name' => __('storefront.shop'), 'url' => route('shop')],
    ];
    if ($activeCategory) {
        $breadcrumbItems[] = ['name' => $activeCategory->localized('name'), 'url' => $shopCanonical];
    } elseif ($activeBrand) {
        $breadcrumbItems[] = ['name' => $activeBrand->localized('name'), 'url' => $shopCanonical];
    } elseif ($genderKey) {
        $breadcrumbItems[] = ['name' => __('storefront.'.$genderKey), 'url' => $shopCanonical];
    }
@endphp
@section('title', $seoTitle)
@section('meta_description', $seoDescription)
@section('canonical', $shopCanonical)
@push('json_ld')
<script type="application/ld+json">{!! \App\Support\Seo::encodeJsonLd(\App\Support\Seo::breadcrumbSchema($breadcrumbItems)) !!}</script>
@endpush
@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8 sm:py-10" x-data="{ filtersOpen: false }" @keydown.escape.window="filtersOpen = false">
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-6 sm:mb-8">
        <div>
            <h1 class="font-display text-4xl sm:text-5xl">{{ __('storefront.shop') }}</h1>
            <p class="text-taupe mt-2">{{ __('storefront.products_count', ['count' => $products->total()]) }}</p>
        </div>
        <button type="button" class="btn btn-secondary lg:hidden w-full sm:w-auto inline-flex items-center justify-center gap-2" @click="filtersOpen = true">
            <x-icon name="filter" class="w-5 h-5" />
            {{ __('storefront.filters_sort') }}
        </button>
    </div>

    <div class="grid lg:grid-cols-[240px_1fr] gap-6 lg:gap-8">
        <div class="fixed inset-0 z-[99999] lg:hidden" x-show="filtersOpen" x-cloak>
            <div class="absolute inset-0 z-[99998] bg-charcoal/40" @click="filtersOpen = false" x-transition.opacity></div>
            <aside
                class="absolute inset-y-0 start-0 z-[99999] w-[min(20rem,92vw)] bg-[#FFFCFA] border-e border-beige p-5 overflow-y-auto shadow-lg"
                x-show="filtersOpen"
                x-transition:enter="transition transform ease-out duration-250"
                x-transition:enter-start="-translate-x-full rtl:translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transition transform ease-in duration-200"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="-translate-x-full rtl:translate-x-full"
            >
                <div class="flex items-center justify-between mb-6">
                    <h2 class="font-display text-2xl">{{ __('storefront.filters') }}</h2>
                    <button type="button" class="btn btn-secondary px-3 py-2" @click="filtersOpen = false">{{ __('storefront.close') }}</button>
                </div>
                @include('storefront.partials.shop-filters', ['filters' => $filters, 'categories' => $categories, 'brands' => $brands])
            </aside>
        </div>

        <aside class="hidden lg:block space-y-6">
            @include('storefront.partials.shop-filters', ['filters' => $filters, 'categories' => $categories, 'brands' => $brands])
        </aside>

        <div class="min-w-0">
            @if($products->isEmpty())
                <x-empty-state :title="__('storefront.no_products_title')" :message="__('storefront.no_products_message')" :action="route('shop')" :actionLabel="__('storefront.reset')" />
            @else
                <div class="grid grid-cols-2 items-stretch md:grid-cols-3 gap-3 sm:gap-5">
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
