@extends('layouts.storefront')
@section('title', __('storefront.page_title_brands'))
@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 py-10">
    <div class="mb-8">
        <h1 class="font-display text-4xl sm:text-5xl">{{ __('storefront.brands') }}</h1>
        <p class="text-taupe mt-2">{{ __('storefront.explore_brands') }}</p>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
        @foreach($brands as $brand)
            <a href="{{ route('shop', ['brand' => $brand->slug]) }}" class="flex flex-col items-center p-6 bg-[#FFFCFA] border border-beige hover:border-gold transition min-h-[140px]">
                <span class="inline-flex h-14 w-14 items-center justify-center overflow-hidden border border-beige bg-ivory/60 mb-3">
                    <img src="{{ $brand->logoUrl() }}" alt="" class="h-10 w-10 object-contain" loading="lazy">
                </span>
                <span class="font-display text-lg text-center leading-tight">{{ $brand->localized('name') }}</span>
                <span class="mt-auto text-xs uppercase tracking-widest text-taupe">{{ __('storefront.shop') }}</span>
            </a>
        @endforeach
    </div>
</div>
@endsection
