@extends('layouts.admin')
@section('heading', 'Dashboard')
@section('title', 'Dashboard')
@section('content')
<div class="mb-8 grid grid-cols-2 gap-2.5 sm:gap-4 xl:grid-cols-4">
    @foreach([
        ['Orders today', $stats['orders_today']],
        ['Revenue today', money($stats['revenue_today'])],
        ['Pending approval', $stats['pending_approval']],
        ['Low stock', $stats['low_stock']],
        ['Out of stock', $stats['out_of_stock']],
        ['Customers', $stats['customers']],
        ['Active products', $stats['products_active']],
        ['Revenue this month', money($stats['revenue_month'])],
    ] as [$label, $value])
        <div class="border border-beige bg-[#FFFCFA] p-3 sm:p-4">
            <div class="text-[10px] uppercase leading-snug tracking-widest text-taupe sm:text-xs">{{ $label }}</div>
            <div class="mt-1.5 font-display text-2xl sm:mt-2 sm:text-3xl">{{ $value }}</div>
        </div>
    @endforeach
</div>

<x-admin.profit-calculator
    :profit-today="$stats['profit_today'] ?? 0"
    :profit-month="$stats['profit_month'] ?? 0"
/>

<div class="grid lg:grid-cols-2 gap-8">
    <div>
        <h2 class="font-display text-2xl mb-4">Pending orders</h2>
        @forelse($pendingOrders as $order)
            <a href="{{ route('admin.orders.show', $order) }}" class="flex justify-between gap-3 border-b border-beige py-3 text-sm {{ $order->status->rowClass() }} px-2">
                <span>{{ $order->order_number }}</span>
                <span>{{ $order->customer_name }}</span>
                <x-badge :tone="$order->status->tone()">{{ $order->status->label() }}</x-badge>
                <span>{{ money($order->total) }}</span>
            </a>
        @empty
            <p class="text-taupe">No pending orders.</p>
        @endforelse

        <h2 class="font-display text-2xl mb-4 mt-10">Return requests</h2>
        @forelse($returnRequestedOrders as $order)
            <a href="{{ route('admin.orders.show', $order) }}" class="flex justify-between gap-3 border-b border-beige py-3 text-sm {{ $order->status->rowClass() }} px-2">
                <span>{{ $order->order_number }}</span>
                <span>{{ $order->customer_name }}</span>
                <x-badge :tone="$order->status->tone()">{{ $order->status->label() }}</x-badge>
                <span>{{ money($order->total) }}</span>
            </a>
        @empty
            <p class="text-taupe">No pending return requests.</p>
        @endforelse
    </div>
    <div>
        <h2 class="font-display text-2xl mb-4">Stock alerts</h2>
        @forelse($lowStockProducts as $product)
            <a href="{{ route('admin.products.edit', $product) }}" class="flex justify-between border-b border-beige py-3 text-sm">
                <span>{{ $product->name }}</span>
                <span>{{ $product->stock_status?->label() }}</span>
                <span>{{ $product->availableStock() }} avail.</span>
            </a>
        @empty
            <p class="text-taupe">Stock looks healthy.</p>
        @endforelse
    </div>
</div>
@endsection
