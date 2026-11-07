<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'type', 'number', 'status', 'warehouse_id', 'target_warehouse_id', 'contractor_id', 'parent_id',
    'issued_at', 'external_number', 'notes', 'total_value', 'created_by',
])]
class Document extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'status' => DocumentStatus::class,
            'issued_at' => 'date',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'total_value' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(DocumentItem::class)->orderBy('position')->orderBy('id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function targetWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'target_warehouse_id');
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Dokumenty wygenerowane z tego dokumentu (np. PW/RW z inwentaryzacji). */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function scopeOfType(Builder $query, DocumentType|string|null $type): void
    {
        if ($type) {
            $query->where('type', $type instanceof DocumentType ? $type->value : $type);
        }
    }

    public function scopeDrafts(Builder $query): void
    {
        $query->where('status', DocumentStatus::Draft);
    }

    public function isDraft(): bool
    {
        return $this->status === DocumentStatus::Draft;
    }

    public function isApproved(): bool
    {
        return $this->status === DocumentStatus::Approved;
    }

    /** Numer dokumentu albo oznaczenie szkicu, np. „PZ · szkic #12”. */
    public function displayNumber(): string
    {
        return $this->number ?? "{$this->type->value} · szkic #{$this->id}";
    }

    /** Chwila, do której odnosi się godzina na listach: zatwierdzenie, a dla szkicu utworzenie. */
    public function stampedAt(): ?CarbonInterface
    {
        return $this->approved_at ?? $this->created_at;
    }

    /**
     * Data dokumentu z godziną, np. „07.11.2026 14:32”. Gdy data dokumentu różni się od dnia
     * zatwierdzenia (dokument wystawiony z datą wsteczną), godzina nie jest doklejana do cudzej daty.
     */
    public function dateTimeLabel(): string
    {
        $stamp = $this->stampedAt();

        return $stamp && $this->issued_at->isSameDay($stamp)
            ? $this->issued_at->format('d.m.Y').' '.$stamp->format('H:i')
            : $this->issued_at->format('d.m.Y');
    }
}
