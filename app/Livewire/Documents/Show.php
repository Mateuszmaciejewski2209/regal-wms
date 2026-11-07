<?php

namespace App\Livewire\Documents;

use App\Enums\DocumentType;
use App\Exceptions\WarehouseException;
use App\Models\Contractor;
use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\Product;
use App\Models\ScanEvent;
use App\Models\Stock;
use App\Models\Warehouse;
use App\Services\Documents\DocumentService;
use App\Services\Layout\RackService;
use App\Services\Scanner\ScannerService;
use App\Support\Num;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Szkic: edytor pozycji (ręcznie, czytnik USB, telefon na żywo). Zatwierdzony: podgląd, PDF, storno.
 */
class Show extends Component
{
    public Document $document;

    /** Pole czytnika kodów (czytnik USB działa jak klawiatura + Enter). */
    public string $code = '';

    public string $productSearch = '';

    /** @var array<int, string> */
    public array $quantities = [];

    /** @var array<int, string> */
    public array $prices = [];

    /**
     * Ostatnio zsynchronizowane wartości z bazy. Zmiana z zewnątrz (np. skan z telefonu)
     * aktualizuje tylko zmienione wiersze – pola edytowane właśnie przez użytkownika nie są nadpisywane.
     *
     * @var array<int, array{q: string, p: string}>
     */
    #[Locked]
    public array $synced = [];

    public ?int $flashItemId = null;

    /** Zmiana wartości restartuje animację podświetlenia wiersza. */
    public int $flashNonce = 0;

    public bool $confirmApprove = false;

    public bool $confirmCancel = false;

    public bool $confirmDelete = false;

    public bool $editHeader = false;

    public array $header = [];

    public function mount(Document $document): void
    {
        $this->document = $document;
        $this->syncInputs(force: true);
    }

    public function getListeners(): array
    {
        return [
            "echo-private:documents.{$this->document->id},.items.changed" => 'onItemsChanged',
            'echo-private:warehouse,.document.changed' => 'onDocumentChanged',
            'scanner-target-changed' => '$refresh',
        ];
    }

    // ───────────── Zdarzenia na żywo ─────────────

    public function onItemsChanged(): void
    {
        if ($changed = $this->syncInputs()) {
            $this->flash($changed);
        }
    }

    public function onDocumentChanged(array $payload): void
    {
        if (($payload['id'] ?? null) === $this->document->id) {
            if (($payload['action'] ?? null) === 'deleted') {
                $this->redirectRoute('documents.index', navigate: true);

                return;
            }
            $this->document->refresh();
            $this->syncInputs(force: true);
        }
    }

    // ───────────── Pozycje ─────────────

    public function scan(ScannerService $scanner): void
    {
        $this->authorizeEdit();

        $code = trim($this->code);
        $this->code = '';

        if ($code === '') {
            return;
        }

        $result = $scanner->handleScan(auth()->user(), $code, '1', 'add', target: $this->document, source: 'keyboard');

        $this->toast(
            $result['product']['name'] ?? $code,
            $result['message'],
            ScanEvent::tone($result['result']),
        );

        $this->onItemsChanged();
    }

    public function addProduct(int $productId, DocumentService $documents): void
    {
        $this->authorizeEdit();
        $product = Product::findOrFail($productId);

        $this->attempt(function () use ($documents, $product) {
            $documents->addProduct($this->document, $product, '1');
        });

        $this->productSearch = '';
        $this->onItemsChanged();
    }

    public function updatedQuantities(mixed $value, string|int $key): void
    {
        $this->saveItem((int) $key);
    }

    public function updatedPrices(mixed $value, string|int $key): void
    {
        $this->saveItem((int) $key);
    }

    public function saveItem(int $itemId): void
    {
        $this->authorizeEdit();
        $item = $this->findItem($itemId);
        $isInventory = $this->document->type === DocumentType::INW;

        $quantityInput = trim((string) ($this->quantities[$itemId] ?? ''));
        $quantity = Num::parse($quantityInput);

        if ($quantity === null && ! ($isInventory && $quantityInput === '')) {
            $this->addError("quantities.{$itemId}", 'Podaj liczbę');

            return;
        }

        $priceInput = trim(str_ireplace('zł', '', (string) ($this->prices[$itemId] ?? '')));
        $price = $this->document->type->hasEnteredPrices() ? Num::parse($priceInput) : null;

        // Nieczytelna cena nie może cicho zamienić się w 0 zł.
        if ($this->document->type->hasEnteredPrices() && $priceInput !== '' && $price === null) {
            $this->addError("prices.{$itemId}", 'Podaj cenę, np. 12,50');

            return;
        }

        $this->resetErrorBag(["quantities.{$itemId}", "prices.{$itemId}"]);
        $this->attempt(fn () => app(DocumentService::class)->updateItem($item, $quantity, $price));
        $this->syncItem($item->fresh());
    }

    /** INW: szybkie potwierdzenie pozycji – „zgodne ze stanem ewidencyjnym” albo „brak towaru (0)”. */
    public function setCounted(int $itemId, string $mode, DocumentService $documents): void
    {
        $this->authorizeEdit();
        $item = $this->findItem($itemId);
        $quantity = $mode === 'zero' ? '0' : (string) ($item->expected_quantity ?? '0');

        $this->attempt(fn () => $documents->updateItem($item, $quantity));
        $this->syncItem($item->fresh());
    }

    /** INW: wszystkie niepoliczone pozycje = stan ewidencyjny (różnica 0). */
    public function confirmRemainingAsBook(DocumentService $documents): void
    {
        $this->authorizeEdit();

        $this->attempt(function () use ($documents) {
            $count = $documents->fillInventoryFromBook($this->document);
            $this->toast('Uzupełniono spis', 'Potwierdzono '.Num::plural($count, 'pozycję', 'pozycje', 'pozycji').' zgodnie ze stanem ewidencyjnym.', 'success');
        });
        $this->syncInputs(force: true);
    }

    public function removeItem(int $itemId, DocumentService $documents): void
    {
        $this->authorizeEdit();
        $this->attempt(fn () => $documents->removeItem($this->findItem($itemId)));
        $this->syncInputs(force: true);
    }

    public function prefill(DocumentService $documents): void
    {
        $this->authorizeEdit();
        $this->attempt(function () use ($documents) {
            $count = $documents->prefillInventory($this->document);
            $count
                ? $this->toast('Wczytano stany', 'Dodano '.Num::plural($count, 'pozycję', 'pozycje', 'pozycji').' do spisu.', 'success')
                : $this->toast('Nic nowego', 'Wszystkie towary ze stanem są już w arkuszu.', 'neutral');
        });
        $this->syncInputs(force: true);
    }

    // ───────────── Skaner ─────────────

    public function toggleScanner(ScannerService $scanner): void
    {
        Gate::authorize('scanner.use');
        $user = auth()->user();
        $attached = $user->scanner_document_id === $this->document->id;

        $this->attempt(fn () => $scanner->setTarget($user, $attached ? null : $this->document));
        $this->dispatch('scanner-target-changed');
    }

    // ───────────── Nagłówek ─────────────

    public function openHeader(): void
    {
        $this->authorizeEdit();
        $this->header = [
            'warehouse_id' => $this->document->warehouse_id,
            'target_warehouse_id' => $this->document->target_warehouse_id,
            'contractor_id' => $this->document->contractor_id,
            'issued_at' => $this->document->issued_at->toDateString(),
            'external_number' => $this->document->external_number,
            'notes' => $this->document->notes,
        ];
        $this->resetErrorBag();
        $this->editHeader = true;
    }

    public function saveHeader(DocumentService $documents): void
    {
        $this->authorizeEdit();
        $type = $this->document->type;

        $data = $this->validate([
            'header.warehouse_id' => ['required', Rule::exists('warehouses', 'id')],
            'header.target_warehouse_id' => [Rule::requiredIf($type->requiresTargetWarehouse()), 'nullable', 'different:header.warehouse_id', Rule::exists('warehouses', 'id')],
            'header.contractor_id' => [Rule::requiredIf($type->requiresContractor()), 'nullable', Rule::exists('contractors', 'id')],
            'header.issued_at' => ['required', 'date'],
            'header.external_number' => ['nullable', 'string', 'max:60'],
            'header.notes' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'header.warehouse_id' => 'magazyn',
            'header.target_warehouse_id' => 'magazyn docelowy',
            'header.contractor_id' => 'kontrahent',
            'header.issued_at' => 'data',
        ])['header'];

        $this->attempt(fn () => $documents->updateHeader($this->document, $data));
        $this->editHeader = false;
    }

    // ───────────── Cykl życia ─────────────

    public function approve(DocumentService $documents): void
    {
        Gate::authorize('approve-document', $this->document);
        $this->confirmApprove = false;

        $this->attempt(function () use ($documents) {
            $this->document = $documents->approve($this->document, auth()->user());
            $this->toast('Zatwierdzono', $this->document->number, 'success');
            $this->dispatch('scanner-target-changed');
        });
    }

    public function cancelDocument(DocumentService $documents): void
    {
        Gate::authorize('cancel-document', $this->document);
        $this->confirmCancel = false;

        $this->attempt(function () use ($documents) {
            $wasApproved = $this->document->isApproved();
            $this->document = $documents->cancel($this->document, auth()->user());
            $this->toast($wasApproved ? 'Wykonano storno' : 'Anulowano szkic', $this->document->displayNumber(), 'success');
            $this->dispatch('scanner-target-changed');
        });
    }

    public function deleteDraft(DocumentService $documents)
    {
        Gate::authorize('cancel-document', $this->document);

        try {
            $documents->deleteDraft($this->document);
        } catch (WarehouseException $e) {
            $this->toast('Nie można usunąć', $e->getMessage(), 'danger');

            return null;
        }

        $this->dispatch('scanner-target-changed');

        return $this->redirectRoute('documents.index', navigate: true);
    }

    // ───────────── Render ─────────────

    public function render(DocumentService $documents)
    {
        $document = $this->document->load([
            'items.product.category',
            'warehouse', 'targetWarehouse', 'contractor', 'author', 'approver', 'canceller', 'parent', 'children',
        ]);

        // Dla rozchodów pokazujemy dostępny stan i ostrzegamy przed brakami jeszcze przed zatwierdzeniem.
        $checksStock = $document->isDraft() && in_array($document->type->direction(), ['out', 'transfer'], true);

        $available = $checksStock
            ? Stock::query()->where('warehouse_id', $document->warehouse_id)->whereIn('product_id', $document->items->pluck('product_id'))->pluck('quantity', 'product_id')
            : collect();

        $shortages = $checksStock
            ? $document->items
                ->filter(fn (DocumentItem $item) => bccomp((string) ($item->quantity ?? '0'), (string) ($available[$item->product_id] ?? '0'), 3) > 0)
                ->keyBy('product_id')
            : collect();

        $searchResults = $document->isDraft() && mb_strlen(trim($this->productSearch)) >= 2
            ? Product::query()->active()->search($this->productSearch)->orderBy('name')->limit(8)->get()
            : collect();

        // Blokada inwentaryzacyjna dotyczy szkiców innych dokumentów w „zamrożonym” magazynie.
        $lock = $document->isDraft() && $document->type !== DocumentType::INW
            ? collect([$document->warehouse_id, $document->target_warehouse_id])->filter()->map(fn ($id) => $documents->inventoryLock($id))->filter()->first()
            : null;

        return view('livewire.documents.show', [
            'doc' => $document,
            'type' => $document->type,
            'canEdit' => $document->isDraft() && auth()->user()->can('documents.create'),
            'checksStock' => $checksStock,
            'available' => $available,
            'searchResults' => $searchResults,
            'shortages' => $shortages,
            'lock' => $lock,
            'scannerAttached' => auth()->user()->scanner_document_id === $document->id,
            'pairedDevices' => auth()->user()->scannerDevices()->active()->count(),
            'draftValue' => $document->type->hasEnteredPrices()
                ? $document->items->reduce(fn ($sum, DocumentItem $i) => bcadd($sum, $i->lineValue(), 2), '0')
                : null,
            'uncounted' => $document->type === DocumentType::INW ? $document->items->whereNull('quantity')->count() : 0,
            // Lokalizacje w magazynie dokumentu: przy przyjęciu „gdzie odłożyć”, przy wydaniu/MM „skąd pobrać”.
            'locations' => app(RackService::class)->codesFor($document->items->pluck('product_id'), $document->warehouse_id),
            // Te same listy co przy tworzeniu: tylko aktywne magazyny i właściwy rodzaj kontrahenta.
            'warehouseOptions' => $this->editHeader ? Warehouse::query()->active()->orderBy('code')->get()->mapWithKeys(fn ($w) => [$w->id => "{$w->code} · {$w->name}"])->all() : [],
            'contractorOptions' => $this->editHeader && $document->type->requiresContractor()
                ? Contractor::query()->where('is_active', true)
                    ->when($document->type === DocumentType::PZ, fn ($q) => $q->suppliers(), fn ($q) => $q->customers())
                    ->orderBy('name')->get(['id', 'name'])->pluck('name', 'id')->all()
                : [],
            'movements' => $document->isDraft() ? collect() : $document->movements()->with(['product:id,name,sku,unit', 'warehouse:id,code,name,color'])->orderBy('id')->get(),
        ])->title($document->displayNumber());
    }

    // ───────────── Pomocnicze ─────────────

    /**
     * Synchronizuje pola z bazą. Bez $force nadpisuje tylko wiersze zmienione od ostatniej synchronizacji.
     *
     * @return int|null id ostatniego zmienionego/nowego wiersza (do podświetlenia)
     */
    private function syncInputs(bool $force = false): ?int
    {
        $items = $this->document->items()->get(['id', 'quantity', 'unit_price']);
        $changed = null;
        $fresh = [];

        foreach ($items as $item) {
            $values = $this->formatItem($item);
            $previous = $this->synced[$item->id] ?? null;

            if ($force || $previous === null || $previous['q'] !== $values['q']) {
                $this->quantities[$item->id] = $values['q'];
            }
            if ($force || $previous === null || $previous['p'] !== $values['p']) {
                $this->prices[$item->id] = $values['p'];
            }
            if (! $force && $previous !== $values) {
                $changed = $item->id;
            }

            $fresh[$item->id] = $values;
        }

        $this->quantities = array_intersect_key($this->quantities, $fresh);
        $this->prices = array_intersect_key($this->prices, $fresh);
        $this->synced = $fresh;

        return $changed;
    }

    /** Zapis własnej zmiany – aktualizuje tylko ten wiersz (bez podświetlania). */
    private function syncItem(DocumentItem $item): void
    {
        $values = $this->formatItem($item);
        $this->synced[$item->id] = $values;
        $this->quantities[$item->id] = $values['q'];
        $this->prices[$item->id] = $values['p'];
    }

    /** @return array{q: string, p: string} */
    private function formatItem(DocumentItem $item): array
    {
        return [
            'q' => Num::input($item->quantity),
            'p' => $item->unit_price === null ? '' : number_format((float) $item->unit_price, 2, ',', ''),
        ];
    }

    private function flash(int $itemId): void
    {
        $this->flashItemId = $itemId;
        $this->flashNonce++;
    }

    private function authorizeEdit(): void
    {
        Gate::authorize('documents.create');
    }

    private function findItem(int $itemId): DocumentItem
    {
        return $this->document->items()->with('product', 'document')->findOrFail($itemId);
    }

    private function attempt(callable $callback): void
    {
        try {
            $callback();
        } catch (WarehouseException $e) {
            $this->toast('Operacja odrzucona', $e->getMessage(), 'danger');
        }
    }

    private function toast(string $title, string $message, string $tone = 'neutral'): void
    {
        $this->dispatch('toast', title: $title, message: $message, tone: $tone);
    }
}
