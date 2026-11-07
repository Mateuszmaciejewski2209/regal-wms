<?php

namespace App\Models;

use App\Enums\Unit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['sku', 'ean', 'name', 'category_id', 'unit', 'min_stock', 'default_price', 'vat_rate', 'description', 'is_active'])]
class Product extends Model
{
    use HasFactory;

    /** Domyślne wartości w pamięci (zgodne z domyślnymi kolumn w bazie). */
    protected $attributes = ['is_active' => true];

    /** Prefiks treści kodu QR na etykiecie – pozwala odróżnić etykiety systemu od obcych kodów. */
    public const QR_PREFIX = 'RGL:';

    protected function casts(): array
    {
        return [
            'unit' => Unit::class,
            'min_stock' => 'decimal:3',
            'default_price' => 'decimal:2',
            'vat_rate' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(ProductLocation::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Towary poniżej stanu minimalnego – jedna definicja dla pulpitu, stanów i webhooka stock.low:
     * łączny stan we wszystkich magazynach < min_stock (gdy min_stock > 0).
     */
    public function scopeBelowMinimum(Builder $query): void
    {
        $query->where('products.min_stock', '>', 0)
            ->whereRaw('(select coalesce(sum(s.quantity), 0) from stocks s where s.product_id = products.id) < products.min_stock');
    }

    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $q) use ($term) {
            // whereLike: bez rozróżniania wielkości liter także w PostgreSQL (ILIKE)
            $q->whereLike('name', "%{$term}%")
                ->orWhereLike('sku', "%{$term}%")
                ->orWhereLike('ean', "%{$term}%");
        });
    }

    /**
     * Znajduje towar po zeskanowanym kodzie: treść etykiety QR (RGL:SKU), indeks lub EAN.
     */
    public static function findByCode(string $code): ?self
    {
        $code = trim($code);

        if (str_starts_with($code, self::QR_PREFIX)) {
            $code = substr($code, strlen(self::QR_PREFIX));
        }

        if ($code === '') {
            return null;
        }

        return static::query()
            ->where('sku', $code)
            ->orWhere('ean', $code)
            ->first();
    }

    public function qrPayload(): string
    {
        return self::QR_PREFIX.$this->sku;
    }

    public function totalQuantity(): string
    {
        return (string) $this->stocks()->sum('quantity');
    }
}
