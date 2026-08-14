@extends('layouts.admin')
@section('heading', 'Order '.$order->order_number)
@section('title', $order->order_number)
@section('content')
@php
    $isPending = $order->status === \App\Enums\OrderStatus::PendingApproval;
    $acceptedCount = $order->items->filter(fn ($item) => $item->isAccepted())->count();
    $isPaid = $order->payment_status === \App\Enums\PaymentStatus::Paid;
    $canTogglePaid = $order->canTogglePayment();
@endphp

<style>
    .order-show-page .ctrl {
        height: 2.5rem;
        min-height: 2.5rem;
        box-sizing: border-box;
    }
    .order-show-page .ctrl.input {
        padding-top: 0;
        padding-bottom: 0;
        line-height: 2.5rem;
    }
    .order-show-page .ctrl.btn {
        min-height: 2.5rem;
        padding-top: 0;
        padding-bottom: 0;
    }
    .order-show-page .item-thumb {
        width: 3.5rem;
        height: 3.5rem;
    }
</style>

<div class="order-show-page grid lg:grid-cols-[1.4fr_1fr] gap-8">
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

            @if($canTogglePaid)
                <form
                    method="POST"
                    action="{{ $isPaid ? route('admin.orders.unmark-paid', $order) : route('admin.orders.mark-paid', $order) }}"
                    class="mb-4 flex items-center gap-2 text-sm border border-beige px-3 h-10 bg-ivory/50"
                    @if($isPaid) onsubmit="return confirm('Unmark payment? This order will move back to Active orders.')" @endif
                >
                    @csrf
                    <input
                        type="checkbox"
                        id="order-paid-checkbox"
                        class="h-4 w-4 accent-charcoal cursor-pointer"
                        @checked($isPaid)
                        onchange="this.form.requestSubmit()"
                    >
                    <label for="order-paid-checkbox" class="cursor-pointer select-none">
                        {{ $isPaid ? 'Paid — appears in Finished orders' : 'Mark as paid (moves to Finished orders)' }}
                    </label>
                </form>
            @else
                <p class="mb-4 text-sm text-taupe">
                    @if($order->status === \App\Enums\OrderStatus::PendingApproval)
                        Approve this order before you can mark it as paid.
                    @else
                        Payment cannot be changed for {{ strtolower($order->status->label()) }} orders.
                    @endif
                </p>
            @endif

            @foreach($order->items as $index => $item)
                @php
                    $images = app(\App\Services\ImageService::class);
                    $imagePath = $item->variant?->image_path ?: $item->product?->primaryImagePath();
                    $imageUrl = $images->url($imagePath);
                    $rejected = $item->isRejected();
                    $returned = $item->isReturned();
                    $stockable = $item->variant ?: $item->product;
                    $availableExtra = $stockable
                        ? max(0, (int) $stockable->stock_quantity - (int) $stockable->reserved_quantity)
                        : 0;
                    $maxQty = $item->product?->track_inventory
                        ? ($item->quantity + $availableExtra)
                        : 9999;
                @endphp
                <div class="flex flex-col gap-3 border-t border-beige py-4 text-sm sm:flex-row sm:justify-between {{ $rejected || $returned ? 'opacity-70' : '' }}">
                    <div class="flex gap-3 min-w-0 flex-1">
                        @if($isPending && ! $rejected)
                            <label class="flex h-14 items-center shrink-0">
                                <input
                                    type="checkbox"
                                    form="reject-items-form"
                                    name="items[{{ $index }}][id]"
                                    value="{{ $item->id }}"
                                    class="reject-item-check h-4 w-4"
                                >
                            </label>
                        @endif
                        <div class="item-thumb shrink-0 overflow-hidden border border-beige bg-beige/30">
                            <img src="{{ $imageUrl }}" alt="{{ $item->product_name }}" class="h-full w-full object-cover">
                        </div>
                        <div class="min-w-0 flex-1 space-y-2">
                            <div class="flex flex-wrap items-center gap-2 min-h-6">
                                <span class="{{ $rejected ? 'line-through' : '' }}">
                                    {{ $item->product_name }} @if($item->variant_name)— {{ $item->variant_name }}@endif
                                </span>
                                @if($rejected)
                                    <x-badge tone="danger">Rejected</x-badge>
                                @endif
                                @if($returned)
                                    <x-badge>Returned</x-badge>
                                @endif
                            </div>
                            <div class="text-taupe text-xs leading-5">
                                {{ $item->sku }} · {{ $item->barcode }} · qty {{ $item->quantity }} · {{ money($item->unit_price) }} each
                            </div>
                            @if($rejected && $item->rejection_reason)
                                <div class="text-xs text-[#B85C5C] leading-5">{{ $item->rejection_reason }}</div>
                            @endif

                            @if($isPending && ! $rejected)
                                <div class="space-y-2" x-data="{ editing: false }">
                                    <button
                                        type="button"
                                        class="btn btn-secondary ctrl"
                                        x-show="!editing"
                                        @click="editing = true; $nextTick(() => $refs.qty?.focus())"
                                    >
                                        <x-icon name="note" /> Edit amount
                                    </button>

                                    <form
                                        method="POST"
                                        action="{{ route('admin.orders.items.quantity', [$order, $item]) }}"
                                        class="flex flex-wrap items-center gap-2"
                                        x-show="editing"
                                        x-cloak
                                    >
                                        @csrf
                                        <input
                                            type="number"
                                            name="quantity"
                                            min="1"
                                            max="{{ $maxQty }}"
                                            value="{{ $item->quantity }}"
                                            class="input ctrl w-20"
                                            title="Quantity"
                                            required
                                            x-ref="qty"
                                        >
                                        <input
                                            type="text"
                                            name="note"
                                            class="input ctrl min-w-[12rem] flex-1"
                                            placeholder="Note to customer (optional)"
                                        >
                                        <button
                                            type="submit"
                                            class="btn btn-primary ctrl"
                                            onclick="return confirm('Update quantity and notify the customer?')"
                                        >
                                            Save
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-secondary ctrl"
                                            @click="editing = false"
                                        >
                                            Cancel
                                        </button>
                                    </form>
                                    <p
                                        class="text-[11px] text-taupe leading-4"
                                        x-show="editing"
                                        x-cloak
                                    >
                                        @if($item->product?->track_inventory)
                                            Available to add: {{ $availableExtra }} · Max: {{ $maxQty }}
                                        @else
                                            Change quantity, then save to notify the customer.
                                        @endif
                                    </p>
                                </div>
                                <input
                                    type="text"
                                    form="reject-items-form"
                                    name="items[{{ $index }}][reason]"
                                    class="input ctrl"
                                    placeholder="Reject reason (optional)"
                                    disabled
                                    data-reason-for="{{ $item->id }}"
                                >
                            @endif

                            @if($isPending && $rejected)
                                <form method="POST" action="{{ route('admin.orders.items.restore', [$order, $item]) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-secondary ctrl">
                                        <x-icon name="undo" /> Restore item
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                    <div class="shrink-0 flex h-14 items-center tabular-nums {{ $rejected || $returned ? 'line-through text-taupe' : '' }}">
                        {{ money($item->line_total) }}
                    </div>
                </div>
            @endforeach

            <div class="mt-2 border-t border-beige pt-4 text-sm space-y-2">
                <div class="flex h-6 justify-between items-center gap-4"><span class="text-taupe">Subtotal</span><span class="tabular-nums">{{ money($order->subtotal) }}</span></div>
                @if((float) $order->discount_amount > 0)
                    <div class="flex h-6 justify-between items-center gap-4"><span class="text-taupe">Discount</span><span class="tabular-nums">−{{ money($order->discount_amount) }}</span></div>
                @endif
                <div class="flex h-6 justify-between items-center gap-4"><span class="text-taupe">Delivery</span><span class="tabular-nums">{{ money($order->delivery_fee) }}</span></div>
                @if((float) $order->tax_amount > 0)
                    <div class="flex h-6 justify-between items-center gap-4"><span class="text-taupe">Tax</span><span class="tabular-nums">{{ money($order->tax_amount) }}</span></div>
                @endif
                <div class="flex h-8 justify-between items-center gap-4 border-t border-beige pt-2 font-medium">
                    <span>Total</span><span class="tabular-nums">{{ money($order->total) }}</span>
                </div>
            </div>

            @if($isPending)
                <form
                    method="POST"
                    id="reject-items-form"
                    action="{{ route('admin.orders.reject-items', $order) }}"
                    class="mt-4 flex flex-wrap items-center gap-2 border-t border-beige pt-4"
                >
                    @csrf
                    <button
                        type="submit"
                        class="btn btn-danger ctrl"
                        onclick="return confirm('Reject selected items? Stock will be released and totals recalculated.')"
                    >
                        <x-icon name="x" /> Reject selected items
                    </button>
                    <p class="text-xs text-taupe">Check items to exclude, then reject them before approving the rest.</p>
                </form>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a class="btn btn-secondary ctrl" href="{{ route('admin.orders.invoice', $order) }}" target="_blank">
                <x-icon name="invoice" /> Invoice
            </a>
            <a class="btn btn-secondary ctrl" href="{{ route('admin.orders.packing-slip', $order) }}" target="_blank">
                <x-icon name="packing-slip" /> Packing slip
            </a>
            @if($isPending)
                <form method="POST" action="{{ route('admin.orders.approve', $order) }}">
                    @csrf
                    <button class="btn btn-primary ctrl" type="submit" @disabled($acceptedCount === 0)>
                        <x-icon name="check" /> Approve remaining
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.orders.reject', $order) }}" class="flex flex-wrap items-center gap-2">
                    @csrf
                    <input class="input ctrl w-56" name="reason" placeholder="Reject whole order reason" required>
                    <button class="btn btn-danger ctrl" type="submit">
                        <x-icon name="x" /> Reject order
                    </button>
                </form>
            @endif
            @if($order->status === \App\Enums\OrderStatus::Preparing || $order->status === \App\Enums\OrderStatus::Approved)
                <form method="POST" action="{{ route('admin.orders.status', $order) }}">
                    @csrf
                    <input type="hidden" name="status" value="{{ \App\Enums\OrderStatus::OnTheWay->value }}">
                    <button class="btn btn-primary ctrl" type="submit">
                        <x-icon name="refresh" /> Mark on the way
                    </button>
                </form>
                <form
                    method="POST"
                    action="{{ route('admin.orders.undo-approve', $order) }}"
                    onsubmit="return confirm('Undo approval? The order will return to Pending Approval and stock will be re-reserved.')"
                >
                    @csrf
                    <button class="btn btn-secondary ctrl" type="submit">
                        <x-icon name="undo" /> Undo approval
                    </button>
                </form>
            @endif
            @if($order->status === \App\Enums\OrderStatus::OnTheWay)
                <form method="POST" action="{{ route('admin.orders.status', $order) }}">
                    @csrf
                    <input type="hidden" name="status" value="{{ \App\Enums\OrderStatus::Delivered->value }}">
                    <button class="btn btn-primary ctrl" type="submit">
                        <x-icon name="check" /> Mark delivered
                    </button>
                </form>
            @endif
            @if($order->status === \App\Enums\OrderStatus::Returned)
                <form method="POST" action="{{ route('admin.orders.status', $order) }}">
                    @csrf
                    <input type="hidden" name="status" value="{{ \App\Enums\OrderStatus::Refunded->value }}">
                    <button class="btn btn-secondary ctrl" type="submit">
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
                    <button class="btn btn-danger ctrl" type="submit">
                        <x-icon name="x" /> Cancel order
                    </button>
                </form>
            @endif
        </div>

        @if($order->pendingReturnRequest)
            @php $pendingReturn = $order->pendingReturnRequest; @endphp
            <form method="POST" action="{{ route('admin.orders.return', $order) }}" class="border border-beige p-5 space-y-3 bg-[#FBF3E6]">
                @csrf
                <h2 class="font-display text-xl">Customer return request</h2>
                <p class="text-sm">{{ $pendingReturn->reason }}</p>
                @if($pendingReturn->photoUrl())
                    <a href="{{ $pendingReturn->photoUrl() }}" target="_blank" rel="noopener" class="block">
                        <img src="{{ $pendingReturn->photoUrl() }}" alt="Return photo" class="max-h-48 w-full object-cover border border-beige">
                    </a>
                @endif
                <ul class="text-sm text-taupe space-y-1">
                    @foreach($pendingReturn->items as $row)
                        <li>
                            {{ $row->orderItem?->product_name }}
                            @if($row->orderItem?->variant_name)— {{ $row->orderItem->variant_name }}@endif
                            · qty {{ $row->quantity }}
                        </li>
                    @endforeach
                </ul>
                <label class="flex items-center gap-2 text-sm h-10">
                    <input type="checkbox" name="resellable" value="1" checked class="h-4 w-4"> Restock as resellable
                </label>
                <input class="input ctrl" name="note" placeholder="Note to customer (optional)">
                <button class="btn btn-secondary ctrl" type="submit">
                    <x-icon name="return" /> Approve return
                </button>
            </form>
            <form method="POST" action="{{ route('admin.orders.return.decline', $order) }}" class="border border-beige p-5 space-y-3">
                @csrf
                <input class="input ctrl" name="note" placeholder="Decline reason (optional)">
                <button class="btn btn-danger ctrl" type="submit" onclick="return confirm('Decline this return request?')">
                    <x-icon name="x" /> Decline return
                </button>
            </form>
        @elseif(in_array($order->status, [\App\Enums\OrderStatus::OnTheWay, \App\Enums\OrderStatus::Delivered], true))
            <form method="POST" action="{{ route('admin.orders.return', $order) }}" class="border border-beige p-5 space-y-3">
                @csrf
                <h2 class="font-display text-xl">Confirm return</h2>
                <label class="flex items-center gap-2 text-sm h-10">
                    <input type="checkbox" name="resellable" value="1" checked class="h-4 w-4"> Restock as resellable
                </label>
                <input class="input ctrl" name="note" placeholder="Note">
                <button class="btn btn-secondary ctrl" type="submit">
                    <x-icon name="return" /> Process return
                </button>
            </form>
        @endif
    </div>

    <div class="space-y-6">
        <div class="border border-beige p-5 bg-[#FFFCFA] text-sm">
            <h2 class="font-display text-xl mb-3">Addresses</h2>
            @foreach($order->addresses as $address)
                <div class="mb-3">
                    <div class="uppercase text-xs tracking-widest text-taupe leading-5">{{ $address->type->label() }}</div>
                    <div class="leading-6">{{ $address->full_name }}<br>{{ $address->formatted() }}</div>
                </div>
            @endforeach
        </div>
        <div class="border border-beige p-5 bg-[#FFFCFA] text-sm">
            <h2 class="font-display text-xl mb-3">History</h2>
            @foreach($order->statusHistories as $history)
                <div class="flex flex-wrap items-center gap-2 min-h-8 py-1">
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
            <label class="flex items-center gap-2 text-sm h-10">
                <input type="checkbox" name="is_customer_visible" value="1" class="h-4 w-4"> Visible to customer
            </label>
            <button class="btn btn-secondary ctrl" type="submit">
                <x-icon name="note" /> Save note
            </button>
        </form>
        @foreach($order->notes as $note)
            <div class="text-sm border-b border-beige py-2 min-h-10 flex items-start">
                <div>{{ $note->body }} <span class="text-taupe">{{ $note->is_customer_visible ? '(customer)' : '(internal)' }}</span></div>
            </div>
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
@if(session('refresh_orders_list'))
<script>
    history.replaceState({ orderChanged: true }, '', location.href);
    history.pushState({ orderChanged: true }, '', location.href);
    window.addEventListener('popstate', () => {
        window.location.replace(@js(route('admin.orders.index', [
            'list' => session('orders_list_after_change', 'active'),
        ])));
    });
</script>
@endif
@endsection
