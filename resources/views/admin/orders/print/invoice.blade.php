<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $order->order_number }}</title>
    <style>
        body { font-family: Georgia, 'Times New Roman', serif; padding: 40px; color: #2C2A28; }
        .brand { font-size: 28px; margin: 0; }
        .muted { color: #8B7E74; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th, td { border-bottom: 1px solid #E8DFD4; padding: 10px 8px; text-align: left; font-family: 'Segoe UI', sans-serif; font-size: 14px; }
        .totals { margin-top: 24px; width: 280px; margin-left: auto; font-family: 'Segoe UI', sans-serif; font-size: 14px; }
        .totals div { display: flex; justify-content: space-between; padding: 4px 0; }
        .totals .grand { font-weight: 600; border-top: 1px solid #E8DFD4; margin-top: 8px; padding-top: 8px; }
        .no-print { margin-bottom: 16px; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body>
<div class="no-print"><button onclick="window.print()">Print invoice</button></div>
<h1 class="brand">Aura &amp; Oath</h1>
<p class="muted">Tax invoice / receipt</p>
<h2>{{ $order->order_number }}</h2>
<p>
    {{ $order->customer_name }}<br>
    {{ $order->customer_email }}<br>
    {{ $order->customer_phone }}<br>
    {{ $order->created_at?->format('Y-m-d H:i') }}
</p>
@if($order->shippingAddress)
    <p><strong>Ship to:</strong><br>{{ $order->shippingAddress->full_name }}<br>{{ $order->shippingAddress->formatted() }}</p>
@endif
<table>
    <thead>
    <tr><th>Item</th><th>SKU</th><th>Qty</th><th>Price</th><th>Total</th></tr>
    </thead>
    <tbody>
    @foreach($order->items as $item)
        <tr>
            <td>{{ $item->product_name }}@if($item->variant_name) — {{ $item->variant_name }}@endif</td>
            <td>{{ $item->sku }}</td>
            <td>{{ $item->quantity }}</td>
            <td>{{ money($item->unit_price) }}</td>
            <td>{{ money($item->line_total) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
<div class="totals">
    <div><span>Subtotal</span><span>{{ money($order->subtotal) }}</span></div>
    <div><span>Discount</span><span>{{ money($order->discount_amount) }}</span></div>
    <div><span>Delivery</span><span>{{ money($order->delivery_fee) }}</span></div>
    <div><span>Tax</span><span>{{ money($order->tax_amount) }}</span></div>
    <div class="grand"><span>Total</span><span>{{ money($order->total) }}</span></div>
</div>
<script>window.addEventListener('load', () => setTimeout(() => window.print(), 250));</script>
</body>
</html>
