<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'warehouse_id', 'quantity', 'value'])]
class Stock extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'value' => 'decimal:4',
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

    /** Cena średnia ważona. */
    public function averagePrice(): string
    {
        if (bccomp((string) $this->quantity, '0', 3) <= 0) {
            return '0';
        }

        return bcdiv((string) $this->value, (string) $this->quantity, 4);
    }
}
