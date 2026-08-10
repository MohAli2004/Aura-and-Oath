@extends('layouts.admin')
@section('heading', 'Orders')
@section('title', 'Orders')
@section('content')
<form method="GET" class="flex flex-wrap gap-2 mb-6 items-center">
    <input class="input max-w-xs" name="q" value="{{ request('q') }}" placeholder="Search orders">
    <select name="status" class="input max-w-xs">
        <option value="">All statuses</option>
        @foreach($statuses as $status)
            <option value="{{ $status->value }}" @selected(request('status')===$status->value)>{{ $status->label() }}</option>
        @endforeach
    </select>
    <button class="btn btn-secondary" type="submit">Filter</button>
    @if(request()->filled('q') || request()->filled('status'))
        <a href="{{ url()->current() }}" class="btn btn-secondary">Clear</a>
    @endif
    <a
        class="btn btn-secondary inline-flex items-center gap-2"
        href="{{ route('admin.orders.print', request()->only(['q', 'status'])) }}"
        target="_blank"
        rel="noopener"
    >
        <x-icon name="print" class="h-4 w-4" />
        Print list
    </a>
</form>
<div class="overflow-x-auto border border-beige bg-[#FFFCFA]">
    <table class="w-full text-sm">
        <thead class="bg-beige/40 text-left"><tr>
            <th class="p-3">Order</th><th class="p-3">Customer</th><th class="p-3">Status</th><th class="p-3">Total</th><th class="p-3">Date</th>
        </tr></thead>
        <tbody>
        @foreach($orders as $order)
            <tr class="border-t border-beige {{ $order->status->rowClass() }}">
                <td class="p-3"><a class="underline" href="{{ route('admin.orders.show', $order) }}">{{ $order->order_number }}</a></td>
                <td class="p-3">{{ $order->customer_name }}</td>
                <td class="p-3"><x-badge :tone="$order->status->tone()">{{ $order->status->label() }}</x-badge></td>
                <td class="p-3">{{ money($order->total) }}</td>
                <td class="p-3">{{ $order->created_at->format('Y-m-d H:i') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="mt-6">{{ $orders->links() }}</div>
@endsection
