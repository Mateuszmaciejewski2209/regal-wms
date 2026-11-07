<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <title>Etykiety półek {{ $rack->code }} · Regał WMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        @page { size: A4; margin: 8mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Inter', system-ui, sans-serif; color: #0f172a; background: #f1f3f5; }
        .toolbar { position: sticky; top: 0; display: flex; gap: 12px; align-items: center; padding: 12px 16px; background: #fff; border-bottom: 1px solid #e3e6ea; font-size: 14px; }
        .toolbar button { height: 36px; padding: 0 14px; border-radius: 8px; border: 0; background: #0f172a; color: #fff; font: inherit; font-weight: 500; cursor: pointer; }
        .sheet { width: 210mm; margin: 16px auto; padding: 8mm; background: #fff; display: grid; grid-template-columns: repeat(3, 1fr); }
        .label { height: 35mm; padding: 3mm; border: 0.2mm dashed #cfd4da; display: flex; gap: 3mm; align-items: center; break-inside: avoid; }
        .label svg { width: 27mm; height: 27mm; flex-shrink: 0; }
        .code { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 14pt; font-weight: 700; letter-spacing: -0.02em; }
        .sub { margin-top: 1.5mm; font-size: 8pt; color: #475569; }
        .wh { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: .05em; }
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
        <span>{{ count($labels) }} etykiet półek regału {{ $rack->code }} ({{ $rack->warehouse->code }}) · naklej na krawędź półki, od lewej</span>
    </div>
    <main class="sheet">
        @foreach ($labels as $l)
            <div class="label">
                {!! \App\Support\Qr::svg($l['qr'], 120) !!}
                <div>
                    <div class="wh">{{ $rack->warehouse->code }} · {{ $rack->warehouse->name }}</div>
                    <div class="code">{{ $l['code'] }}</div>
                    <div class="sub">{{ $l['label'] }}</div>
                </div>
            </div>
        @endforeach
    </main>
</body>
</html>
