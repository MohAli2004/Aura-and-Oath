@extends('layouts.admin')
@section('heading', $customer->name)
@section('content')
<div class="grid md:grid-cols-2 gap-8">
    <div class="border border-beige p-5 bg-[#FFFCFA] text-sm space-y-2">
        <div>{{ $customer->email }}</div>
        <div>{{ $customer->phone }}</div>
        <div>Joined {{ $customer->created_at->format('Y-m-d') }}</div>
    </div>
    <div>
        <h2 class="font-display text-2xl mb-3">Orders</h2>
        @foreach($customer->orders as $order)
            <a href="{{ route('admin.orders.show', $order) }}" class="flex justify-between gap-3 border-b border-beige py-2 text-sm {{ $order->status->rowClass() }} px-2">
                <span>{{ $order->order_number }}</span>
                <x-badge :tone="$order->status->tone()">{{ $order->status->label() }}</x-badge>
                <span>{{ money($order->total) }}</span>
            </a>
        @endforeach
    </div>
</div>
@endsection
