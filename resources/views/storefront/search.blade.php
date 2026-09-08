@extends('layouts.storefront')
@section('title', __('storefront.page_title_search', ['name' => config('aura.name')]))
@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 py-10">
    <h1 class="font-display text-5xl mb-2">{{ __('storefront.search') }}</h1>
    <p class="text-taupe mb-8">{{ __('storefront.search_results_for', ['query' => $q]) }}</p>
    <form method="GET" class="mb-8 max-w-xl"><input class="input" type="search" name="q" value="{{ $q }}" placeholder="{{ __('storefront.search_placeholder') }}"></form>

    @if(($offers ?? collect())->isNotEmpty())
        <section class="mb-12">
            <div class="flex items-end justify-between mb-6">
                <h2 class="font-display text-3xl">{{ __('storefront.matching_offers') }}</h2>
                <a href="{{ route('offers.index') }}" class="text-sm text-taupe">{{ __('storefront.all_hot_offers') }}</a>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-5">
                @foreach($offers as $offer)
                    <x-offer-card :offer="$offer" />
                @endforeach
            </div>
        </section>
    @endif

    @if($products->isEmpty() && ($offers ?? collect())->isEmpty())
        <x-empty-state :title="__('storefront.no_matches')" :message="__('storefront.try_another_keyword')" :action="route('shop')" :actionLabel="__('storefront.browse_shop')" />
    @elseif($products->isNotEmpty())
        <h2 class="font-display text-3xl mb-6">{{ __('storefront.products_heading') }}</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-5">
            @foreach($products as $product)<x-product-card :product="$product" />@endforeach
        </div>
        <div class="mt-10">{{ $products->links() }}</div>
    @endif
</div>
@endsection
