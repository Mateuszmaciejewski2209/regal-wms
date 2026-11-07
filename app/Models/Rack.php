<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['warehouse_id', 'code', 'levels', 'bays', 'pos_x', 'pos_y', 'rotated', 'note'])]
class Rack extends Model
{
    public const MAX_LEVELS = 12;

    public const MAX_BAYS = 12;

    protected function casts(): array
    {
        return [
            'levels' => 'integer',
            'bays' => 'integer',
            'pos_x' => 'integer',
            'pos_y' => 'integer',
            'rotated' => 'boolean',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function placements(): HasMany
    {
        return $this->hasMany(ProductLocation::class);
    }

    /** Zajmowane kratki planu: regał z N polami ma długość N i głębokość 1. */
    public function footprint(): array
    {
        return $this->rotated
            ? ['w' => 1, 'h' => $this->bays]
            : ['w' => $this->bays, 'h' => 1];
    }

    public function slotCount(): int
    {
        return $this->levels * $this->bays;
    }

    /** Zajętość regału 0–100 (suma wypełnienia pól / pojemność). */
    public function occupancy(): int
    {
        $slots = $this->slotCount();

        if ($slots === 0) {
            return 0;
        }

        $sum = $this->relationLoaded('placements')
            ? $this->placements->sum('fill')
            : $this->placements()->sum('fill');

        return (int) min(100, round($sum / $slots));
    }

    public function overlaps(int $x, int $y, int $w, int $h): bool
    {
        $f = $this->footprint();

        return $x < $this->pos_x + $f['w'] && $this->pos_x < $x + $w
            && $y < $this->pos_y + $f['h'] && $this->pos_y < $y + $h;
    }
}
