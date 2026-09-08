@extends('layouts.storefront')
@section('title', __('storefront.page_title_about'))
@section('meta_description', __('storefront.about_meta'))
@section('canonical', route('pages.about'))
@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 py-16">
    <h1 class="font-display text-5xl mb-6">{{ __('storefront.about_title') }}</h1>
    <div class="space-y-4 text-taupe leading-relaxed text-lg">
        <p>{{ __('storefront.about_p1') }}</p>
        <p>{{ __('storefront.about_p2') }}</p>
        <p>{{ __('storefront.about_p3') }}</p>
    </div>
</div>
@endsection
