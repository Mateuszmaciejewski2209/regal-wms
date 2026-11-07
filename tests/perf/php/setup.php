<?php

/*
 * Dane testowe pomiarów (towary „PERF-…”, szkice dokumentów) tworzone przez serwisy aplikacji – tak jak robi to seeder.
 *
 *   php setup.php products <prefiks> <liczba> [cena]
 *   php setup.php drafts <liczba> <typ> <kod magazynu> <e-mail autora> <uwagi> [SKU=ilość ...]
 *   php setup.php doc-items <id> [<id> ...]
 *   php setup.php scan-events <nazwa urządzenia>
 *   php setup.php lookup-skus <liczba>
 */

require __DIR__.'/bootstrap.php';

use App\Enums\DocumentType;
use App\Enums\Unit;
use App\Models\Contractor;
use App\Models\Document;
use App\Models\Product;
use App\Models\ScanEvent;
use App\Models\ScannerDevice;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Documents\DocumentService;

$cmd = $argv[1] ?? '';
$args = array_slice($argv, 2);

switch ($cmd) {
    case 'products':
        [$prefix, $count] = $args;
        $price = $args[2] ?? '10.00';
        $list = [];
        for ($i = 1; $i <= (int) $count; $i++) {
            $sku = sprintf('PERF-%s-%03d', $prefix, $i);
            $product = Product::query()->firstOrCreate(['sku' => $sku], [
                'name' => sprintf('PERF %s towar %03d', $prefix, $i),
                'unit' => Unit::Piece,
                'default_price' => $price,
                'min_stock' => 0,
                'vat_rate' => 23,
                'is_active' => true,
            ]);
            $list[] = ['id' => $product->id, 'sku' => $product->sku, 'name' => $product->name];
        }
        out(['products' => $list]);
        break;

    case 'drafts':
        [$n, $type, $warehouseCode, $authorEmail, $notes] = $args;
        $lines = array_slice($args, 5);
        $type = DocumentType::from($type);
        $warehouse = Warehouse::query()->where('code', $warehouseCode)->firstOrFail();
        $author = User::query()->where('email', $authorEmail)->firstOrFail();
        $contractor = Contractor::query()->where('is_active', true)->orderBy('id')->firstOrFail();
        $service = app(DocumentService::class);
        $ids = [];
        for ($i = 1; $i <= (int) $n; $i++) {
            $document = $service->createDraft($type, [
                'warehouse_id' => $warehouse->id,
                'contractor_id' => $type->requiresContractor() ? $contractor->id : null,
                'notes' => (int) $n > 1 ? "{$notes} #{$i}" : $notes,
            ], $author);
            foreach ($lines as $line) {
                [$sku, $qty] = explode('=', $line);
                $service->addProduct($document, Product::query()->where('sku', $sku)->firstOrFail(), $qty);
            }
            $ids[] = $document->id;
        }
        out(['ids' => $ids]);
        break;

    case 'doc-items':
        $docs = [];
        foreach ($args as $id) {
            $document = Document::query()->with('items.product')->findOrFail((int) $id);
            $docs[$id] = [
                'status' => $document->status->value,
                'number' => $document->number,
                'items' => $document->items->map(fn ($item) => ['sku' => $item->product->sku, 'quantity' => (string) $item->quantity])->values()->all(),
            ];
        }
        out(['documents' => $docs]);
        break;

    case 'scan-events':
        $device = ScannerDevice::query()->where('name', $args[0])->latest('id')->firstOrFail();
        $events = ScanEvent::query()->where('scanner_device_id', $device->id)->orderBy('id')->get()
            ->map(fn ($e) => ['id' => $e->id, 'code' => $e->code, 'document_id' => $e->document_id, 'result' => $e->result, 'quantity' => (string) $e->quantity, 'created_at' => (string) $e->created_at])
            ->all();
        out(['device_id' => $device->id, 'events' => $events]);
        break;

    case 'lookup-skus':
        $skus = Product::query()->where('sku', 'not like', 'PERF-%')->where('is_active', true)->orderBy('id')->limit((int) $args[0])->pluck('sku')->all();
        out(['skus' => $skus]);
        break;

    default:
        fwrite(STDERR, "Nieznane polecenie: {$cmd}\n");
        exit(1);
}
