@php
    use App\Enums\DocumentType;
    use App\Support\Num;
    $isInventory = $document->type === DocumentType::INW;
@endphp
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <title>{{ $document->displayNumber() }}</title>
    <style>
        @page { margin: 18mm 14mm; }
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 9pt; color: #0f172a; }
        h1 { font-size: 15pt; margin: 0 0 2pt; }
        .muted { color: #64748b; }
        .head { width: 100%; margin-bottom: 14pt; }
        .head td { vertical-align: top; }
        .box { border: 0.6pt solid #cbd5e1; padding: 6pt 8pt; }
        .label { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: .4pt; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 10pt; }
        table.items th { background: #f1f5f9; font-size: 7.5pt; text-align: left; padding: 5pt; border-bottom: 0.8pt solid #94a3b8; }
        table.items td { padding: 5pt; border-bottom: 0.4pt solid #e2e8f0; }
        .r { text-align: right; }
        .total td { border-top: 0.8pt solid #0f172a; font-weight: bold; border-bottom: none; }
        .badge { display: inline-block; padding: 1pt 4pt; border: 0.6pt solid #0f172a; font-weight: bold; }
        .sign { width: 100%; margin-top: 40pt; }
        .sign td { width: 33%; text-align: center; padding-top: 4pt; border-top: 0.6pt solid #94a3b8; font-size: 7.5pt; color: #64748b; }
        .stamp { color: #b91c1c; font-weight: bold; font-size: 12pt; border: 1.2pt solid #b91c1c; padding: 2pt 6pt; display: inline-block; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                <span class="badge">{{ $document->type->value }}</span>
                <h1>{{ $document->type->label() }}</h1>
                <div style="font-size: 11pt;">{{ $document->number ?? 'SZKIC #'.$document->id }}</div>
                @if ($document->status === \App\Enums\DocumentStatus::Cancelled)<div style="margin-top:6pt;"><span class="stamp">ANULOWANY</span></div>@endif
            </td>
            <td class="r">
                <div class="label">Data dokumentu</div>
                <div>{{ $document->issued_at->format('d.m.Y') }}</div>
                @if ($document->external_number)
                    <div class="label" style="margin-top:4pt;">Numer obcy</div>
                    <div>{{ $document->external_number }}</div>
                @endif
                <div class="muted" style="margin-top:6pt;">Regał WMS</div>
            </td>
        </tr>
    </table>

    <table style="width:100%; border-spacing: 6pt 0; margin-left:-6pt;">
        <tr>
            <td class="box" style="width:50%; vertical-align: top;">
                <div class="label">{{ $document->type->requiresTargetWarehouse() ? 'Magazyn źródłowy' : 'Magazyn' }}</div>
                <div><b>{{ $document->warehouse->code }}</b> {{ $document->warehouse->name }}</div>
                <div class="muted">{{ $document->warehouse->address }}</div>
            </td>
            <td class="box" style="width:50%; vertical-align: top;">
                @if ($document->contractor)
                    <div class="label">{{ $document->type->contractorLabel() }}</div>
                    <div><b>{{ $document->contractor->name }}</b></div>
                    <div class="muted">{{ $document->contractor->address() }}</div>
                    @if ($document->contractor->nip)<div class="muted">NIP {{ $document->contractor->formattedNip() }}</div>@endif
                @elseif ($document->targetWarehouse)
                    <div class="label">Magazyn docelowy</div>
                    <div><b>{{ $document->targetWarehouse->code }}</b> {{ $document->targetWarehouse->name }}</div>
                    <div class="muted">{{ $document->targetWarehouse->address }}</div>
                @else
                    <div class="label">Rodzaj</div>
                    <div>{{ $document->type->description() }}</div>
                    @if ($document->parent)<div class="muted">Do dokumentu {{ $document->parent->displayNumber() }}</div>@endif
                @endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width:18pt;">Lp.</th>
                <th>Indeks</th>
                <th>Nazwa towaru</th>
                @if ($isInventory)
                    <th class="r">Stan ewid.</th><th class="r">Spis</th><th class="r">Różnica</th><th>J.m.</th>
                @else
                    <th class="r">Ilość</th><th>J.m.</th><th class="r">Cena netto</th><th class="r">Wartość</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($document->items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->product->sku }}</td>
                    <td>
                        {{ $item->product->name }}
                        @if (! empty($locations[$item->product_id]))
                            <br><span class="muted">Lok.: {{ implode(', ', array_slice($locations[$item->product_id], 0, 4)) }}</span>
                        @endif
                    </td>
                    @if ($isInventory)
                        <td class="r">{{ Num::qty($item->expected_quantity ?? 0) }}</td>
                        <td class="r">{{ $item->quantity === null ? '—' : Num::qty($item->quantity) }}</td>
                        <td class="r">{{ $item->difference() === null ? '—' : Num::signedQty($item->difference()) }}</td>
                        <td>{{ $item->product->unit->value }}</td>
                    @else
                        <td class="r">{{ Num::qty($item->quantity) }}</td>
                        <td>{{ $item->product->unit->value }}</td>
                        <td class="r">{{ $item->unit_price !== null ? Num::money($item->unit_price) : '—' }}</td>
                        <td class="r">{{ Num::money($item->lineValue()) }}</td>
                    @endif
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="{{ $isInventory ? 6 : 6 }}" class="r">{{ $isInventory ? 'Różnice netto' : 'Razem netto' }}</td>
                <td class="r">{{ Num::money($document->total_value) }}</td>
            </tr>
        </tbody>
    </table>

    @if ($document->notes)
        <p style="margin-top:10pt;"><span class="label">Uwagi:</span> {{ $document->notes }}</p>
    @endif

    <table class="sign">
        <tr>
            <td>Wystawił: {{ $document->author->name }}</td>
            <td>Zatwierdził: {{ $document->approver?->name ?? '—' }}</td>
            <td>{{ $document->type->requiresContractor() ? 'Odebrał / wydał' : 'Podpis' }}</td>
        </tr>
    </table>
</body>
</html>
