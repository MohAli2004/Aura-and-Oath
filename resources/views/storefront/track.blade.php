@extends('layouts.storefront')
@section('title', __('storefront.page_title_track'))
@section('content')
<div class="max-w-xl mx-auto px-4 sm:px-6 py-10">
    <h1 class="font-display text-5xl mb-8">{{ __('storefront.track_order') }}</h1>
    <form method="GET" class="space-y-4 mb-8">
        <x-input :label="__('storefront.order_number')" name="order_number" value="{{ request('order_number') }}" required />
        <x-input :label="__('storefront.email')" name="email" type="email" value="{{ request('email') }}" required />
        <button class="btn btn-primary" type="submit">{{ __('storefront.track') }}</button>
    </form>
    @if($searched)
        @if($order)
            <div class="border border-beige p-5 bg-[#FFFCFA]">
                <div class="font-display text-2xl mb-2">{{ $order->order_number }}</div>
                <x-badge>{{ $order->status->label() }}</x-badge>
                <div class="mt-4 space-y-1 text-sm">
                    @foreach($order->statusHistories as $history)
                        <div>{{ $history->to_status->label() }} — {{ $history->created_at->format('M j, Y H:i') }}</div>
                    @endforeach
                </div>
            </div>
        @else
            <x-alert type="error">{{ __('storefront.order_not_found') }}</x-alert>
        @endif
    @endif
</div>
@endsection
