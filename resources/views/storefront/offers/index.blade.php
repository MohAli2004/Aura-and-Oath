@extends('layouts.storefront')
@section('title', __('storefront.page_title_offers', ['name' => config('aura.name')]))
@section('meta_description', __('storefront.offers_meta_description'))
@section('canonical', route('offers.index'))
@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 py-10 sm:py-16">
    <p class="text-xs uppercase tracking-[0.18em] text-taupe mb-2">{{ __('storefront.offers_limited_deals') }}</p>
    <h1 class="font-display text-4xl sm:text-5xl mb-3">{{ __('storefront.hot_offers') }}</h1>
    <p class="text-taupe max-w-2xl mb-10">{{ __('storefront.offers_intro') }}</p>

    @if($offers->isEmpty())
        <x-empty-state :title="__('storefront.offers_empty_title')" :message="__('storefront.offers_empty_message')" :action="route('shop')" :actionLabel="__('storefront.browse_shop')" />
    @else
        <div class="grid grid-cols-2 md:grid-cols-4 gap-5">
            @foreach($offers as $offer)
                <x-offer-card :offer="$offer" />
            @endforeach
        </div>
    @endif
</div>
@endsection
