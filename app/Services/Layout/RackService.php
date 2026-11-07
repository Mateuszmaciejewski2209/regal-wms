<?php

namespace App\Services\Layout;

use App\Events\LayoutChanged;
use App\Exceptions\WarehouseException;
use App\Models\Product;
use App\Models\ProductLocation;
use App\Models\Rack;
use App\Models\Warehouse;
use App\Support\Live;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Regały i rozmieszczenie towarów: konfigurator seryjny, układ na planie hali, przypisanie towaru do pola półki.
 */
class RackService
{
    /** Odstęp między rzędami regałów na planie (1 kratka regał + 2 kratki alejka). */
    private const ROW_STEP = 3;

    private const MARGIN = 1;

    /**
     * Kody z konfiguratora, np. prefiks „REG-”, start 1, 40 sztuk, 2 cyfry → REG-01 … REG-40.
     *
     * @return list<string>
     */
    public function codes(string $prefix, int $start, int $count, int $digits, string $suffix = ''): array
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = $prefix.str_pad((string) ($start + $i), $digits, '0', STR_PAD_LEFT).$suffix;
        }

        return $codes;
    }

    /**
     * Tworzy serię regałów i układa je automatycznie w rzędy pod istniejącymi.
     *
     * @return Collection<int, Rack>
     */
    public function createBatch(Warehouse $warehouse, array $codes, int $levels, int $bays, int $perRow): Collection
    {
        $taken = $warehouse->racks()->whereIn('code', $codes)->pluck('code')->all();

        if ($taken) {
            throw WarehouseException::rackCodesTaken($taken);
        }

        $racks = DB::transaction(function () use ($warehouse, $codes, $levels, $bays, $perRow) {
            $startY = (int) $warehouse->racks()->get()->map(fn (Rack $r) => $r->pos_y + $r->footprint()['h'])->max();
            $startY = $startY > 0 ? $startY + 2 : self::MARGIN;
            $created = collect();

            foreach (array_values($codes) as $index => $code) {
                $row = intdiv($index, $perRow);
                $col = $index % $perRow;

                $created->push($warehouse->racks()->create([
                    'code' => $code,
                    'levels' => $levels,
                    'bays' => $bays,
                    'pos_x' => self::MARGIN + $col * ($bays + 1),
                    'pos_y' => $startY + $row * self::ROW_STEP,
                ]));
            }

            // Plan rośnie, żeby pomieścić nowe regały (wartości z bazy – model mógł nie mieć domyślnych).
            $warehouse->refresh();
            $needW = self::MARGIN * 2 + min($perRow, count($codes)) * ($bays + 1);
            $needH = $startY + (intdiv(count($codes) - 1, $perRow) + 1) * self::ROW_STEP + 1;
            $warehouse->update([
                'plan_width' => max($warehouse->plan_width, $needW),
                'plan_height' => max($warehouse->plan_height, $needH),
            ]);

            return $created;
        });

        $this->changed($warehouse->id);

        return $racks;
    }

    public function update(Rack $rack, array $data): Rack
    {
        $levels = (int) $data['levels'];
        $bays = (int) $data['bays'];

        $orphans = $rack->placements()->where(fn ($q) => $q->where('level', '>', $levels)->orWhere('bay', '>', $bays))->exists();

        if ($orphans) {
            throw WarehouseException::rackShrinkWouldOrphan();
        }

        $rack->fill([
            'code' => $data['code'],
            'levels' => $levels,
            'bays' => $bays,
            'rotated' => (bool) ($data['rotated'] ?? $rack->rotated),
            'note' => $data['note'] ?? null,
        ]);

        $this->assertFits($rack, $rack->pos_x, $rack->pos_y);
        $rack->save();
        $this->changed($rack->warehouse_id);

        return $rack;
    }

    /** Przesunięcie regału na planie (przeciągnij i upuść). */
    public function move(Rack $rack, int $x, int $y, ?bool $rotated = null): Rack
    {
        if ($rotated !== null) {
            $rack->rotated = $rotated;
        }

        $this->assertFits($rack, $x, $y);
        $rack->forceFill(['pos_x' => $x, 'pos_y' => $y])->save();
        $this->changed($rack->warehouse_id);

        return $rack;
    }

    public function delete(Rack $rack): void
    {
        $warehouseId = $rack->warehouse_id;
        $rack->delete(); // rozmieszczenia usuwane kaskadowo
        $this->changed($warehouseId);
    }

    public function resizePlan(Warehouse $warehouse, int $width, int $height): void
    {
        foreach ($warehouse->racks as $rack) {
            $f = $rack->footprint();
            if ($rack->pos_x + $f['w'] > $width || $rack->pos_y + $f['h'] > $height) {
                throw WarehouseException::rackOutsidePlan();
            }
        }

        $warehouse->update(['plan_width' => $width, 'plan_height' => $height]);
        $this->changed($warehouse->id);
    }

    // ───────────── Rozmieszczenie towarów ─────────────

    /**
     * Kładzie towar na polu półki (lub aktualizuje istniejące rozmieszczenie).
     * Suma zajętości pola nie może przekroczyć 100%.
     */
    public function place(Product $product, Rack $rack, int $level, int $bay, int $fill, ?string $quantity = null): ProductLocation
    {
        if ($level < 1 || $level > $rack->levels || $bay < 1 || $bay > $rack->bays) {
            throw WarehouseException::slotOutsideRack();
        }

        $fill = max(5, min(100, $fill));

        $location = DB::transaction(function () use ($product, $rack, $level, $bay, $fill, $quantity) {
            // Blokada wiersza regału szereguje równoległe rozmieszczenia na jego polach.
            // PostgreSQL nie pozwala łączyć FOR UPDATE z SUM() i nie blokuje wierszy, których jeszcze nie ma.
            Rack::query()->whereKey($rack->id)->lockForUpdate()->first();

            $others = (int) ProductLocation::query()
                ->where(['rack_id' => $rack->id, 'level' => $level, 'bay' => $bay])
                ->where('product_id', '!=', $product->id)
                ->sum('fill');

            if ($others + $fill > 100) {
                throw WarehouseException::slotFull(max(0, 100 - $others));
            }

            return ProductLocation::updateOrCreate(
                ['rack_id' => $rack->id, 'level' => $level, 'bay' => $bay, 'product_id' => $product->id],
                ['fill' => $fill, 'quantity' => $quantity],
            );
        });

        $this->changed($rack->warehouse_id);

        return $location;
    }

    public function remove(ProductLocation $location): void
    {
        $warehouseId = $location->rack->warehouse_id;
        $location->delete();
        $this->changed($warehouseId);
    }

    public function slotFree(Rack $rack, int $level, int $bay, ?int $exceptProductId = null): int
    {
        $used = (int) ProductLocation::query()
            ->where(['rack_id' => $rack->id, 'level' => $level, 'bay' => $bay])
            ->when($exceptProductId, fn ($q) => $q->where('product_id', '!=', $exceptProductId))
            ->sum('fill');

        return max(0, 100 - $used);
    }

    /**
     * Lokalizacje towaru (opcjonalnie w jednym magazynie), posortowane jak trasa zbiórki.
     *
     * @return Collection<int, ProductLocation>
     */
    public function locationsOf(Product $product, ?int $warehouseId = null): Collection
    {
        return ProductLocation::query()
            ->with('rack.warehouse:id,code,name,color')
            ->where('product_id', $product->id)
            ->when($warehouseId, fn ($q) => $q->whereHas('rack', fn ($r) => $r->where('warehouse_id', $warehouseId)))
            ->get()
            ->sortBy(fn (ProductLocation $l) => [$l->rack->warehouse->code, $l->rack->code, $l->level, $l->bay])
            ->values();
    }

    /**
     * Kody lokalizacji wielu towarów naraz (do dokumentów i listy zbiórki): product_id → ['REG-01-2-3', …].
     *
     * @param  iterable<int>  $productIds
     * @return Collection<int, list<string>>
     */
    public function codesFor(iterable $productIds, int $warehouseId): Collection
    {
        return ProductLocation::query()
            ->with('rack:id,code,warehouse_id')
            ->whereIn('product_id', collect($productIds)->all())
            ->whereHas('rack', fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->get()
            ->sortBy(fn (ProductLocation $l) => [$l->rack->code, $l->level, $l->bay])
            ->groupBy('product_id')
            ->map(fn ($group) => $group->map(fn (ProductLocation $l) => ProductLocation::code($l->rack, $l->level, $l->bay))->values()->all());
    }

    private function assertFits(Rack $rack, int $x, int $y): void
    {
        $f = $rack->footprint();
        $warehouse = $rack->warehouse ?? Warehouse::find($rack->warehouse_id);

        if ($x < 0 || $y < 0 || $x + $f['w'] > $warehouse->plan_width || $y + $f['h'] > $warehouse->plan_height) {
            throw WarehouseException::rackOutsidePlan();
        }

        $collision = Rack::query()
            ->where('warehouse_id', $rack->warehouse_id)
            ->when($rack->exists, fn ($q) => $q->whereKeyNot($rack->id))
            ->get()
            ->first(fn (Rack $other) => $other->overlaps($x, $y, $f['w'], $f['h']));

        if ($collision) {
            throw WarehouseException::rackOverlap($collision->code);
        }
    }

    private function changed(int $warehouseId): void
    {
        Live::broadcast(new LayoutChanged($warehouseId));
    }
}
