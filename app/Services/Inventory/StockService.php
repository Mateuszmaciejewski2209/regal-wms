<?php

namespace App\Services\Inventory;

use App\Exceptions\WarehouseException;
use App\Models\Document;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Support\Num;

/**
 * Ewidencja stanów metodą ceny średniej ważonej.
 *
 * Każda zmiana stanu: (1) blokuje wiersz stanu (SELECT … FOR UPDATE),
 * (2) aktualizuje ilość i wartość, (3) dopisuje ruch do kartoteki z saldem po operacji.
 * Metody muszą być wywoływane wewnątrz transakcji (DocumentService).
 */
class StockService
{
    /** Przychód: ilość i wartość rosną, cena średnia przelicza się automatycznie. */
    public function receive(Product $product, int $warehouseId, string $quantity, string $unitPrice, Document $document, bool $reversal = false): StockMovement
    {
        $stock = $this->lockedStock($product->id, $warehouseId);

        $newQuantity = bcadd((string) $stock->quantity, $quantity, 3);
        $newValue = bcadd((string) $stock->value, bcmul($quantity, $unitPrice, 4), 4);

        return $this->apply($stock, $document, $quantity, $unitPrice, $newQuantity, $newValue, $reversal);
    }

    /**
     * Rozchód: domyślnie po bieżącej cenie średniej. Cena jawna – przy stornie przychodu.
     *
     * @throws WarehouseException gdy stan jest niewystarczający
     */
    public function issue(Product $product, int $warehouseId, string $quantity, Document $document, ?string $unitPrice = null, bool $reversal = false): StockMovement
    {
        $stock = $this->lockedStock($product->id, $warehouseId);

        if (bccomp((string) $stock->quantity, $quantity, 3) < 0) {
            throw WarehouseException::insufficientStock(
                $product->name,
                Warehouse::find($warehouseId)?->code ?? (string) $warehouseId,
                Num::qty($stock->quantity, $product->unit),
                Num::qty($quantity, $product->unit),
            );
        }

        $unitPrice ??= $stock->averagePrice();
        $newQuantity = bcsub((string) $stock->quantity, $quantity, 3);
        $newValue = bccomp($newQuantity, '0', 3) === 0
            ? '0'
            : bcsub((string) $stock->value, bcmul($quantity, $unitPrice, 4), 4);

        if (bccomp($newValue, '0', 4) < 0) {
            $newValue = '0';
        }

        return $this->apply($stock, $document, bcmul($quantity, '-1', 3), $unitPrice, $newQuantity, $newValue, $reversal);
    }

    public function currentQuantity(int $productId, int $warehouseId): string
    {
        return (string) (Stock::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->value('quantity') ?? '0');
    }

    public function averagePrice(int $productId, int $warehouseId): string
    {
        $stock = Stock::query()->where('product_id', $productId)->where('warehouse_id', $warehouseId)->first();

        return $stock?->averagePrice() ?? '0';
    }

    private function lockedStock(int $productId, int $warehouseId): Stock
    {
        Stock::query()->firstOrCreate(['product_id' => $productId, 'warehouse_id' => $warehouseId]);

        return Stock::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function apply(Stock $stock, Document $document, string $signedQuantity, string $unitPrice, string $newQuantity, string $newValue, bool $reversal): StockMovement
    {
        $stock->update(['quantity' => $newQuantity, 'value' => $newValue]);

        return StockMovement::create([
            'product_id' => $stock->product_id,
            'warehouse_id' => $stock->warehouse_id,
            'document_id' => $document->id,
            'quantity' => $signedQuantity,
            'unit_price' => $unitPrice,
            'balance_quantity' => $newQuantity,
            'balance_value' => $newValue,
            'is_reversal' => $reversal,
            'created_at' => now(),
        ]);
    }
}
