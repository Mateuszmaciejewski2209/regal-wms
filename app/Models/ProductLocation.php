<?php

namespace App\Models;

use App\Support\Num;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Towar leżący na konkretnym miejscu: regał · półka · pole, z przybliżonym zajęciem miejsca.
 */
#[Fillable(['rack_id', 'level', 'bay', 'product_id', 'fill', 'quantity'])]
class ProductLocation extends Model
{
    /** Prefiks treści kodu QR etykiety miejsca składowania. */
    public const QR_PREFIX = 'RGL:L:';

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'bay' => 'integer',
            'fill' => 'integer',
            'quantity' => 'decimal:3',
        ];
    }

    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Kod miejsca, np. „REG-01-2-3” (regał – półka – pole). */
    public static function code(Rack $rack, int $level, int $bay): string
    {
        return "{$rack->code}-{$level}-{$bay}";
    }

    /** Czytelny opis, np. „REG-01 · półka 2 · pole 3”. */
    public static function label(Rack $rack, int $level, int $bay): string
    {
        return "{$rack->code} · półka {$level} · pole {$bay}";
    }

    /** Treść kodu QR etykiety półki – zawiera magazyn, bo kody regałów są unikalne w obrębie magazynu. */
    public static function qrPayload(Rack $rack, int $level, int $bay): string
    {
        return self::QR_PREFIX.$rack->warehouse->code.':'.$rack->code.':'.$level.':'.$bay;
    }

    /** @return array{rack: Rack, level: int, bay: int}|null */
    public static function parseQr(string $code): ?array
    {
        if (! str_starts_with($code, self::QR_PREFIX)) {
            return null;
        }

        $parts = explode(':', substr($code, strlen(self::QR_PREFIX)));

        if (count($parts) !== 4) {
            return null;
        }

        [$warehouse, $rackCode, $level, $bay] = $parts;

        $rack = Rack::query()
            ->whereHas('warehouse', fn ($q) => $q->where('code', $warehouse))
            ->where('code', $rackCode)
            ->with('warehouse')
            ->first();

        if (! $rack || (int) $level < 1 || (int) $level > $rack->levels || (int) $bay < 1 || (int) $bay > $rack->bays) {
            return null;
        }

        return ['rack' => $rack, 'level' => (int) $level, 'bay' => (int) $bay];
    }

    public function displayCode(): string
    {
        return self::code($this->rack, $this->level, $this->bay);
    }

    public function summary(): string
    {
        $text = $this->rack->warehouse->code.' · '.self::label($this->rack, $this->level, $this->bay);

        return $this->quantity !== null
            ? $text.' · '.Num::qty($this->quantity, $this->product?->unit)
            : $text;
    }
}
