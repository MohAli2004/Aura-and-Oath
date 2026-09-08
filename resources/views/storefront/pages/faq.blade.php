@extends('layouts.storefront')
@section('title', __('storefront.page_title_faq'))
@section('meta_description', __('storefront.faq_meta_description'))
@section('canonical', route('pages.faq'))
@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 py-16 space-y-6">
    <h1 class="font-display text-5xl mb-8">{{ __('storefront.faq') }}</h1>
    <div><h2 class="font-display text-2xl">{{ __('storefront.faq_delivery_q') }}</h2><p class="text-taupe mt-2">{{ __('storefront.faq_delivery_a') }}</p></div>
    <div>
        <h2 class="font-display text-2xl">{{ __('storefront.faq_payment_q') }}</h2>
        <p class="text-taupe mt-2">
            @php
                $paymentLabels = collect(\App\Support\SiteOptions::enabledPaymentMethods())->map->label();
            @endphp
            @if($paymentLabels->isEmpty())
                {{ __('storefront.faq_payment_empty') }}
            @else
                {{ $paymentLabels->join(' '.__('storefront.and').' ') }}.
            @endif
        </p>
    </div>
    <div><h2 class="font-display text-2xl">{{ __('storefront.faq_stock_q') }}</h2><p class="text-taupe mt-2">{{ __('storefront.faq_stock_a') }}</p></div>
    <div><h2 class="font-display text-2xl">{{ __('storefront.faq_cancel_q') }}</h2><p class="text-taupe mt-2">{!! __('storefront.faq_cancel_a', ['returns' => '<a href="'.route('returns.index').'" class="underline">'.__('storefront.returns').'</a>']) !!}</p></div>
    <div><h2 class="font-display text-2xl">{{ __('storefront.faq_return_q') }}</h2><p class="text-taupe mt-2">{!! __('storefront.faq_return_a', [
        'returns' => '<a href="'.route('returns.index').'" class="underline">'.__('storefront.returns').'</a>',
        'window' => \App\Support\TrustMessaging::returnWindowLabel(),
        'policy' => '<a href="'.route('pages.show', 'returns-policy').'" class="underline">'.__('storefront.returns_policy_link').'</a>',
    ]) !!}</p></div>
</div>
@endsection
