<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $order->order_number }}</title>
    @php $page = print_page_dims('invoice'); @endphp
    <style>
        @page { size: {{ $page['name'] }} portrait; margin: {{ $page['margin'] }}; }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            color: #2C2A28;
            background: #F0EBE4;
            font-family: Georgia, 'Times New Roman', serif;
            -webkit-print-color-adjust: exact;
            print-color-adjust: economy;
        }

        .toolbar {
            max-width: {{ $page['width'] }};
            margin: 0 auto;
            padding: 16px 0 0;
        }

        .toolbar button {
            font-family: 'Segoe UI', sans-serif;
            font-size: 13px;
            padding: 8px 14px;
            cursor: pointer;
            border: 1px solid #8B7E74;
            background: #fff;
            color: #2C2A28;
        }

        .sheet {
            width: {{ $page['width'] }};
            min-height: {{ $page['height'] }};
            margin: 16px auto 32px;
            padding: {{ $page['margin'] }};
            background: #fff;
            box-shadow: 0 1px 3px rgba(44, 42, 40, 0.08);
            display: flex;
            flex-direction: column;
        }

        .sheet-body { flex: 1 1 auto; }

        .label {
            font-family: 'Segoe UI', sans-serif;
            font-size: {{ $page['compact'] ? '9px' : '10px' }};
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: #8B7E74;
            margin: 0 0 {{ $page['compact'] ? '3px' : '4px' }};
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: {{ $page['compact'] ? '16px' : '24px' }};
            padding-bottom: {{ $page['compact'] ? '10px' : '14px' }};
            border-bottom: 1px solid #2C2A28;
        }

        .brand {
            font-size: {{ $page['compact'] ? '22px' : '26px' }};
            font-weight: 500;
            letter-spacing: 0.02em;
            margin: 0;
        }

        .doc-type {
            font-family: 'Segoe UI', sans-serif;
            font-size: {{ $page['compact'] ? '11px' : '12px' }};
            color: #8B7E74;
            margin: {{ $page['compact'] ? '4px' : '6px' }} 0 0;
        }

        .header-meta {
            font-family: 'Segoe UI', sans-serif;
            text-align: right;
            line-height: 1.4;
        }

        .header-meta .order-no {
            font-size: {{ $page['compact'] ? '13px' : '15px' }};
            font-weight: 600;
            margin: 0 0 {{ $page['compact'] ? '4px' : '6px' }};
        }

        .header-meta .meta-line {
            font-size: {{ $page['compact'] ? '11px' : '12px' }};
            color: #5C534C;
            margin: 0;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: {{ $page['compact'] ? '14px' : '20px' }};
            margin: {{ $page['compact'] ? '14px' : '18px' }} 0 0;
            font-family: 'Segoe UI', sans-serif;
            font-size: {{ $page['compact'] ? '12px' : '13px' }};
            line-height: 1.45;
        }

        .meta-grid .value {
            color: #2C2A28;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: {{ $page['compact'] ? '18px' : '26px' }};
        }

        thead th {
            font-family: 'Segoe UI', sans-serif;
            font-size: {{ $page['compact'] ? '9px' : '10px' }};
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #8B7E74;
            text-align: left;
            padding: 0 {{ $page['compact'] ? '6px' : '8px' }} {{ $page['compact'] ? '6px' : '8px' }};
            border-bottom: 1px solid #2C2A28;
            background: transparent;
        }

        tbody td {
            font-family: 'Segoe UI', sans-serif;
            font-size: {{ $page['compact'] ? '12px' : '13px' }};
            padding: {{ $page['compact'] ? '7px 6px' : '10px 8px' }};
            border-bottom: 1px solid #E5DDD3;
            vertical-align: top;
            background: transparent;
        }

        th.num, td.num { text-align: right; }
        th.center, td.center { text-align: center; }

        .item-name { font-weight: 500; }
        .item-variant {
            display: block;
            margin-top: 2px;
            font-size: {{ $page['compact'] ? '11px' : '12px' }};
            color: #8B7E74;
            font-weight: 400;
        }

        .totals {
            margin-top: auto;
            padding-top: {{ $page['compact'] ? '18px' : '28px' }};
            width: {{ $page['compact'] ? '200px' : '260px' }};
            align-self: flex-end;
            font-family: 'Segoe UI', sans-serif;
            font-size: {{ $page['compact'] ? '12px' : '13px' }};
        }

        .totals .row {
            display: flex;
            justify-content: space-between;
            gap: {{ $page['compact'] ? '16px' : '24px' }};
            padding: {{ $page['compact'] ? '4px' : '5px' }} 0;
            color: #5C534C;
        }

        .totals .row span:last-child {
            color: #2C2A28;
            font-variant-numeric: tabular-nums;
        }

        .totals .grand {
            margin-top: {{ $page['compact'] ? '4px' : '6px' }};
            padding-top: {{ $page['compact'] ? '8px' : '10px' }};
            border-top: 1px solid #2C2A28;
            font-size: {{ $page['compact'] ? '14px' : '15px' }};
            font-weight: 600;
            color: #2C2A28;
        }

        .footer-note {
            margin-top: {{ $page['compact'] ? '12px' : '18px' }};
            padding-top: {{ $page['compact'] ? '8px' : '10px' }};
            border-top: 1px solid #E5DDD3;
            font-family: 'Segoe UI', sans-serif;
            font-size: {{ $page['compact'] ? '10px' : '11px' }};
            color: #8B7E74;
            text-align: center;
        }

        @media print {
            html, body {
                background: #fff !important;
                height: 100%;
            }
            .toolbar { display: none !important; }
            .sheet {
                width: auto;
                min-height: 100%;
                height: 100%;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <button type="button" onclick="window.print()">Print invoice</button>
</div>

<div class="sheet">
    <div class="sheet-body">
        <div class="header">
            <div>
                @if(print_shows('invoice', 'brand'))
                    <h1 class="brand">{{ setting('store_name', config('aura.name')) }}</h1>
                    <p class="doc-type">Tax invoice / receipt</p>
                @else
                    <p class="label">Invoice</p>
                @endif
            </div>
            <div class="header-meta">
                <p class="order-no">{{ $order->order_number }}</p>
                @if(print_shows('invoice', 'order_date') && $order->created_at)
                    <p class="meta-line">{{ $order->created_at->format('Y-m-d') }}</p>
                    <p class="meta-line">{{ $order->created_at->format('H:i') }}</p>
                @endif
            </div>
        </div>

        @if(
            print_shows('invoice', 'customer_name')
            || print_shows('invoice', 'customer_email')
            || print_shows('invoice', 'customer_phone')
            || (print_shows('invoice', 'ship_to') && $order->shippingAddress)
        )
            <div class="meta-grid">
                @if(
                    print_shows('invoice', 'customer_name')
                    || print_shows('invoice', 'customer_email')
                    || print_shows('invoice', 'customer_phone')
                )
                    <div>
                        <p class="label">Bill to</p>
                        <div class="value">
                            @if(print_shows('invoice', 'customer_name'))
                                {{ $order->customer_name }}@if(print_shows('invoice', 'customer_email') || print_shows('invoice', 'customer_phone'))<br>@endif
                            @endif
                            @if(print_shows('invoice', 'customer_email'))
                                {{ $order->customer_email }}@if(print_shows('invoice', 'customer_phone'))<br>@endif
                            @endif
                            @if(print_shows('invoice', 'customer_phone'))
                                {{ $order->customer_phone }}
                            @endif
                        </div>
                    </div>
                @endif

                @if(print_shows('invoice', 'ship_to') && $order->shippingAddress)
                    <div>
                        <p class="label">Ship to</p>
                        <div class="value">{{ $order->shippingAddress->formatted() }}</div>
                    </div>
                @endif
            </div>
        @endif

        @php
            $columns = collect([
                'item' => ['label' => 'Item', 'class' => ''],
                'sku' => ['label' => 'SKU', 'class' => ''],
                'quantity' => ['label' => 'Qty', 'class' => 'center'],
                'unit_price' => ['label' => 'Price', 'class' => 'num'],
                'line_total' => ['label' => 'Total', 'class' => 'num'],
            ])->filter(fn ($col, $key) => print_shows('invoice', $key));
        @endphp

        @if($columns->isNotEmpty())
            <table>
                <thead>
                <tr>
                    @foreach($columns as $col)
                        <th class="{{ $col['class'] }}">{{ $col['label'] }}</th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @foreach($order->items as $item)
                    @continue($item->isRejected())
                    <tr>
                        @foreach($columns->keys() as $key)
                            <td class="{{ $columns[$key]['class'] }}">
                                @switch($key)
                                    @case('item')
                                        <span class="item-name">{{ $item->product_name }}</span>
                                        @if($item->variant_name)
                                            <span class="item-variant">{{ $item->variant_name }}</span>
                                        @endif
                                        @break
                                    @case('sku') {{ $item->sku }} @break
                                    @case('quantity') {{ $item->quantity }} @break
                                    @case('unit_price') {{ money($item->unit_price) }} @break
                                    @case('line_total') {{ money($item->line_total) }} @break
                                @endswitch
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if(
        print_shows('invoice', 'subtotal')
        || print_shows('invoice', 'discount')
        || print_shows('invoice', 'delivery')
        || print_shows('invoice', 'tax')
        || print_shows('invoice', 'total')
    )
        <div class="totals">
            @if(print_shows('invoice', 'subtotal'))
                <div class="row"><span>Subtotal</span><span>{{ money($order->subtotal) }}</span></div>
            @endif
            @if(print_shows('invoice', 'discount'))
                <div class="row"><span>Discount</span><span>{{ money($order->discount_amount) }}</span></div>
            @endif
            @if(print_shows('invoice', 'delivery'))
                <div class="row"><span>Delivery</span><span>{{ money($order->delivery_fee) }}</span></div>
            @endif
            @if(print_shows('invoice', 'tax'))
                <div class="row"><span>Tax</span><span>{{ money($order->tax_amount) }}</span></div>
            @endif
            @if(print_shows('invoice', 'total'))
                <div class="row grand"><span>Total</span><span>{{ money($order->total) }}</span></div>
            @endif
        </div>
    @endif

    <p class="footer-note">Thank you for your order</p>
</div>
</body>
</html>
