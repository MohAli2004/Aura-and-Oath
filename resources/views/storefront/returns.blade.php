@extends('layouts.storefront')
@section('title', __('storefront.page_title_returns'))
@section('content')
<div class="max-w-xl mx-auto px-4 sm:px-6 py-10">
    <h1 class="font-display text-5xl mb-4">{{ __('storefront.returns') }}</h1>
    <p class="text-taupe mb-2">
        {{ __('storefront.returns_intro', ['hours' => $windowHours]) }}
    </p>
    @if($policy)
        <p class="text-taupe mb-8 text-sm">
            {!! __('storefront.returns_read_full', ['link' => '<a href="'.route('pages.show', $policy->slug).'" class="underline">'.__('storefront.returns_policy_link').'</a>']) !!}
        </p>
    @else
        <div class="mb-8"></div>
    @endif

    @if($eligibleOrders->isNotEmpty() && ! $order)
        <div class="mb-10">
            <h2 class="font-display text-2xl mb-3">{{ __('storefront.eligible_orders') }}</h2>
            <div class="space-y-2">
                @foreach($eligibleOrders as $eligible)
                    <a href="{{ route('returns.index', ['order' => $eligible->id]) }}" class="flex flex-wrap justify-between gap-3 border border-beige bg-[#FFFCFA] p-4 text-sm hover:border-gold/60 transition">
                        <span class="font-medium">{{ $eligible->order_number }}</span>
                        <span class="text-taupe">{{ __('storefront.delivered_at', ['date' => $eligible->delivered_at?->format('M j, Y H:i')]) }}</span>
                        <span>{{ money($eligible->total) }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <form method="GET" class="space-y-4 mb-8">
        <x-input :label="__('storefront.order_number')" name="order_number" value="{{ request('order_number') }}" required />
        <x-input :label="__('storefront.email')" name="email" type="email" value="{{ request('email') }}" required />
        <button class="btn btn-primary" type="submit">{{ __('storefront.find_order') }}</button>
    </form>

    @if($searched)
        @if($order)
            <div class="border border-beige p-5 bg-[#FFFCFA] space-y-4">
                <div>
                    <div class="font-display text-2xl mb-2">{{ $order->order_number }}</div>
                    <x-badge :tone="$order->status->tone()">{{ $order->status->label() }}</x-badge>
                    @if($order->delivered_at)
                        <div class="mt-2 text-sm text-taupe">{{ __('storefront.delivered_at', ['date' => $order->delivered_at->format('M j, Y H:i')]) }}</div>
                    @endif
                </div>

                @if($order->canRequestReturn())
                    <form method="POST" action="{{ route('returns.store') }}" class="space-y-4" enctype="multipart/form-data">
                        @csrf
                        @auth
                            @if($order->isOwnedBy(auth()->user()))
                                <input type="hidden" name="order_id" value="{{ $order->id }}">
                            @else
                                <input type="hidden" name="order_number" value="{{ $order->order_number }}">
                                <input type="hidden" name="email" value="{{ $order->customer_email }}">
                            @endif
                        @else
                            <input type="hidden" name="order_number" value="{{ $order->order_number }}">
                            <input type="hidden" name="email" value="{{ $order->customer_email }}">
                        @endauth

                        @include('storefront.partials.return-request-fields', ['order' => $order])

                        <button class="btn btn-primary" type="submit">{{ __('storefront.request_return') }}</button>
                    </form>
                @elseif($order->canCustomerCancel())
                    <p class="text-sm text-taupe">{{ __('storefront.cancel_not_delivered') }}</p>
                    <form method="POST" action="{{ route('orders.cancel') }}" onsubmit="return confirm(@js(__('storefront.cancel_order_confirm')))">
                        @csrf
                        <input type="hidden" name="order_number" value="{{ $order->order_number }}">
                        <input type="hidden" name="email" value="{{ $order->customer_email }}">
                        <button class="btn btn-danger" type="submit">{{ __('storefront.cancel_order') }}</button>
                    </form>
                @else
                    <x-alert type="error">{{ $order->returnIneligibilityReason() }}</x-alert>
                    @if(in_array($order->status, [\App\Enums\OrderStatus::Preparing, \App\Enums\OrderStatus::OnTheWay], true))
                        <p class="text-sm text-taupe">{{ __('storefront.order_preparing_note') }}</p>
                    @endif
                @endif
            </div>
        @else
            <x-alert type="error">{{ __('storefront.order_not_found') }}</x-alert>
        @endif
    @endif
</div>
@endsection
