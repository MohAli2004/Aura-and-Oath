@extends('layouts.storefront')
@section('title', __('storefront.page_title_order', ['number' => $order->order_number]))
@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 py-10">
    <h1 class="font-display text-4xl mb-2">{{ $order->order_number }}</h1>
    <p class="text-taupe mb-8">
        <x-badge>{{ $order->status->label() }}</x-badge>
        · <x-badge>{{ $order->payment_method->label() }}</x-badge>
        · <x-badge>{{ $order->payment_status->label() }}</x-badge>
        · {{ money($order->total) }}
    </p>

    @if ($order->payment_method === \App\Enums\PaymentMethod::WishAccount && $order->payment_status !== \App\Enums\PaymentStatus::Paid)
        <div class="border border-beige bg-[#FFFCFA] p-5 mb-8 text-sm space-y-3">
            <h2 class="font-display text-2xl">{{ __('storefront.order_wish_payment') }}</h2>
            @if (app(\App\Services\WhishPayService::class)->isConfigured())
                <p class="text-taupe">{{ __('storefront.order_wish_complete') }}</p>
                <div class="space-y-1">
                    <div><span class="text-taupe">{{ __('storefront.amount') }}:</span> {{ money($order->total) }}</div>
                    <div><span class="text-taupe">{{ __('storefront.order_label') }}:</span> {{ $order->order_number }}</div>
                </div>
                <form method="POST" action="{{ route('payments.whish.continue', $order) }}">
                    @csrf
                    <button class="btn btn-primary" type="submit">{{ __('storefront.continue_payment') }}</button>
                </form>
            @else
                <p class="text-taupe">{{ config('aura.payments.wish.instructions') }}</p>
                <div class="space-y-1">
                    <div><span class="text-taupe">{{ __('storefront.account_name') }}:</span> {{ config('aura.payments.wish.account_name') }}</div>
                    <div><span class="text-taupe">{{ __('storefront.wish_number') }}:</span> {{ config('aura.payments.wish.account_number') }}</div>
                    <div><span class="text-taupe">{{ __('storefront.amount') }}:</span> {{ money($order->total) }}</div>
                    <div><span class="text-taupe">{{ __('storefront.transfer_note') }}:</span> {{ $order->order_number }}</div>
                </div>
            @endif
        </div>
    @endif

    <div class="space-y-3 mb-8">
        @foreach($order->items as $item)
            @php
                $images = app(\App\Services\ImageService::class);
                $imagePath = $item->variant?->primaryImagePath() ?: $item->product?->primaryImagePath();
                $imageUrl = $images->url($imagePath);
                $productUrl = $item->product?->slug
                    ? route('products.show', $item->product->slug)
                    : null;
            @endphp
            <div class="flex justify-between gap-4 border-b border-beige py-3 text-sm {{ $item->isRejected() || $item->isReturned() ? 'opacity-70' : '' }}">
                <div class="flex gap-3 min-w-0">
                    @if($productUrl)
                        <a href="{{ $productUrl }}" class="shrink-0 h-14 w-14 sm:h-16 sm:w-16 overflow-hidden border border-beige bg-beige/30">
                            <img src="{{ $imageUrl }}" alt="{{ $item->product_name }}" class="h-full w-full object-cover">
                        </a>
                    @else
                        <div class="shrink-0 h-14 w-14 sm:h-16 sm:w-16 overflow-hidden border border-beige bg-beige/30">
                            <img src="{{ $imageUrl }}" alt="{{ $item->product_name }}" class="h-full w-full object-cover">
                        </div>
                    @endif
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            @if($productUrl)
                                <a href="{{ $productUrl }}" class="hover:text-gold transition {{ $item->isRejected() ? 'line-through' : '' }}">{{ $item->product_name }}</a>
                            @else
                                <div class="{{ $item->isRejected() ? 'line-through' : '' }}">{{ $item->product_name }}</div>
                            @endif
                            @if($item->isRejected())
                                <x-badge>{{ __('storefront.rejected') }}</x-badge>
                            @endif
                            @if($item->isReturned())
                                <x-badge>{{ __('storefront.returned') }}</x-badge>
                            @endif
                        </div>
                        @if($item->variant_name)<div class="text-taupe">{{ $item->variant_name }}</div>@endif
                        <div class="text-taupe">{{ __('storefront.qty') }} {{ $item->quantity }}</div>
                        @if($item->isRejected() && $item->rejection_reason)
                            <div class="mt-1 text-xs text-[#B85C5C]">{{ $item->rejection_reason }}</div>
                        @endif
                    </div>
                </div>
                <div class="shrink-0 {{ $item->isRejected() ? 'line-through text-taupe' : '' }}">{{ money($item->line_total) }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid sm:grid-cols-2 gap-6 mb-8 text-sm">
        <div>
            <h2 class="font-display text-2xl mb-2">{{ __('storefront.totals') }}</h2>
            <div class="space-y-1">
                <div class="flex justify-between"><span>{{ __('storefront.subtotal') }}</span><span>{{ money($order->subtotal) }}</span></div>
                <div class="flex justify-between"><span>{{ __('storefront.discount') }}</span><span>{{ money($order->discount_amount) }}</span></div>
                <div class="flex justify-between"><span>{{ __('storefront.delivery') }}</span><span>{{ money($order->delivery_fee) }}</span></div>
                <div class="flex justify-between font-medium"><span>{{ __('storefront.total') }}</span><span>{{ money($order->total) }}</span></div>
            </div>
        </div>
        <div>
            <h2 class="font-display text-2xl mb-2">{{ __('storefront.status_history') }}</h2>
            @foreach($order->statusHistories as $history)
                <div class="py-1">{{ $history->to_status->label() }} · {{ $history->created_at->format('M j, H:i') }}</div>
            @endforeach
        </div>
    </div>

    @if($order->status === \App\Enums\OrderStatus::Cancelled)
        <div class="mb-8 border border-beige bg-[#FFFCFA] p-5">
            <h2 class="font-display text-2xl mb-2">{{ __('storefront.cancelled_heading') }}</h2>
            <p class="text-sm text-taupe">{{ __('storefront.cancelled_note') }}</p>
        </div>
    @endif

    @can('cancel', $order)
        <div class="mb-8 border border-beige bg-[#FFFCFA] p-5 space-y-3">
            <h2 class="font-display text-2xl">{{ __('storefront.cancel_order_heading') }}</h2>
            <p class="text-sm text-taupe">{{ __('storefront.cancel_order_note') }}</p>
            <form method="POST" action="{{ route('account.orders.cancel', $order) }}" onsubmit="return confirm(@js(__('storefront.cancel_order_confirm')))">
                @csrf
                <button class="btn btn-danger" type="submit">{{ __('storefront.cancel_order') }}</button>
            </form>
        </div>
    @endcan

    @can('requestReturn', $order)
        <div class="mt-8 border border-beige bg-[#FFFCFA] p-5 space-y-4">
            <h2 class="font-display text-2xl">{{ __('storefront.return_items_heading_order') }}</h2>
            <p class="text-sm text-taupe">{{ __('storefront.return_eligible_note', ['hours' => $order->returnWindowHours()]) }}</p>
            <form method="POST" action="{{ route('returns.store') }}" class="space-y-4" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="order_id" value="{{ $order->id }}">
                @include('storefront.partials.return-request-fields', ['order' => $order])
                <button class="btn btn-primary" type="submit">{{ __('storefront.request_return') }}</button>
            </form>
        </div>
    @else
        @if($order->status === \App\Enums\OrderStatus::ReturnRequested)
            <div class="mt-8 border border-beige bg-[#FFFCFA] p-5 text-sm">
                <h2 class="font-display text-2xl mb-2">{{ __('storefront.return_requested_heading') }}</h2>
                <p class="text-taupe">{{ __('storefront.return_requested_note') }}</p>
            </div>
        @endif
    @endcan
</div>
@if(session('order_just_placed'))
<script nonce="{{ csp_nonce() }}">
    history.replaceState({ orderPlaced: true }, '', location.href);
    history.pushState({ orderPlaced: true }, '', location.href);
    window.addEventListener('popstate', () => {
        window.location.replace(@js(route('account.orders.index')));
    });
</script>
@endif
@endsection
