@extends('layouts.storefront')
@section('title', config('aura.name').' — '.config('aura.tagline'))
@section('meta_description', __('storefront.home_meta_description'))
@section('canonical', url('/'))
@push('json_ld')
<script type="application/ld+json">{!! \App\Support\Seo::encodeJsonLd(\App\Support\Seo::organizationSchema()) !!}</script>
<script type="application/ld+json">{!! \App\Support\Seo::encodeJsonLd(\App\Support\Seo::websiteSchema()) !!}</script>
@endpush
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
        style="background: url('{{ css_url($heroBackground) }}') center / cover no-repeat;"
    ></div>
    <div
        class="pointer-events-none absolute inset-0 z-[1]"
        aria-hidden="true"
        style="background: linear-gradient(120deg, rgba(44,42,40,0.35), rgba(44,42,40,0.15));"
    ></div>
    <div class="relative z-[2] max-w-7xl mx-auto w-full px-4 sm:px-6 pb-20 pt-40 text-[#FFFCFA]">
        <p class="font-display text-5xl sm:text-7xl max-w-3xl leading-[0.95] mb-4">{{ $hero?->localized('title') ?: config('aura.name') }}</p>
        <p class="text-lg sm:text-xl max-w-xl opacity-90 mb-8">{{ $hero?->localized('subtitle') ?: config('aura.tagline') }}</p>
        <a href="{{ safe_href($hero->link_url ?? null, route('shop')) }}" class="btn btn-gold">{{ $hero?->localized('button_text') ?: __('storefront.shop') }}</a>
    </div>
</section>

@if(site_flag('home_show_hot_offers') && ($hotOffers ?? collect())->isNotEmpty())
    <x-hot-offers-banner :offers="$hotOffers" class="max-w-7xl mx-auto px-4 sm:px-6 py-16" />
@endif

@if(site_flag('home_show_featured'))
    <x-product-rail
        class="rise-in"
        :title="__('storefront.featured')"
        :products="$featured"
        :more-url="route('shop', ['featured' => 1])"
        :more-hint="__('storefront.show_more_hint_featured')"
        :empty="__('storefront.no_featured_products')"
    />
@endif

@if(site_flag('home_show_new_arrivals'))
    <x-product-rail
        :title="__('storefront.new_arrivals')"
        :products="$newArrivals"
        :more-url="route('shop', ['sort' => 'newest'])"
        :more-hint="__('storefront.show_more_hint_new_arrivals')"
    />
@endif

@if(site_flag('home_show_shop_by_gender'))
<section class="bg-[#F3EEE7] py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="flex items-end justify-between mb-8">
            <div>
                <h2 class="font-display text-4xl">{{ __('storefront.shop_by_gender') }}</h2>
                <p class="mt-2 text-sm text-taupe">{{ __('storefront.shop_by_gender_desc') }}</p>
            </div>
        </div>
        <div class="grid sm:grid-cols-3 gap-4">
            <a href="{{ route('shop', ['gender' => 'women']) }}" class="block p-8 bg-ivory/70 border border-beige/70 hover:border-gold transition">
                <div class="font-display text-3xl">{{ __('storefront.women') }}</div>
                <div class="text-xs text-taupe mt-2 uppercase tracking-widest">{{ __('storefront.shop_women') }}</div>
            </a>
            <a href="{{ route('shop', ['gender' => 'men']) }}" class="block p-8 bg-ivory/70 border border-beige/70 hover:border-gold transition">
                <div class="font-display text-3xl">{{ __('storefront.men') }}</div>
                <div class="text-xs text-taupe mt-2 uppercase tracking-widest">{{ __('storefront.shop_men') }}</div>
            </a>
            <a href="{{ route('shop', ['gender' => 'unisex']) }}" class="block p-8 bg-ivory/70 border border-beige/70 hover:border-gold transition">
                <div class="font-display text-3xl">{{ __('storefront.unisex') }}</div>
                <div class="text-xs text-taupe mt-2 uppercase tracking-widest">{{ __('storefront.shop_unisex') }}</div>
            </a>
        </div>
    </div>
</section>
@endif

@if(site_flag('home_show_women'))
    <x-product-rail
        :title="__('storefront.women')"
        :products="$womenProducts"
        :more-url="route('shop', ['gender' => 'women'])"
        :more-hint="__('storefront.show_more_hint_women')"
        :empty="__('storefront.no_women_products')"
    />
@endif

@if(site_flag('home_show_men'))
    <div class="bg-[#F3EEE7]">
        <x-product-rail
            :title="__('storefront.men')"
            :products="$menProducts"
            :more-url="route('shop', ['gender' => 'men'])"
            :more-hint="__('storefront.show_more_hint_men')"
            :empty="__('storefront.no_men_products')"
        />
    </div>
@endif

@if(site_flag('home_show_unisex'))
    <x-product-rail
        :title="__('storefront.unisex')"
        :products="$unisexProducts"
        :more-url="route('shop', ['gender' => 'unisex'])"
        :more-hint="__('storefront.show_more_hint_unisex')"
        :empty="__('storefront.no_unisex_products')"
    />
@endif

@if(site_flag('home_show_categories'))
<section class="bg-[#F3EEE7] py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <h2 class="font-display text-4xl mb-8">{{ __('storefront.categories') }}</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach($categories as $category)
                <a href="{{ route('shop', ['category' => $category->slug]) }}" class="block p-6 bg-ivory/70 border border-beige/70 hover:border-gold transition">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center">
                        <img src="{{ $category->iconUrl() }}" alt="" class="max-h-full max-w-full object-contain" loading="lazy">
                    </div>
                    <div class="font-display text-2xl">{{ $category->localized('name') }}</div>
                    <div class="text-xs text-taupe mt-2 uppercase tracking-widest">{{ __('storefront.categories_shop') }}</div>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif

@if(site_flag('home_show_bestsellers'))
    <x-product-rail
        :title="__('storefront.bestsellers')"
        :products="$bestsellers"
        :more-url="route('shop', ['bestseller' => 1])"
        :more-hint="__('storefront.show_more_hint_bestsellers')"
    />
@endif

<x-trust-facts />
@endsection
