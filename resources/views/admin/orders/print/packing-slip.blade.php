<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Packing slip {{ $order->order_number }}</title>
    <style>
        body { font-family: Georgia, serif; padding: 40px; color: #2C2A28; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th, td { border-bottom: 1px solid #E8DFD4; padding: 10px 8px; text-align: left; font-family: 'Segoe UI', sans-serif; font-size: 14px; }
        .box { border: 1px solid #E8DFD4; padding: 16px; margin-top: 12px; }
        .no-print { margin-bottom: 16px; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body>
<div class="no-print"><button onclick="window.print()">Print packing slip</button></div>
<h1>Packing Slip</h1>
<h2>{{ $order->order_number }}</h2>
@if($order->shippingAddress)
    <div class="box">
        <strong>Ship to</strong><br>
        {{ $order->shippingAddress->full_name }}<br>
        {{ $order->shippingAddress->phone }}<br>
        {{ $order->shippingAddress->formatted() }}
    </div>
@endif
@if($order->tracking_number)
    <p>Tracking: {{ $order->tracking_number }}</p>
@endif
<table>
    <thead>
    <tr><th>SKU</th><th>Barcode</th><th>Item</th><th>Qty</th><th>Picked</th></tr>
    </thead>
    <tbody>
    @foreach($order->items as $item)
        <tr>
            <td>{{ $item->sku }}</td>
            <td>{{ $item->barcode }}</td>
            <td>{{ $item->product_name }} {{ $item->variant_name }}</td>
            <td>{{ $item->quantity }}</td>
            <td>☐</td>
        </tr>
    @endforeach
    </tbody>
</table>
<script>window.addEventListener('load', () => setTimeout(() => window.print(), 250));</script>
</body>
</html>
