@extends('layouts.admin')
@section('heading', 'Orders')
@section('title', 'Orders')
@section('content')
@php
    $list = $list ?? 'active';
    $filterQuery = array_filter([
        'q' => request('q'),
        'status' => request('status'),
    ], fn ($value) => filled($value));
@endphp

<div class="flex flex-wrap gap-2 mb-6">
    <a
        href="{{ route('admin.orders.index', array_merge($filterQuery, ['list' => 'active'])) }}"
        class="btn {{ $list === 'active' ? 'btn-primary' : 'btn-secondary' }}"
    >
        Active orders
        <span class="opacity-70">({{ $activeCount }})</span>
    </a>
    <a
        href="{{ route('admin.orders.index', array_merge($filterQuery, ['list' => 'finished'])) }}"
        class="btn {{ $list === 'finished' ? 'btn-primary' : 'btn-secondary' }}"
    >
        Finished orders
        <span class="opacity-70">({{ $finishedCount }})</span>
    </a>
    <a
        href="{{ route('admin.orders.index', array_merge($filterQuery, ['list' => 'closed'])) }}"
        class="btn {{ $list === 'closed' ? 'btn-primary' : 'btn-secondary' }}"
    >
        Cancelled, refunded &amp; returned
        <span class="opacity-70">({{ $closedCount }})</span>
    </a>
</div>

<form method="GET" class="flex flex-wrap gap-2 mb-6 items-center">
    <input type="hidden" name="list" value="{{ $list }}">
    <input class="input max-w-xs" name="q" value="{{ request('q') }}" placeholder="Search orders">
    <select name="status" class="input max-w-xs">
        <option value="">All statuses</option>
        @foreach($statuses as $status)
            <option value="{{ $status->value }}" @selected(request('status')===$status->value)>{{ $status->label() }}</option>
        @endforeach
    </select>
    <button class="btn btn-secondary" type="submit">Filter</button>
    @if(request()->filled('q') || request()->filled('status'))
        <a href="{{ route('admin.orders.index', ['list' => $list]) }}" class="btn btn-secondary">Clear</a>
    @endif
    <a
        class="btn btn-secondary inline-flex items-center gap-2"
        href="{{ route('admin.orders.print', request()->only(['q', 'status', 'list'])) }}"
        target="_blank"
        rel="noopener"
    >
        <x-icon name="print" class="h-4 w-4" />
        Print list
    </a>
</form>

<div class="overflow-x-auto border border-beige bg-[#FFFCFA]">
    <table class="w-full text-sm">
        <thead class="bg-beige/40 text-left">
            <tr>
                <th class="p-3 w-16">Paid</th>
                <th class="p-3">Order</th>
                <th class="p-3">Customer</th>
                <th class="p-3">Status</th>
                <th class="p-3">Payment</th>
                <th class="p-3">Total</th>
                <th class="p-3">Date</th>
            </tr>
        </thead>
        <tbody>
        @forelse($orders as $order)
            @php
                $isPaid = $order->payment_status === \App\Enums\PaymentStatus::Paid;
                $canTogglePaid = ! in_array($order->status, [
                    \App\Enums\OrderStatus::Cancelled,
                    \App\Enums\OrderStatus::Refunded,
                    \App\Enums\OrderStatus::Rejected,
                ], true);
            @endphp
            <tr class="border-t border-beige {{ $order->status->rowClass() }}">
                <td class="p-3">
                    @if($canTogglePaid)
                        <form
                            method="POST"
                            action="{{ $isPaid ? route('admin.orders.unmark-paid', $order) : route('admin.orders.mark-paid', $order) }}"
                            @if($isPaid) onsubmit="return confirm('Unmark payment? This order will move back to Active orders.')" @endif
                        >
                            @csrf
                            <input
                                type="checkbox"
                                class="h-4 w-4 accent-charcoal cursor-pointer"
                                title="{{ $isPaid ? 'Paid — uncheck to unmark' : 'Mark as paid' }}"
                                @checked($isPaid)
                                onchange="this.form.requestSubmit()"
                            >
                        </form>
                    @else
                        <span class="text-taupe" title="Payment cannot be changed for {{ strtolower($order->status->label()) }} orders">—</span>
                    @endif
                </td>
                <td class="p-3"><a class="underline" href="{{ route('admin.orders.show', $order) }}">{{ $order->order_number }}</a></td>
                <td class="p-3">{{ $order->customer_name }}</td>
                <td class="p-3"><x-badge :tone="$order->status->tone()">{{ $order->status->label() }}</x-badge></td>
                <td class="p-3"><x-badge :tone="$order->payment_status->tone()">{{ $order->payment_status->label() }}</x-badge></td>
                <td class="p-3">{{ money($order->total) }}</td>
                <td class="p-3">{{ $order->created_at->format('Y-m-d H:i') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="p-6 text-taupe text-center">
                    {{ match ($list) {
                        'finished' => 'No finished (paid) orders yet.',
                        'closed' => 'No cancelled, refunded, or returned orders.',
                        default => 'No active unpaid orders.',
                    } }}
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-6">{{ $orders->links() }}</div>
@endsection
