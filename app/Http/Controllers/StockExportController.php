<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Eksport stanów magazynowych do CSV (separator „;” – poprawnie otwiera się w polskim Excelu). */
class StockExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $warehouseId = $request->integer('warehouse') ?: null;

        $rows = Stock::query()
            ->with(['product.category', 'warehouse'])
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->where('quantity', '!=', 0)
            ->get()
            ->sortBy(fn (Stock $s) => [$s->warehouse->code, $s->product->name]);

        $filename = 'stany-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 dla Excela
            fputcsv($out, ['Magazyn', 'Indeks', 'EAN', 'Nazwa', 'Kategoria', 'Ilość', 'J.m.', 'Cena śr.', 'Wartość'], ';');

            foreach ($rows as $stock) {
                fputcsv($out, [
                    $stock->warehouse->code,
                    $stock->product->sku,
                    $stock->product->ean,
                    $stock->product->name,
                    $stock->product->category?->name,
                    str_replace('.', ',', (string) $stock->quantity),
                    $stock->product->unit->value,
                    str_replace('.', ',', bcadd($stock->averagePrice(), '0', 2)),
                    str_replace('.', ',', bcadd((string) $stock->value, '0', 2)),
                ], ';');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=utf-8']);
    }
}
