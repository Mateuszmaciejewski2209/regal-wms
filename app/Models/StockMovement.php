<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id', 'warehouse_id', 'document_id', 'quantity', 'unit_price',
    'balance_quantity', 'balance_value', 'is_reversal', 'created_at',
])]
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:4',
            'balance_quantity' => 'decimal:3',
            'balance_value' => 'decimal:4',
            'is_reversal' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
