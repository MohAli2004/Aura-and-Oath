<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Packing slip {{ $order->order_number }}</title>
    @php $page = print_page_dims('packing_slip'); @endphp
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

        .doc-title {
            font-size: {{ $page['compact'] ? '22px' : '26px' }};
            font-weight: 500;
            letter-spacing: 0.02em;
            margin: 0;
        }

        .doc-sub {
            font-family: 'Segoe UI', sans-serif;
            font-size: {{ $page['compact'] ? '11px' : '12px' }};
            color: #8B7E74;
            margin: {{ $page['compact'] ? '4px' : '6px' }} 0 0;
        }

        .header-meta {
            font-family: 'Segoe UI', sans-serif;
            text-align: right;
            line-height: 1.45;
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
            line-height: 1.5;
        }

        .meta-grid .value { color: #2C2A28; }

        .tracking {
            margin: {{ $page['compact'] ? '12px' : '16px' }} 0 0;
            font-family: 'Segoe UI', sans-serif;
            font-size: {{ $page['compact'] ? '12px' : '13px' }};
        }

        .tracking strong {
            font-weight: 600;
            letter-spacing: 0.02em;
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
            letter-spacing: 0.12em;
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

        .picked {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 1px solid #2C2A28;
            vertical-align: middle;
        }

        .footer-note {
            margin-top: auto;
            padding-top: {{ $page['compact'] ? '12px' : '18px' }};
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
    <button type="button" onclick="window.print()">Print packing slip</button>
</div>

<div class="sheet">
    <div class="sheet-body">
        <div class="header">
            <div>
                <h1 class="doc-title">Packing Slip</h1>
                <p class="doc-sub">{{ setting('store_name', config('aura.name')) }}</p>
            </div>
            <div class="header-meta">
                <p class="order-no">{{ $order->order_number }}</p>
                @if(print_shows('packing_slip', 'order_date') && $order->created_at)
                    <p class="meta-line">{{ $order->created_at->format('Y-m-d') }}</p>
                    <p class="meta-line">{{ $order->created_at->format('H:i') }}</p>
                @endif
            </div>
        </div>

        @if(
            (print_shows('packing_slip', 'customer_name') || print_shows('packing_slip', 'ship_to') || print_shows('packing_slip', 'phone'))
            && $order->shippingAddress
        )
            <div class="meta-grid">
                @if(print_shows('packing_slip', 'customer_name') || (print_shows('packing_slip', 'phone') && $order->shippingAddress->phone))
                    <div>
                        @if(print_shows('packing_slip', 'customer_name'))
                            <p class="label">Customer</p>
                            <div class="value">{{ $order->shippingAddress->full_name }}</div>
                        @endif
                        @if(print_shows('packing_slip', 'phone') && $order->shippingAddress->phone)
                            <p class="label" style="margin-top: 12px;">Phone</p>
                            <div class="value">{{ $order->shippingAddress->phone }}</div>
                        @endif
                    </div>
                @endif

                @if(print_shows('packing_slip', 'ship_to'))
                    <div>
                        <p class="label">Ship to</p>
                        <div class="value">{{ $order->shippingAddress->formatted() }}</div>
                    </div>
                @endif
            </div>
        @endif

        @if(print_shows('packing_slip', 'tracking') && $order->tracking_number)
            <p class="tracking"><strong>Tracking</strong> · {{ $order->tracking_number }}</p>
        @endif

        @php
            $columns = collect([
                'sku' => ['label' => 'SKU', 'class' => ''],
                'barcode' => ['label' => 'Barcode', 'class' => ''],
                'item' => ['label' => 'Item', 'class' => ''],
                'quantity' => ['label' => 'Qty', 'class' => 'center'],
                'picked' => ['label' => 'Picked', 'class' => 'center'],
            ])->filter(fn ($col, $key) => print_shows('packing_slip', $key));
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
                                    @case('sku') {{ $item->sku }} @break
                                    @case('barcode') {{ $item->barcode }} @break
                                    @case('item')
                                        <span class="item-name">{{ $item->product_name }}</span>
                                        @if($item->variant_name)
                                            <span class="item-variant">{{ $item->variant_name }}</span>
                                        @endif
                                        @break
                                    @case('quantity') {{ $item->quantity }} @break
                                    @case('picked') <span class="picked" aria-hidden="true"></span> @break
                                @endswitch
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <p class="footer-note">Check each item before sealing</p>
</div>
</body>
</html>
