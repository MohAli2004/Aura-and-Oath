@extends('layouts.admin')
@section('heading', 'Order '.$order->order_number)
@section('title', $order->order_number)
@section('content')
<div class="grid lg:grid-cols-[1.4fr_1fr] gap-8">
    <div class="space-y-6">
        <div class="border border-beige bg-[#FFFCFA] p-5">
            <div class="flex flex-wrap gap-2 mb-4">
                <x-badge>{{ $order->status->label() }}</x-badge>
                <x-badge>{{ $order->payment_method->label() }}</x-badge>
                <x-badge>{{ $order->payment_status->label() }}</x-badge>
            </div>
            <div class="text-sm space-y-1 mb-4">
                <div>{{ $order->customer_name }} · {{ $order->customer_email }} · {{ $order->customer_phone }}</div>
                <div class="text-taupe">{{ money($order->total) }} total</div>
            </div>
            @foreach($order->items as $item)
                <div class="flex justify-between border-t border-beige py-3 text-sm">
                    <div>
                        <div>{{ $item->product_name }} @if($item->variant_name)— {{ $item->variant_name }}@endif</div>
                        <div class="text-taupe">{{ $item->sku }} · {{ $item->barcode }} · qty {{ $item->quantity }}</div>
                    </div>
                    <div>{{ money($item->line_total) }}</div>
                </div>
            @endforeach
        </div>

        <div class="flex flex-wrap gap-2">
            <a class="btn btn-secondary" href="{{ route('admin.orders.invoice', $order) }}" target="_blank">Invoice</a>
            <a class="btn btn-secondary" href="{{ route('admin.orders.packing-slip', $order) }}" target="_blank">Packing slip</a>
            @if($order->status === \App\Enums\OrderStatus::PendingApproval)
                <form method="POST" action="{{ route('admin.orders.approve', $order) }}">@csrf<button class="btn btn-primary" type="submit">Approve</button></form>
                <form method="POST" action="{{ route('admin.orders.reject', $order) }}" class="flex gap-2">
                    @csrf
                    <input class="input" name="reason" placeholder="Rejection reason" required>
                    <button class="btn btn-danger" type="submit">Reject</button>
                </form>
            @endif
            @if($order->payment_status !== \App\Enums\PaymentStatus::Paid)
                <form method="POST" action="{{ route('admin.orders.mark-paid', $order) }}">
                    @csrf
                    <button class="btn btn-secondary" type="submit">Mark payment paid</button>
                </form>
            @endif
        </div>

        <form method="POST" action="{{ route('admin.orders.status', $order) }}" class="border border-beige p-5 bg-[#FFFCFA] space-y-3">
            @csrf
            <h2 class="font-display text-xl">Update status</h2>
            <select name="status" class="input">
                @foreach($order->status->allowedTransitions() as $next)
                    <option value="{{ $next->value }}">{{ $next->label() }}</option>
                @endforeach
            </select>
            <input class="input" name="tracking_number" placeholder="Tracking number" value="{{ $order->tracking_number }}">
            <input class="input" name="note" placeholder="Note">
            <button class="btn btn-secondary" type="submit" @disabled(count($order->status->allowedTransitions())===0)>Update</button>
        </form>

        @if(in_array($order->status, [\App\Enums\OrderStatus::OnTheWay, \App\Enums\OrderStatus::Delivered], true))
            <form method="POST" action="{{ route('admin.orders.return', $order) }}" class="border border-beige p-5 space-y-3">
                @csrf
                <h2 class="font-display text-xl">Confirm return</h2>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="resellable" value="1" checked> Restock as resellable</label>
                <input class="input" name="note" placeholder="Note">
                <button class="btn btn-secondary" type="submit">Process return</button>
            </form>
        @endif
    </div>

    <div class="space-y-6">
        <div class="border border-beige p-5 bg-[#FFFCFA] text-sm">
            <h2 class="font-display text-xl mb-3">Addresses</h2>
            @foreach($order->addresses as $address)
                <div class="mb-3"><div class="uppercase text-xs tracking-widest text-taupe">{{ $address->type->label() }}</div>{{ $address->full_name }}<br>{{ $address->formatted() }}</div>
            @endforeach
        </div>
        <div class="border border-beige p-5 bg-[#FFFCFA] text-sm">
            <h2 class="font-display text-xl mb-3">History</h2>
            @foreach($order->statusHistories as $history)
                <div class="py-1">{{ $history->to_status->label() }} · {{ $history->created_at->format('Y-m-d H:i') }} @if($history->note)— {{ $history->note }}@endif</div>
            @endforeach
        </div>
        <form method="POST" action="{{ route('admin.orders.notes', $order) }}" class="border border-beige p-5 bg-[#FFFCFA] space-y-3">
            @csrf
            <h2 class="font-display text-xl">Add note</h2>
            <textarea name="body" class="input" rows="3" required></textarea>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_customer_visible" value="1"> Visible to customer</label>
            <button class="btn btn-secondary" type="submit">Save note</button>
        </form>
        @foreach($order->notes as $note)
            <div class="text-sm border-b border-beige py-2">{{ $note->body }} <span class="text-taupe">{{ $note->is_customer_visible ? '(customer)' : '(internal)' }}</span></div>
        @endforeach
    </div>
</div>
@endsection
