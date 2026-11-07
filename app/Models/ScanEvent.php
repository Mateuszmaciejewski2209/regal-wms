<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'scanner_device_id', 'code', 'product_id', 'document_id',
    'quantity', 'result', 'source', 'message',
])]
class ScanEvent extends Model
{
    public const UPDATED_AT = null;

    /** Wyniki skanu: kod → [polska nazwa, ton plakietki]. Jedno źródło dla dziennika, pulpitu i komunikatów. */
    public const RESULTS = [
        'added' => ['dodano', 'success'],
        'placed' => ['odłożono na półkę', 'success'],
        'found' => ['sprawdzono', 'neutral'],
        'location' => ['skan półki', 'neutral'],
        'no_target' => ['brak dokumentu', 'warn'],
        'rejected' => ['odrzucono', 'warn'],
        'not_found' => ['nieznany kod', 'danger'],
    ];

    public static function tone(string $result): string
    {
        return self::RESULTS[$result][1] ?? 'neutral';
    }

    public function resultLabel(): string
    {
        return self::RESULTS[$this->result][0] ?? $this->result;
    }

    public function resultTone(): string
    {
        return self::tone($this->result);
    }

    /** Dla skanu etykiety półki: „REG-02 · półka 1 · pole 3” (bez zapytań do bazy). */
    public function locationLabel(): ?string
    {
        if (! str_starts_with($this->code, ProductLocation::QR_PREFIX)) {
            return null;
        }

        $parts = explode(':', substr($this->code, strlen(ProductLocation::QR_PREFIX)));

        return count($parts) === 4 ? "{$parts[1]} · półka {$parts[2]} · pole {$parts[3]}" : null;
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(ScannerDevice::class, 'scanner_device_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
