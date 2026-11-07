<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <title>Etykiety QR · Regał WMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        @page { size: A4; margin: 8mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Inter', system-ui, sans-serif; color: #0f172a; background: #f1f3f5; }
        .toolbar { position: sticky; top: 0; display: flex; gap: 12px; align-items: center; padding: 12px 16px; background: #fff; border-bottom: 1px solid #e3e6ea; font-size: 14px; }
        .toolbar button { height: 36px; padding: 0 14px; border-radius: 8px; border: 0; background: #0f172a; color: #fff; font: inherit; font-weight: 500; cursor: pointer; }
        .sheet { width: 210mm; margin: 16px auto; padding: 8mm; background: #fff; display: grid; grid-template-columns: repeat(3, 1fr); gap: 0; }
        .label { height: 35mm; padding: 3mm; border: 0.2mm dashed #cfd4da; display: flex; gap: 3mm; align-items: center; break-inside: avoid; }
        .label svg { width: 27mm; height: 27mm; flex-shrink: 0; }
        .name { font-size: 9pt; font-weight: 600; line-height: 1.2; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        .sku { margin-top: 1.5mm; font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 8pt; }
        .ean { font-family: ui-monospace, monospace; font-size: 7pt; color: #475569; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; padding: 0; width: auto; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Drukuj</button>
        <span>{{ $labels->count() }} etykiet · A4, 3 × 8 · treść QR: <code>RGL:&lt;indeks&gt;</code></span>
    </div>
    <main class="sheet">
        @foreach ($labels as $product)
            <div class="label">
                {!! \App\Support\Qr::svg($product->qrPayload(), 120) !!}
                <div>
                    <div class="name">{{ $product->name }}</div>
                    <div class="sku">{{ $product->sku }}</div>
                    @if ($product->ean)<div class="ean">EAN {{ $product->ean }}</div>@endif
                </div>
            </div>
        @endforeach
    </main>
</body>
</html>
