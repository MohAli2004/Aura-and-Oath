@extends('layouts.storefront')
@section('title', 'Order '.$order->order_number)
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
            <h2 class="font-display text-2xl">Wish Account payment</h2>
            @if (app(\App\Services\WhishPayService::class)->isConfigured())
                <p class="text-taupe">Complete payment in the Whish app to confirm this order. Use Continue payment if you left before finishing.</p>
                <div class="space-y-1">
                    <div><span class="text-taupe">Amount:</span> {{ money($order->total) }}</div>
                    <div><span class="text-taupe">Order:</span> {{ $order->order_number }}</div>
                </div>
                <form method="POST" action="{{ route('payments.whish.continue', $order) }}">
                    @csrf
                    <button class="btn btn-primary" type="submit">Continue payment</button>
                </form>
            @else
                <p class="text-taupe">{{ config('aura.payments.wish.instructions') }}</p>
                <div class="space-y-1">
                    <div><span class="text-taupe">Account name:</span> {{ config('aura.payments.wish.account_name') }}</div>
                    <div><span class="text-taupe">Wish number:</span> {{ config('aura.payments.wish.account_number') }}</div>
                    <div><span class="text-taupe">Amount:</span> {{ money($order->total) }}</div>
                    <div><span class="text-taupe">Transfer note:</span> {{ $order->order_number }}</div>
                </div>
            @endif
        </div>
    @endif

    <div class="space-y-3 mb-8">
        @foreach($order->items as $item)
            @php
                $images = app(\App\Services\ImageService::class);
                $imagePath = $item->variant?->image_path ?: $item->product?->primaryImagePath();
                $imageUrl = $images->url($imagePath);
                $productUrl = $item->product?->slug
                    ? route('products.show', $item->product->slug)
                    : null;
            @endphp
            <div class="flex justify-between gap-4 border-b border-beige py-3 text-sm {{ $item->isRejected() ? 'opacity-70' : '' }}">
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
                                <x-badge>Rejected</x-badge>
                            @endif
                        </div>
                        @if($item->variant_name)<div class="text-taupe">{{ $item->variant_name }}</div>@endif
                        <div class="text-taupe">Qty {{ $item->quantity }}</div>
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
            <h2 class="font-display text-2xl mb-2">Totals</h2>
            <div class="space-y-1">
                <div class="flex justify-between"><span>Subtotal</span><span>{{ money($order->subtotal) }}</span></div>
                <div class="flex justify-between"><span>Discount</span><span>{{ money($order->discount_amount) }}</span></div>
                <div class="flex justify-between"><span>Delivery</span><span>{{ money($order->delivery_fee) }}</span></div>
                <div class="flex justify-between font-medium"><span>Total</span><span>{{ money($order->total) }}</span></div>
            </div>
        </div>
        <div>
            <h2 class="font-display text-2xl mb-2">Status history</h2>
            @foreach($order->statusHistories as $history)
                <div class="py-1">{{ $history->to_status->label() }} · {{ $history->created_at->format('M j, H:i') }}</div>
            @endforeach
        </div>
    </div>

    @can('cancel', $order)
        <form method="POST" action="{{ route('account.orders.cancel', $order) }}" onsubmit="return confirm('Cancel this order?')">
            @csrf
            <button class="btn btn-danger" type="submit">Cancel order</button>
        </form>
    @endcan
</div>
@if(session('order_just_placed'))
<script>
    // Keep Back from returning to the completed checkout form.
    history.replaceState({ orderPlaced: true }, '', location.href);
    history.pushState({ orderPlaced: true }, '', location.href);
    window.addEventListener('popstate', () => {
        window.location.replace(@js(route('account.orders.index')));
    });
</script>
@endif
@endsection
