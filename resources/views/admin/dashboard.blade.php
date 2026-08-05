@extends('layouts.admin')
@section('heading', 'Dashboard')
@section('title', 'Dashboard')
@section('content')
<div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
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
        <div class="border border-beige bg-[#FFFCFA] p-4">
            <div class="text-xs uppercase tracking-widest text-taupe">{{ $label }}</div>
            <div class="font-display text-3xl mt-2">{{ $value }}</div>
        </div>
    @endforeach
</div>

<div class="grid lg:grid-cols-2 gap-8">
    <div>
        <h2 class="font-display text-2xl mb-4">Pending orders</h2>
        @forelse($pendingOrders as $order)
            <a href="{{ route('admin.orders.show', $order) }}" class="flex justify-between border-b border-beige py-3 text-sm">
                <span>{{ $order->order_number }}</span>
                <span>{{ $order->customer_name }}</span>
                <span>{{ money($order->total) }}</span>
            </a>
        @empty
            <p class="text-taupe">No pending orders.</p>
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
