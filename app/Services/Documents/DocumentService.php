<?php

namespace App\Services\Documents;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\WebhookEvent;
use App\Events\DocumentChanged;
use App\Events\DocumentItemsChanged;
use App\Exceptions\WarehouseException;
use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use App\Services\Inventory\StockService;
use App\Services\Webhooks\WebhookDispatcher;
use App\Support\Live;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cykl życia dokumentu magazynowego: szkic → zatwierdzony → (opcjonalnie) anulowany/storno.
 *
 * Zatwierdzenie i storno wykonywane są atomowo w jednej transakcji. Zdarzenia
 * (WebSocket, webhooki) wysyłane są dopiero po zatwierdzeniu transakcji.
 */
class DocumentService
{
    /** @var list<array{0: string, 1: Document, 2: array<int, int>}> */
    private array $pending = [];

    public function __construct(
        private readonly StockService $stock,
        private readonly DocumentNumberGenerator $numbers,
        private readonly WebhookDispatcher $webhooks,
    ) {}

    // ───────────────────────── Szkic ─────────────────────────

    public function createDraft(DocumentType $type, array $data, User $author): Document
    {
        // Trwająca inwentaryzacja zamraża magazyn: nie otwieramy nowych dokumentów ani drugiego spisu.
        foreach (array_filter([$data['warehouse_id'], $type->requiresTargetWarehouse() ? ($data['target_warehouse_id'] ?? null) : null]) as $warehouseId) {
            if ($lock = $this->inventoryLock((int) $warehouseId)) {
                throw $type === DocumentType::INW
                    ? WarehouseException::inventoryAlreadyOpen($lock->warehouse->code, $lock->displayNumber())
                    : WarehouseException::inventoryInProgress($lock->warehouse->code, $lock->displayNumber());
            }
        }

        $document = Document::create([
            'type' => $type,
            'status' => DocumentStatus::Draft,
            'warehouse_id' => $data['warehouse_id'],
            'target_warehouse_id' => $type->requiresTargetWarehouse() ? ($data['target_warehouse_id'] ?? null) : null,
            'contractor_id' => $type->requiresContractor() ? ($data['contractor_id'] ?? null) : null,
            'parent_id' => $data['parent_id'] ?? null,
            'issued_at' => $data['issued_at'] ?? now()->toDateString(),
            'external_number' => $data['external_number'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $author->id,
        ]);

        if ($type === DocumentType::INW && ($data['prefill'] ?? false)) {
            $this->prefillInventory($document);
        }

        Live::broadcast(new DocumentChanged($document, 'created'));

        return $document;
    }

    public function updateHeader(Document $document, array $data): Document
    {
        $this->ensureDraft($document);

        $type = $document->type;
        $warehouseId = (int) ($data['warehouse_id'] ?? $document->warehouse_id);

        // Arkusz spisu jest związany z magazynem (stany ewidencyjne) – zmiana magazynu = nowa inwentaryzacja.
        if ($type === DocumentType::INW && $warehouseId !== $document->warehouse_id) {
            throw WarehouseException::inventoryWarehouseFixed();
        }

        // Ta sama blokada co przy tworzeniu: nie można przenieść szkicu do magazynu w trakcie spisu.
        if ($type !== DocumentType::INW) {
            foreach (array_filter([$warehouseId, $type->requiresTargetWarehouse() ? ($data['target_warehouse_id'] ?? null) : null]) as $id) {
                if ($lock = $this->inventoryLock((int) $id)) {
                    throw WarehouseException::inventoryInProgress($lock->warehouse->code, $lock->displayNumber());
                }
            }
        }

        $document->update([
            'warehouse_id' => $warehouseId,
            'target_warehouse_id' => $type->requiresTargetWarehouse() ? ($data['target_warehouse_id'] ?? null) : null,
            'contractor_id' => $type->requiresContractor() ? ($data['contractor_id'] ?? null) : null,
            'issued_at' => $data['issued_at'] ?? $document->issued_at,
            'external_number' => $data['external_number'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return $document;
    }

    /**
     * Dodaje towar do szkicu. Jeżeli pozycja istnieje – zwiększa ilość (zachowanie skanera).
     * Dla INW zwiększana jest ilość policzona.
     */
    public function addProduct(Document $document, Product $product, string $quantity = '1', ?string $unitPrice = null): DocumentItem
    {
        $this->ensureDraft($document);

        if (! $product->is_active) {
            throw WarehouseException::inactiveProduct($product->name);
        }

        if (bccomp($quantity, '0', 3) <= 0) {
            throw WarehouseException::invalidQuantity($product->name);
        }

        $item = DB::transaction(function () use ($document, $product, $quantity, $unitPrice) {
            $item = $document->items()->where('product_id', $product->id)->lockForUpdate()->first();

            if ($item) {
                $item->quantity = bcadd((string) ($item->quantity ?? '0'), $quantity, 3);
                if ($unitPrice !== null) {
                    $item->unit_price = $unitPrice;
                }
                $item->save();

                return $item;
            }

            return $document->items()->create([
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice ?? ($document->type->hasEnteredPrices() ? $product->default_price : null),
                'expected_quantity' => $document->type === DocumentType::INW
                    ? $this->stock->currentQuantity($product->id, $document->warehouse_id)
                    : null,
                'position' => (int) $document->items()->max('position') + 1,
            ]);
        });

        Live::broadcast(new DocumentItemsChanged($document->id));

        return $item->load('product');
    }

    public function updateItem(DocumentItem $item, ?string $quantity, ?string $unitPrice = null): DocumentItem
    {
        $this->ensureDraft($item->document);

        $isInventory = $item->document->type === DocumentType::INW;

        if ($quantity !== null && bccomp($quantity, '0', 3) < 0) {
            throw WarehouseException::invalidQuantity($item->product->name);
        }

        if ($quantity === null && ! $isInventory) {
            throw WarehouseException::invalidQuantity($item->product->name);
        }

        $item->update([
            'quantity' => $quantity,
            'unit_price' => $item->document->type->hasEnteredPrices() ? $unitPrice : $item->unit_price,
        ]);

        Live::broadcast(new DocumentItemsChanged($item->document_id));

        return $item;
    }

    public function removeItem(DocumentItem $item): void
    {
        $this->ensureDraft($item->document);

        $documentId = $item->document_id;
        $item->delete();

        Live::broadcast(new DocumentItemsChanged($documentId));
    }

    /** INW: wstawia wszystkie towary ze stanem w magazynie jako pozycje do policzenia. */
    public function prefillInventory(Document $document): int
    {
        $this->ensureDraft($document);

        $existing = $document->items()->pluck('product_id');
        $position = (int) $document->items()->max('position');

        $stocks = Stock::query()
            ->where('warehouse_id', $document->warehouse_id)
            ->where('quantity', '!=', 0)
            ->whereNotIn('product_id', $existing)
            ->join('products', 'products.id', '=', 'stocks.product_id')
            ->orderBy('products.name')
            ->get(['stocks.product_id', 'stocks.quantity']);

        foreach ($stocks as $stock) {
            $document->items()->create([
                'product_id' => $stock->product_id,
                'quantity' => null,
                'expected_quantity' => $stock->quantity,
                'position' => ++$position,
            ]);
        }

        Live::broadcast(new DocumentItemsChanged($document->id));

        return $stocks->count();
    }

    /** INW: niepoliczone pozycje przyjmują stan ewidencyjny (potwierdzenie zgodności). */
    public function fillInventoryFromBook(Document $document): int
    {
        $this->ensureDraft($document);

        $count = 0;
        foreach ($document->items()->whereNull('quantity')->get() as $item) {
            $item->update(['quantity' => $item->expected_quantity ?? '0']);
            $count++;
        }

        if ($count) {
            Live::broadcast(new DocumentItemsChanged($document->id));
        }

        return $count;
    }

    public function deleteDraft(Document $document): void
    {
        $this->ensureDraft($document);

        $document->delete();

        Live::broadcast(new DocumentChanged($document, 'deleted'));
    }

    // ───────────────────────── Zatwierdzenie ─────────────────────────

    public function approve(Document $document, User $user): Document
    {
        $this->pending = [];

        DB::transaction(function () use ($document, $user) {
            $locked = Document::query()->lockForUpdate()->findOrFail($document->id);
            $this->approveLocked($locked, $user);
        });

        $this->flushEvents();

        return $document->fresh(['items.product', 'children']);
    }

    private function approveLocked(Document $document, User $user): Document
    {
        $this->ensureDraft($document);
        $this->validateHeader($document);
        $this->ensureNotFrozen($document);

        $items = $document->items()->with('product')->get();

        if ($items->isEmpty() && $document->type !== DocumentType::INW) {
            throw WarehouseException::emptyDocument();
        }

        $touched = [];
        $total = '0';

        switch ($document->type->direction()) {
            case 'in':
                foreach ($items as $item) {
                    $quantity = $this->positiveQuantity($item);
                    $price = (string) ($item->unit_price ?? $item->product->default_price ?? '0');
                    $this->stock->receive($item->product, $document->warehouse_id, $quantity, $price, $document);
                    $item->update(['unit_price' => $price]);
                    $total = bcadd($total, bcmul($quantity, $price, 4), 4);
                }
                break;

            case 'out':
                foreach ($items as $item) {
                    $quantity = $this->positiveQuantity($item);
                    $movement = $this->stock->issue($item->product, $document->warehouse_id, $quantity, $document);
                    $item->update(['unit_price' => $movement->unit_price]);
                    $total = bcadd($total, bcmul($quantity, (string) $movement->unit_price, 4), 4);
                    $touched[$item->product_id] = $item->product_id;
                }
                break;

            case 'transfer':
                foreach ($items as $item) {
                    $quantity = $this->positiveQuantity($item);
                    $movement = $this->stock->issue($item->product, $document->warehouse_id, $quantity, $document);
                    $this->stock->receive($item->product, $document->target_warehouse_id, $quantity, (string) $movement->unit_price, $document);
                    $item->update(['unit_price' => $movement->unit_price]);
                    $total = bcadd($total, bcmul($quantity, (string) $movement->unit_price, 4), 4);
                    $touched[$item->product_id] = $item->product_id;
                }
                break;

            case 'inventory':
                $total = $this->settleInventory($document, $items, $user);
                break;
        }

        $document->forceFill([
            'number' => $this->numbers->next($document->type, $document->issued_at),
            'status' => DocumentStatus::Approved,
            'approved_by' => $user->id,
            'approved_at' => now(),
            'total_value' => bcadd($total, '0', 2),
        ])->save();

        $this->pending[] = ['approved', $document, array_values($touched)];

        return $document;
    }

    /**
     * Rozliczenie spisu z natury: nadwyżki → PW, niedobory → RW (dokumenty potomne, zatwierdzane od razu).
     * Pozycje niepoliczone (ilość null) nie zmieniają stanu.
     *
     * @return string wartość netto różnic inwentaryzacyjnych
     */
    private function settleInventory(Document $inventory, Collection $items, User $user): string
    {
        $surplus = [];
        $shortage = [];

        foreach ($items as $item) {
            if ($item->quantity === null) {
                continue;
            }

            $book = $this->stock->currentQuantity($item->product_id, $inventory->warehouse_id);
            $item->update(['expected_quantity' => $book]);
            $difference = bcsub((string) $item->quantity, $book, 3);

            if (bccomp($difference, '0', 3) > 0) {
                $surplus[] = [$item->product, $difference];
            } elseif (bccomp($difference, '0', 3) < 0) {
                $shortage[] = [$item->product, bcmul($difference, '-1', 3)];
            }
        }

        $net = '0';

        if ($surplus) {
            $pw = $this->createChild(DocumentType::PW, $inventory, $user, 'Nadwyżki inwentaryzacyjne', $surplus);
            $net = bcadd($net, (string) $pw->total_value, 2);
        }

        if ($shortage) {
            $rw = $this->createChild(DocumentType::RW, $inventory, $user, 'Niedobory inwentaryzacyjne', $shortage);
            $net = bcsub($net, (string) $rw->total_value, 2);
        }

        return $net;
    }

    /** @param list<array{0: Product, 1: string}> $lines */
    private function createChild(DocumentType $type, Document $parent, User $user, string $notes, array $lines): Document
    {
        $child = Document::create([
            'type' => $type,
            'status' => DocumentStatus::Draft,
            'warehouse_id' => $parent->warehouse_id,
            'parent_id' => $parent->id,
            'issued_at' => $parent->issued_at,
            'notes' => $notes,
            'created_by' => $user->id,
        ]);

        foreach (array_values($lines) as $index => [$product, $quantity]) {
            $average = $this->stock->averagePrice($product->id, $parent->warehouse_id);
            $price = bccomp($average, '0', 4) > 0 ? $average : (string) ($product->default_price ?? '0');

            $child->items()->create([
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $type->hasEnteredPrices() ? $price : null,
                'position' => $index + 1,
            ]);
        }

        return $this->approveLocked($child, $user);
    }

    // ───────────────────────── Anulowanie / storno ─────────────────────────

    /**
     * Szkic → status „anulowany”. Zatwierdzony → storno: ruchy odwrotne po cenach pierwotnych.
     */
    public function cancel(Document $document, User $user): Document
    {
        $this->pending = [];

        DB::transaction(function () use ($document, $user) {
            $locked = Document::query()->lockForUpdate()->findOrFail($document->id);

            if ($locked->isDraft()) {
                $this->markCancelled($locked, $user);

                return;
            }

            if (! $locked->isApproved()) {
                throw WarehouseException::notApproved();
            }

            if ($locked->parent_id && $locked->parent?->type === DocumentType::INW && $locked->parent->isApproved()) {
                throw WarehouseException::generatedDocument();
            }

            $this->ensureNotFrozen($locked);

            $this->reverseLocked($locked, $user);
        });

        $this->flushEvents();

        return $document->fresh();
    }

    private function reverseLocked(Document $document, User $user): void
    {
        if ($document->type === DocumentType::INW) {
            foreach ($document->children()->where('status', DocumentStatus::Approved)->orderByDesc('id')->get() as $child) {
                $this->reverseLocked($child, $user);
            }
        }

        $movements = $document->movements()->where('is_reversal', false)->with('product')->orderByDesc('id')->get();
        $touched = [];

        foreach ($movements as $movement) {
            $quantity = (string) $movement->quantity;
            $price = (string) $movement->unit_price;

            if (bccomp($quantity, '0', 3) > 0) {
                $this->stock->issue($movement->product, $movement->warehouse_id, $quantity, $document, $price, reversal: true);
                $touched[$movement->product_id] = $movement->product_id;
            } else {
                $this->stock->receive($movement->product, $movement->warehouse_id, bcmul($quantity, '-1', 3), $price, $document, reversal: true);
            }
        }

        $this->markCancelled($document, $user, array_values($touched));
    }

    private function markCancelled(Document $document, User $user, array $touched = []): void
    {
        $document->forceFill([
            'status' => DocumentStatus::Cancelled,
            'cancelled_by' => $user->id,
            'cancelled_at' => now(),
        ])->save();

        $this->pending[] = ['cancelled', $document, $touched];
    }

    // ───────────────────────── Blokada inwentaryzacyjna ─────────────────────────

    /** Otwarta (niezatwierdzona) inwentaryzacja magazynu – w tym czasie stan jest „zamrożony”. */
    public function inventoryLock(int $warehouseId, ?int $exceptDocumentId = null): ?Document
    {
        return Document::query()
            ->with('warehouse:id,code')
            ->where('type', DocumentType::INW)
            ->where('status', DocumentStatus::Draft)
            ->where('warehouse_id', $warehouseId)
            ->when($exceptDocumentId, fn ($q) => $q->whereKeyNot($exceptDocumentId))
            ->first();
    }

    /**
     * Zakaz ruchów w magazynie objętym spisem. Wyjątek: PW/RW generowane przez rozliczaną właśnie inwentaryzację.
     */
    private function ensureNotFrozen(Document $document): void
    {
        foreach (array_filter([$document->warehouse_id, $document->target_warehouse_id]) as $warehouseId) {
            $lock = $this->inventoryLock($warehouseId, $document->type === DocumentType::INW ? $document->id : null);

            if ($lock && $lock->id !== $document->parent_id) {
                throw WarehouseException::inventoryInProgress($lock->warehouse->code, $lock->displayNumber());
            }
        }
    }

    // ───────────────────────── Pomocnicze ─────────────────────────

    private function ensureDraft(Document $document): void
    {
        if (! $document->isDraft()) {
            throw WarehouseException::notDraft();
        }
    }

    private function validateHeader(Document $document): void
    {
        if ($document->type->requiresContractor() && ! $document->contractor_id) {
            throw WarehouseException::missing($document->type->contractorLabel());
        }

        if ($document->type->requiresTargetWarehouse()) {
            if (! $document->target_warehouse_id) {
                throw WarehouseException::missing('magazyn docelowy');
            }
            if ($document->target_warehouse_id === $document->warehouse_id) {
                throw WarehouseException::sameWarehouse();
            }
        }
    }

    private function positiveQuantity(DocumentItem $item): string
    {
        $quantity = (string) ($item->quantity ?? '0');

        if (bccomp($quantity, '0', 3) <= 0) {
            throw WarehouseException::invalidQuantity($item->product->name);
        }

        return $quantity;
    }

    /** Zdarzenia po COMMIT: WebSocket do paneli, webhooki do systemów zewnętrznych, alerty stanów. */
    private function flushEvents(): void
    {
        $events = $this->pending;
        $this->pending = [];
        $lowStockCandidates = [];

        foreach ($events as [$action, $document, $touched]) {
            Live::broadcast(new DocumentChanged($document, $action));

            if ($action === 'approved') {
                $this->webhooks->dispatch(WebhookEvent::DocumentApproved, DocumentPayload::make($document));
            } elseif ($document->approved_at !== null) {
                $this->webhooks->dispatch(WebhookEvent::DocumentCancelled, DocumentPayload::make($document));
            }

            foreach ($touched as $productId) {
                $lowStockCandidates[$productId] = $productId;
            }
        }

        if ($lowStockCandidates) {
            $this->notifyLowStock(array_values($lowStockCandidates));
        }
    }

    /** @param list<int> $productIds */
    private function notifyLowStock(array $productIds): void
    {
        $products = Product::query()
            ->whereIn('id', $productIds)
            ->where('min_stock', '>', 0)
            ->withSum('stocks as total_quantity', 'quantity')
            ->get();

        foreach ($products as $product) {
            if (bccomp((string) ($product->total_quantity ?? '0'), (string) $product->min_stock, 3) < 0) {
                $this->webhooks->dispatch(WebhookEvent::StockLow, [
                    'product' => ['id' => $product->id, 'sku' => $product->sku, 'ean' => $product->ean, 'name' => $product->name],
                    'quantity' => (string) ($product->total_quantity ?? '0'),
                    'min_stock' => (string) $product->min_stock,
                    'unit' => $product->unit->value,
                ]);
            }
        }
    }
}
