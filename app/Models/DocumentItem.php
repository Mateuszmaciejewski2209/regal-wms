<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['document_id', 'product_id', 'quantity', 'expected_quantity', 'unit_price', 'position'])]
class DocumentItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'expected_quantity' => 'decimal:3',
            'unit_price' => 'decimal:4',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function lineValue(): string
    {
        if ($this->quantity === null || $this->unit_price === null) {
            return '0';
        }

        return bcmul((string) $this->quantity, (string) $this->unit_price, 2);
    }

    /** INW: różnica spisu (policzone − ewidencja); null gdy niepoliczone. */
    public function difference(): ?string
    {
        if ($this->quantity === null) {
            return null;
        }

        return bcsub((string) $this->quantity, (string) ($this->expected_quantity ?? '0'), 3);
    }
}
