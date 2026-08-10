<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Orders list</title>
    <style>
        @page { size: A4 portrait; margin: 12mm; }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            color: #2C2A28;
            background: #F0EBE4;
            font-family: Georgia, 'Times New Roman', serif;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .toolbar {
            max-width: 210mm;
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
            width: 210mm;
            min-height: 297mm;
            margin: 16px auto 24px;
            padding: 14mm 12mm;
            background: #fff;
            box-shadow: 0 2px 16px rgba(44, 42, 40, 0.12);
        }

        h1 {
            margin: 0 0 4px;
            font-size: 22px;
            font-weight: normal;
            letter-spacing: 0.04em;
        }

        .meta {
            margin: 0 0 18px;
            font-size: 12px;
            color: #6B635C;
            font-family: 'Segoe UI', sans-serif;
        }

        .meta strong { color: #2C2A28; font-weight: 600; }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            font-family: 'Segoe UI', sans-serif;
        }

        th, td {
            padding: 7px 8px;
            text-align: left;
            border-bottom: 1px solid #E8E0D8;
            vertical-align: top;
        }

        th {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #6B635C;
            border-bottom: 1.5px solid #C4B8AE;
        }

        .num { white-space: nowrap; font-variant-numeric: tabular-nums; }
        .right { text-align: right; }
        .muted { color: #6B635C; font-size: 10px; }

        .empty {
            padding: 24px 0;
            text-align: center;
            color: #6B635C;
            font-family: 'Segoe UI', sans-serif;
            font-size: 13px;
        }

        .footer {
            margin-top: 16px;
            font-size: 11px;
            color: #6B635C;
            font-family: 'Segoe UI', sans-serif;
        }

        @media print {
            html, body { background: #fff; }
            .toolbar { display: none; }
            .sheet {
                margin: 0;
                padding: 0;
                box-shadow: none;
                width: auto;
                min-height: 0;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print</button>
    </div>

    <div class="sheet">
        <h1>Orders</h1>
        <p class="meta">
            @if($statusFilter)
                Status: <strong>{{ $statusFilter->label() }}</strong>
            @else
                Status: <strong>All</strong>
            @endif
            @if($search)
                · Search: <strong>{{ $search }}</strong>
            @endif
            · Printed {{ $printedAt->format('Y-m-d H:i') }}
            · {{ $orders->count() }} order{{ $orders->count() === 1 ? '' : 's' }}
        </p>

        @if($orders->isEmpty())
            <p class="empty">No orders match this filter.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th class="right">Total</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($orders as $order)
                        <tr>
                            <td class="num">{{ $order->order_number }}</td>
                            <td>
                                {{ $order->customer_name }}
                                @if($order->customer_email)
                                    <div class="muted">{{ $order->customer_email }}</div>
                                @endif
                            </td>
                            <td class="num">{{ $order->customer_phone ?: '—' }}</td>
                            <td>{{ $order->status->label() }}</td>
                            <td>{{ $order->payment_status->label() }}</td>
                            <td class="right num">{{ money($order->total) }}</td>
                            <td class="num">{{ $order->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="footer">
                Totals shown: {{ money($orders->sum('total')) }}
                @if($orders->count() >= 500)
                    · Showing first 500 matching orders
                @endif
            </p>
        @endif
    </div>

    <script>
        window.addEventListener('load', function () {
            if (new URLSearchParams(window.location.search).get('autoprint') === '1') {
                window.print();
            }
        });
    </script>
</body>
</html>
