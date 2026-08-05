<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Barcode labels — Aura & Oath</title>
    <style>
        @page { margin: 12mm; }
        body { font-family: 'Source Sans 3', 'Segoe UI', sans-serif; color: #2C2A28; padding: 12px; }
        h1 { font-family: Georgia, serif; font-weight: 500; font-size: 22px; margin: 0 0 16px; }
        .sheet { display: flex; flex-wrap: wrap; gap: 12px; }
        .label {
            width: 240px;
            border: 1px solid #E8DFD4;
            padding: 16px 14px;
            text-align: center;
            page-break-inside: avoid;
            background: #FFFCFA;
        }
        .brand { font-family: Georgia, serif; font-size: 14px; letter-spacing: 0.04em; margin-bottom: 6px; }
        .name { font-size: 13px; min-height: 36px; line-height: 1.3; }
        .barcode-bars {
            display: flex; align-items: end; justify-content: center; gap: 1px;
            height: 52px; margin: 10px 0 6px;
        }
        .barcode-bars i {
            display: inline-block; background: #2C2A28; width: 2px; height: 100%;
        }
        .barcode-bars i:nth-child(3n) { width: 1px; height: 82%; }
        .barcode-bars i:nth-child(4n) { width: 3px; }
        .barcode-bars i:nth-child(7n) { width: 1px; height: 68%; }
        .code { font-family: ui-monospace, Consolas, monospace; font-size: 15px; letter-spacing: 0.12em; }
        .sku { font-size: 11px; color: #8B7E74; margin-top: 4px; }
        .price { font-size: 12px; margin-top: 6px; }
        .no-print { margin-bottom: 16px; }
        @media print { .no-print { display: none !important; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="no-print">
        <h1>Barcode labels</h1>
        <p><button onclick="window.print()">Print</button></p>
    </div>
    <div class="sheet">
        @forelse($barcodes as $item)
            @php
                $code = (string) ($item['barcode'] ?? '');
                $bars = max(24, strlen($code) * 3);
            @endphp
            <div class="label">
                <div class="brand">Aura &amp; Oath</div>
                <div class="name">{{ $item['name'] }}</div>
                <div class="barcode-bars" aria-hidden="true">
                    @for($i = 0; $i < $bars; $i++)
                        <i></i>
                    @endfor
                </div>
                <div class="code">{{ $code }}</div>
                <div class="sku">SKU {{ $item['sku'] }}</div>
                @php
                    $labelPrice = null;
                    if (! empty($item['variant'])) {
                        $labelPrice = $item['variant']->effectivePrice();
                    } elseif (! empty($item['product'])) {
                        $labelPrice = $item['product']->effectivePrice();
                    }
                @endphp
                @if($labelPrice !== null)
                    <div class="price">{{ money($labelPrice) }}</div>
                @endif
            </div>
        @empty
            <p>No barcodes selected. Use lookup and open Print label, or pass <code>?codes=BARCODE1,BARCODE2</code>.</p>
        @endforelse
    </div>
    @if($barcodes->isNotEmpty())
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 250));</script>
    @endif
</body>
</html>
