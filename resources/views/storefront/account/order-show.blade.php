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
            <div class="flex justify-between border-b border-beige py-3 text-sm">
                <div>
                    <div>{{ $item->product_name }}</div>
                    @if($item->variant_name)<div class="text-taupe">{{ $item->variant_name }}</div>@endif
                    <div class="text-taupe">Qty {{ $item->quantity }}</div>
                </div>
                <div>{{ money($item->line_total) }}</div>
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
@endsection
