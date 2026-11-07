<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'color', 'name', 'address', 'is_active', 'plan_width', 'plan_height'])]
class Warehouse extends Model
{
    use HasFactory;

    /** Paleta kolorów magazynów (klucz = klasa CSS .wh-{klucz}). Bez zieleni – ta oznacza w aplikacji sukces. */
    public const COLORS = [
        'sky' => 'Niebieski',
        'amber' => 'Bursztynowy',
        'violet' => 'Fioletowy',
        'rose' => 'Różowy',
        'teal' => 'Morski',
        'orange' => 'Pomarańczowy',
        'indigo' => 'Indygo',
        'slate' => 'Grafitowy',
    ];

    /** Domyślne wartości w pamięci (zgodne z domyślnymi kolumn w bazie). */
    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'plan_width' => 'integer', 'plan_height' => 'integer'];
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function racks(): HasMany
    {
        return $this->hasMany(Rack::class)->orderBy('code');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function label(): string
    {
        return "{$this->code} · {$this->name}";
    }

    /** Kolor magazynu; brak wybranego → kolor z palety wg identyfikatora (stabilny). */
    public function colorKey(): string
    {
        if (isset(self::COLORS[$this->color ?? ''])) {
            return $this->color;
        }

        $keys = array_keys(self::COLORS);

        return $keys[max(0, ((int) $this->id) - 1) % count($keys)];
    }

    /** Pierwszy kolor z palety, którego nie używa jeszcze żaden magazyn. */
    public static function nextFreeColor(): string
    {
        $used = static::query()->pluck('color')->filter()->all();

        return collect(array_keys(self::COLORS))->first(fn ($key) => ! in_array($key, $used, true)) ?? 'slate';
    }
}
