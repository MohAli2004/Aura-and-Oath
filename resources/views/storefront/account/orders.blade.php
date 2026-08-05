@extends('layouts.storefront')
@section('title', 'Orders')
@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 py-10">
    <h1 class="font-display text-5xl mb-8">Orders</h1>
    @forelse($orders as $order)
        <a href="{{ route('account.orders.show', $order) }}" class="flex flex-wrap justify-between gap-3 border-b border-beige py-4">
            <span class="font-medium">{{ $order->order_number }}</span>
            <span class="text-sm text-taupe">{{ $order->created_at->format('M j, Y') }}</span>
            <x-badge>{{ $order->status->label() }}</x-badge>
            <span>{{ money($order->total) }}</span>
        </a>
    @empty
        <x-empty-state title="No orders yet" :action="route('shop')" actionLabel="Start shopping" />
    @endforelse
    <div class="mt-8">{{ $orders->links() }}</div>
</div>
@endsection
