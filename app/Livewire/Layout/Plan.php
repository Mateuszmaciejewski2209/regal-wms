<?php

namespace App\Livewire\Layout;

use App\Exceptions\WarehouseException;
use App\Models\Product;
use App\Models\ProductLocation;
use App\Models\Rack;
use App\Models\Warehouse;
use App\Services\Layout\RackService;
use App\Support\Num;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Plan magazynu: widok z góry (regały na siatce hali) + front wybranego regału (półki i pola).
 * Magazynier rozmieszcza towary, kierownik konfiguruje regały i układ.
 */
#[Title('Plan magazynu')]
class Plan extends Component
{
    public const CELL = 28; // px na kratkę planu

    #[Url(as: 'magazyn')]
    public ?int $warehouseId = null;

    #[Url(as: 'regal')]
    public ?int $rackId = null;

    #[Url(as: 'znajdz')]
    public string $find = '';

    public bool $editMode = false;

    // Konfigurator serii regałów
    public bool $showConfigurator = false;

    public array $config = [];

    // Edycja pojedynczego regału
    public bool $showRackForm = false;

    public array $rackForm = [];

    // Rozmiar planu
    public bool $showPlanSize = false;

    public array $planSize = [];

    // Pole półki (slot)
    public bool $showSlot = false;

    public ?int $slotLevel = null;

    public ?int $slotBay = null;

    public string $productSearch = '';

    public int $newFill = 25;

    public string $newQuantity = '';

    /** @var array<int, array{fill: int, quantity: string}> */
    public array $slotEdits = [];

    public function mount(): void
    {
        $this->warehouseId ??= Warehouse::query()->active()->orderBy('code')->value('id');
    }

    public function getListeners(): array
    {
        return ['echo-private:warehouse,.layout.changed' => '$refresh'];
    }

    public function updatedWarehouseId(): void
    {
        $this->rackId = null;
        $this->editMode = false;
    }

    // ───────────── Wybór i układ ─────────────

    public function selectRack(int $id): void
    {
        $this->rackId = $this->warehouse()->racks()->whereKey($id)->exists() ? $id : null;
    }

    public function moveRack(int $id, int $x, int $y, RackService $racks): void
    {
        Gate::authorize('layout.manage');
        $this->attempt(fn () => $racks->move($this->findRack($id), $x, $y));
        $this->rackId = $id;
    }

    public function rotateRack(int $id, RackService $racks): void
    {
        Gate::authorize('layout.manage');
        $rack = $this->findRack($id);
        $this->attempt(fn () => $racks->move($rack, $rack->pos_x, $rack->pos_y, ! $rack->rotated));
    }

    // ───────────── Konfigurator serii ─────────────

    public function openConfigurator(): void
    {
        Gate::authorize('layout.manage');
        $next = $this->warehouse()->racks()->count() + 1;
        $this->config = ['prefix' => 'REG-', 'start' => $next, 'count' => 10, 'digits' => 2, 'suffix' => '', 'levels' => 4, 'bays' => 3, 'per_row' => 8];
        $this->resetErrorBag();
        $this->showConfigurator = true;
    }

    public function createRacks(RackService $racks): void
    {
        Gate::authorize('layout.manage');

        $data = $this->validate([
            'config.prefix' => ['nullable', 'string', 'max:12', 'regex:/^[A-Za-z0-9._\-\/]*$/'],
            'config.suffix' => ['nullable', 'string', 'max:8', 'regex:/^[A-Za-z0-9._\-\/]*$/'],
            'config.start' => ['required', 'integer', 'min:0', 'max:9999'],
            'config.count' => ['required', 'integer', 'min:1', 'max:200'],
            'config.digits' => ['required', 'integer', 'min:1', 'max:4'],
            'config.levels' => ['required', 'integer', 'min:1', 'max:'.Rack::MAX_LEVELS],
            'config.bays' => ['required', 'integer', 'min:1', 'max:'.Rack::MAX_BAYS],
            'config.per_row' => ['required', 'integer', 'min:1', 'max:30'],
        ], ['config.prefix.regex' => 'Dozwolone: litery, cyfry i znaki . _ - /', 'config.suffix.regex' => 'Dozwolone: litery, cyfry i znaki . _ - /'], [
            'config.start' => 'numer początkowy', 'config.count' => 'liczba regałów', 'config.digits' => 'liczba cyfr',
            'config.levels' => 'liczba półek', 'config.bays' => 'liczba pól', 'config.per_row' => 'regałów w rzędzie',
        ])['config'];

        $codes = $racks->codes((string) $data['prefix'], (int) $data['start'], (int) $data['count'], (int) $data['digits'], (string) $data['suffix']);

        try {
            $created = $racks->createBatch($this->warehouse(), $codes, (int) $data['levels'], (int) $data['bays'], (int) $data['per_row']);
        } catch (WarehouseException $e) {
            $this->addError('config.prefix', $e->getMessage());

            return;
        }

        $this->showConfigurator = false;
        $this->rackId = $created->first()->id;
        $this->toast('Dodano regały', count($codes).' regałów: '.$codes[0].' … '.end($codes), 'success');
    }

    // ───────────── Edycja regału ─────────────

    public function openRackForm(): void
    {
        Gate::authorize('layout.manage');
        $rack = $this->findRack($this->rackId);
        $this->rackForm = $rack->only(['code', 'levels', 'bays', 'rotated']) + ['note' => (string) $rack->note];
        $this->resetErrorBag();
        $this->showRackForm = true;
    }

    public function saveRack(RackService $racks): void
    {
        Gate::authorize('layout.manage');
        $rack = $this->findRack($this->rackId);

        $data = $this->validate([
            'rackForm.code' => ['required', 'string', 'max:30', Rule::unique('racks', 'code')->where('warehouse_id', $rack->warehouse_id)->ignore($rack->id)],
            'rackForm.levels' => ['required', 'integer', 'min:1', 'max:'.Rack::MAX_LEVELS],
            'rackForm.bays' => ['required', 'integer', 'min:1', 'max:'.Rack::MAX_BAYS],
            'rackForm.rotated' => ['boolean'],
            'rackForm.note' => ['nullable', 'string', 'max:255'],
        ], [], ['rackForm.code' => 'kod', 'rackForm.levels' => 'liczba półek', 'rackForm.bays' => 'liczba pól'])['rackForm'];

        try {
            $racks->update($rack, $data);
        } catch (WarehouseException $e) {
            $this->addError('rackForm.levels', $e->getMessage());

            return;
        }

        $this->showRackForm = false;
        $this->toast('Zapisano regał', $data['code'], 'success');
    }

    public function deleteRack(RackService $racks): void
    {
        Gate::authorize('layout.manage');
        $rack = $this->findRack($this->rackId);
        $racks->delete($rack);
        $this->rackId = null;
        $this->showRackForm = false;
        $this->toast('Usunięto regał', $rack->code, 'neutral');
    }

    public function openPlanSize(): void
    {
        Gate::authorize('layout.manage');
        $w = $this->warehouse();
        $this->planSize = ['width' => $w->plan_width, 'height' => $w->plan_height];
        $this->resetErrorBag();
        $this->showPlanSize = true;
    }

    public function savePlanSize(RackService $racks): void
    {
        Gate::authorize('layout.manage');
        $data = $this->validate([
            'planSize.width' => ['required', 'integer', 'min:8', 'max:200'],
            'planSize.height' => ['required', 'integer', 'min:6', 'max:200'],
        ], [], ['planSize.width' => 'szerokość', 'planSize.height' => 'głębokość'])['planSize'];

        try {
            $racks->resizePlan($this->warehouse(), $data['width'], $data['height']);
        } catch (WarehouseException $e) {
            $this->addError('planSize.width', $e->getMessage());

            return;
        }

        $this->showPlanSize = false;
    }

    // ───────────── Pole półki: rozmieszczanie towarów ─────────────

    public function openSlot(int $level, int $bay): void
    {
        $this->slotLevel = $level;
        $this->slotBay = $bay;
        $this->productSearch = '';
        $this->newQuantity = '';
        $this->loadSlotEdits();
        $this->resetErrorBag();
        $this->showSlot = true;
    }

    public function place(int $productId, RackService $racks): void
    {
        Gate::authorize('locations.manage');
        $quantity = $this->newQuantity === '' ? null : Num::parse($this->newQuantity);

        $this->attempt(function () use ($racks, $productId, $quantity) {
            $racks->place(Product::findOrFail($productId), $this->findRack($this->rackId), $this->slotLevel, $this->slotBay, $this->newFill, $quantity);
            $this->productSearch = '';
            $this->newQuantity = '';
        });

        $this->loadSlotEdits();
    }

    public function savePlacement(int $locationId, RackService $racks): void
    {
        Gate::authorize('locations.manage');
        $location = $this->findPlacement($locationId);
        $edit = $this->slotEdits[$locationId] ?? null;

        if (! $edit) {
            return;
        }

        $quantity = trim((string) $edit['quantity']) === '' ? null : Num::parse($edit['quantity']);

        $this->attempt(fn () => $racks->place($location->product, $location->rack, $location->level, $location->bay, (int) $edit['fill'], $quantity));
        $this->loadSlotEdits();
    }

    public function removePlacement(int $locationId, RackService $racks): void
    {
        Gate::authorize('locations.manage');
        $racks->remove($this->findPlacement($locationId));
        $this->loadSlotEdits();
    }

    // ───────────── Render ─────────────

    public function render(RackService $service)
    {
        $warehouse = $this->warehouse();
        $racks = $warehouse?->racks()->with('placements.product:id,name,sku,unit')->get() ?? collect();
        $rack = $this->rackId ? $racks->firstWhere('id', $this->rackId) : null;

        // Wyszukiwanie towaru: podświetla regały i pola, na których leży.
        $matches = collect();
        if (mb_strlen(trim($this->find)) >= 2) {
            $ids = Product::query()->search($this->find)->pluck('id');
            $matches = $racks->flatMap(fn (Rack $r) => $r->placements->whereIn('product_id', $ids))
                ->map(fn (ProductLocation $l) => $l->rack_id.':'.$l->level.':'.$l->bay);
        }

        $slotItems = $rack && $this->showSlot ? $rack->placements->where('level', $this->slotLevel)->where('bay', $this->slotBay)->values() : collect();

        return view('livewire.layout.plan', [
            'warehouse' => $warehouse,
            'warehouseOptions' => Warehouse::query()->orderBy('code')->get()->mapWithKeys(fn ($w) => [$w->id => "{$w->code} · {$w->name}"])->all(),
            'racks' => $racks,
            'rack' => $rack,
            'matches' => $matches,
            'matchedRacks' => $matches->map(fn ($k) => (int) explode(':', $k)[0])->unique(),
            'slotItems' => $slotItems,
            'slotFree' => $rack && $this->showSlot ? $service->slotFree($rack, $this->slotLevel, $this->slotBay) : 100,
            'searchResults' => $this->showSlot && mb_strlen(trim($this->productSearch)) >= 2
                ? Product::query()->active()->search($this->productSearch)->with('stocks')->orderBy('name')->limit(6)->get()
                : collect(),
            'previewCodes' => $this->showConfigurator ? $this->previewCodes($service) : [],
            'cell' => self::CELL,
            'stats' => $this->stats($racks),
        ]);
    }

    /** @return array{racks: int, slots: int, occupancy: int, placed: int} */
    private function stats(Collection $racks): array
    {
        $slots = $racks->sum(fn (Rack $r) => $r->slotCount());

        return [
            'racks' => $racks->count(),
            'slots' => $slots,
            'occupancy' => $slots ? (int) round($racks->sum(fn (Rack $r) => $r->placements->sum('fill')) / $slots) : 0,
            'placed' => $racks->flatMap->placements->pluck('product_id')->unique()->count(),
        ];
    }

    /** @return list<string> */
    private function previewCodes(RackService $service): array
    {
        $count = max(0, min(200, (int) ($this->config['count'] ?? 0)));
        $codes = $service->codes((string) ($this->config['prefix'] ?? ''), (int) ($this->config['start'] ?? 1), $count, max(1, min(4, (int) ($this->config['digits'] ?? 2))), (string) ($this->config['suffix'] ?? ''));

        return count($codes) > 6 ? [...array_slice($codes, 0, 3), '…', ...array_slice($codes, -2)] : $codes;
    }

    private function loadSlotEdits(): void
    {
        $this->slotEdits = ProductLocation::query()
            ->where(['rack_id' => $this->rackId, 'level' => $this->slotLevel, 'bay' => $this->slotBay])
            ->get()
            ->mapWithKeys(fn (ProductLocation $l) => [$l->id => ['fill' => $l->fill, 'quantity' => Num::input($l->quantity)]])
            ->all();

        // Następny towar domyślnie dostaje całe pozostałe miejsce na polu.
        $this->newFill = max(5, 100 - array_sum(array_column($this->slotEdits, 'fill')));
    }

    private function warehouse(): ?Warehouse
    {
        return $this->warehouseId ? Warehouse::find($this->warehouseId) : null;
    }

    private function findRack(?int $id): Rack
    {
        return Rack::query()->where('warehouse_id', $this->warehouseId)->with('warehouse')->findOrFail($id);
    }

    private function findPlacement(int $id): ProductLocation
    {
        return ProductLocation::query()->where('rack_id', $this->rackId)->with(['rack.warehouse', 'product'])->findOrFail($id);
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
