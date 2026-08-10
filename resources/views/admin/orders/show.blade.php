@extends('layouts.admin')
@section('heading', 'Order '.$order->order_number)
@section('title', $order->order_number)
@section('content')
@php
    $isPending = $order->status === \App\Enums\OrderStatus::PendingApproval;
    $acceptedCount = $order->items->filter(fn ($item) => $item->isAccepted())->count();
@endphp
<div class="grid lg:grid-cols-[1.4fr_1fr] gap-8">
    <div class="space-y-6">
        <div class="border border-beige bg-[#FFFCFA] p-5">
            <div class="flex flex-wrap gap-2 mb-4">
                <x-badge :tone="$order->status->tone()">{{ $order->status->label() }}</x-badge>
                <x-badge>{{ $order->payment_method->label() }}</x-badge>
                <x-badge :tone="$order->payment_status->tone()">{{ $order->payment_status->label() }}</x-badge>
            </div>
            <div class="text-sm space-y-1 mb-4">
                <div>{{ $order->customer_name }} · {{ $order->customer_email }} · {{ $order->customer_phone }}</div>
                <div class="text-taupe">{{ money($order->total) }} total</div>
            </div>

            @foreach($order->items as $index => $item)
                @php
                    $images = app(\App\Services\ImageService::class);
                    $imagePath = $item->variant?->image_path ?: $item->product?->primaryImagePath();
                    $imageUrl = $images->url($imagePath);
                    $rejected = $item->isRejected();
                @endphp
                <div class="flex flex-col gap-3 border-t border-beige py-3 text-sm sm:flex-row sm:justify-between {{ $rejected ? 'opacity-70' : '' }}">
                    <div class="flex gap-3 min-w-0">
                        @if($isPending && ! $rejected)
                            <label class="mt-1 shrink-0">
                                <input
                                    type="checkbox"
                                    form="reject-items-form"
                                    name="items[{{ $index }}][id]"
                                    value="{{ $item->id }}"
                                    class="reject-item-check"
                                >
                            </label>
                        @endif
                        <div class="shrink-0 h-14 w-14 overflow-hidden border border-beige bg-beige/30">
                            <img src="{{ $imageUrl }}" alt="{{ $item->product_name }}" class="h-full w-full object-cover">
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="{{ $rejected ? 'line-through' : '' }}">
                                    {{ $item->product_name }} @if($item->variant_name)— {{ $item->variant_name }}@endif
                                </span>
                                @if($rejected)
                                    <x-badge tone="danger">Rejected</x-badge>
                                @endif
                            </div>
                            <div class="text-taupe">{{ $item->sku }} · {{ $item->barcode }} · qty {{ $item->quantity }}</div>
                            @if($rejected && $item->rejection_reason)
                                <div class="mt-1 text-xs text-[#B85C5C]">{{ $item->rejection_reason }}</div>
                            @endif
                            @if($isPending && ! $rejected)
                                <input
                                    type="text"
                                    form="reject-items-form"
                                    name="items[{{ $index }}][reason]"
                                    class="input mt-2 text-xs"
                                    placeholder="Reject reason (optional)"
                                    disabled
                                    data-reason-for="{{ $item->id }}"
                                >
                            @endif
                            @if($isPending && $rejected)
                                <form method="POST" action="{{ route('admin.orders.items.restore', [$order, $item]) }}" class="mt-2">
                                    @csrf
                                    <button type="submit" class="btn btn-secondary btn-sm">
                                        <x-icon name="undo" /> Restore item
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                    <div class="shrink-0 {{ $rejected ? 'line-through text-taupe' : '' }}">{{ money($item->line_total) }}</div>
                </div>
            @endforeach

            @if($isPending)
                <form
                    method="POST"
                    id="reject-items-form"
                    action="{{ route('admin.orders.reject-items', $order) }}"
                    class="mt-4 flex flex-wrap gap-2 border-t border-beige pt-4"
                >
                    @csrf
                    <button
                        type="submit"
                        class="btn btn-danger"
                        onclick="return confirm('Reject selected items? Stock will be released and totals recalculated.')"
                    >
                        <x-icon name="x" /> Reject selected items
                    </button>
                    <p class="w-full text-xs text-taupe">Check items to exclude, then reject them before approving the rest.</p>
                </form>
            @endif
        </div>

        <div class="flex flex-wrap gap-2">
            <a class="btn btn-secondary" href="{{ route('admin.orders.invoice', $order) }}" target="_blank">
                <x-icon name="invoice" /> Invoice
            </a>
            <a class="btn btn-secondary" href="{{ route('admin.orders.packing-slip', $order) }}" target="_blank">
                <x-icon name="packing-slip" /> Packing slip
            </a>
            @if($isPending)
                <form method="POST" action="{{ route('admin.orders.approve', $order) }}">
                    @csrf
                    <button class="btn btn-primary" type="submit" @disabled($acceptedCount === 0)>
                        <x-icon name="check" /> Approve remaining
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.orders.reject', $order) }}" class="flex flex-wrap gap-2">
                    @csrf
                    <input class="input" name="reason" placeholder="Reject whole order reason" required>
                    <button class="btn btn-danger" type="submit">
                        <x-icon name="x" /> Reject order
                    </button>
                </form>
            @endif
            @if($order->status === \App\Enums\OrderStatus::Preparing || $order->status === \App\Enums\OrderStatus::Approved)
                <form method="POST" action="{{ route('admin.orders.status', $order) }}">
                    @csrf
                    <input type="hidden" name="status" value="{{ \App\Enums\OrderStatus::OnTheWay->value }}">
                    <button class="btn btn-primary" type="submit">
                        <x-icon name="refresh" /> Mark on the way
                    </button>
                </form>
                <form
                    method="POST"
                    action="{{ route('admin.orders.undo-approve', $order) }}"
                    onsubmit="return confirm('Undo approval? The order will return to Pending Approval and stock will be re-reserved.')"
                >
                    @csrf
                    <button class="btn btn-secondary" type="submit">
                        <x-icon name="undo" /> Undo approval
                    </button>
                </form>
            @endif
            @if($order->status === \App\Enums\OrderStatus::OnTheWay)
                <form method="POST" action="{{ route('admin.orders.status', $order) }}">
                    @csrf
                    <input type="hidden" name="status" value="{{ \App\Enums\OrderStatus::Delivered->value }}">
                    <button class="btn btn-primary" type="submit">
                        <x-icon name="check" /> Mark delivered
                    </button>
                </form>
            @endif
            @if($order->status === \App\Enums\OrderStatus::Returned)
                <form method="POST" action="{{ route('admin.orders.status', $order) }}">
                    @csrf
                    <input type="hidden" name="status" value="{{ \App\Enums\OrderStatus::Refunded->value }}">
                    <button class="btn btn-secondary" type="submit">
                        <x-icon name="payment" /> Mark refunded
                    </button>
                </form>
            @endif
            @if(in_array($order->status, [
                \App\Enums\OrderStatus::Approved,
                \App\Enums\OrderStatus::Preparing,
                \App\Enums\OrderStatus::OnTheWay,
            ], true))
                <form
                    method="POST"
                    action="{{ route('admin.orders.status', $order) }}"
                    onsubmit="return confirm('Cancel this order?')"
                >
                    @csrf
                    <input type="hidden" name="status" value="{{ \App\Enums\OrderStatus::Cancelled->value }}">
                    <button class="btn btn-danger" type="submit">
                        <x-icon name="x" /> Cancel order
                    </button>
                </form>
            @endif
            @if($order->payment_status !== \App\Enums\PaymentStatus::Paid)
                <form method="POST" action="{{ route('admin.orders.mark-paid', $order) }}">
                    @csrf
                    <button class="btn btn-secondary" type="submit">
                        <x-icon name="payment" /> Mark payment paid
                    </button>
                </form>
            @elseif($order->payment_status === \App\Enums\PaymentStatus::Paid)
                <form
                    method="POST"
                    action="{{ route('admin.orders.unmark-paid', $order) }}"
                    onsubmit="return confirm('Unmark payment? This will set payment back to pending / awaiting confirmation.')"
                >
                    @csrf
                    <button class="btn btn-secondary" type="submit">
                        <x-icon name="undo" /> Unmark payment
                    </button>
                </form>
            @endif
        </div>

        @if(in_array($order->status, [\App\Enums\OrderStatus::OnTheWay, \App\Enums\OrderStatus::Delivered], true))
            <form method="POST" action="{{ route('admin.orders.return', $order) }}" class="border border-beige p-5 space-y-3">
                @csrf
                <h2 class="font-display text-xl">Confirm return</h2>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="resellable" value="1" checked> Restock as resellable</label>
                <input class="input" name="note" placeholder="Note">
                <button class="btn btn-secondary" type="submit">
                    <x-icon name="return" /> Process return
                </button>
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
                <div class="flex flex-wrap items-center gap-2 py-1">
                    <x-badge :tone="$history->to_status->tone()">{{ $history->to_status->label() }}</x-badge>
                    <span class="text-taupe">{{ $history->created_at->format('Y-m-d H:i') }}</span>
                    @if($history->note)<span>— {{ $history->note }}</span>@endif
                </div>
            @endforeach
        </div>
        <form method="POST" action="{{ route('admin.orders.notes', $order) }}" class="border border-beige p-5 bg-[#FFFCFA] space-y-3">
            @csrf
            <h2 class="font-display text-xl">Add note</h2>
            <textarea name="body" class="input" rows="3" required></textarea>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_customer_visible" value="1"> Visible to customer</label>
            <button class="btn btn-secondary" type="submit">
                <x-icon name="note" /> Save note
            </button>
        </form>
        @foreach($order->notes as $note)
            <div class="text-sm border-b border-beige py-2">{{ $note->body }} <span class="text-taupe">{{ $note->is_customer_visible ? '(customer)' : '(internal)' }}</span></div>
        @endforeach
    </div>
</div>

@if($isPending)
<script>
    document.querySelectorAll('.reject-item-check').forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            const reason = document.querySelector(`[data-reason-for="${checkbox.value}"]`);
            if (! reason) return;
            reason.disabled = ! checkbox.checked;
            if (! checkbox.checked) reason.value = '';
        });
    });

    document.getElementById('reject-items-form')?.addEventListener('submit', (event) => {
        const checked = document.querySelectorAll('.reject-item-check:checked');
        if (checked.length === 0) {
            event.preventDefault();
            alert('Select at least one item to reject.');
        }
    });
</script>
@endif
@endsection
