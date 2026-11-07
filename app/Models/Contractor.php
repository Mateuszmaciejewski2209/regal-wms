<?php

namespace App\Models;

use App\Enums\ContractorType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'nip', 'type', 'street', 'postal_code', 'city', 'email', 'phone', 'is_active'])]
class Contractor extends Model
{
    use HasFactory;

    /** Domyślne wartości w pamięci (zgodne z domyślnymi kolumn w bazie). */
    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'type' => ContractorType::class,
            'is_active' => 'boolean',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function scopeSuppliers(Builder $query): void
    {
        $query->whereIn('type', [ContractorType::Supplier, ContractorType::Both]);
    }

    public function scopeCustomers(Builder $query): void
    {
        $query->whereIn('type', [ContractorType::Customer, ContractorType::Both]);
    }

    public function address(): string
    {
        return collect([$this->street, trim("{$this->postal_code} {$this->city}")])
            ->filter()
            ->implode(', ');
    }

    public function formattedNip(): ?string
    {
        if (! $this->nip || strlen($this->nip) !== 10) {
            return $this->nip;
        }

        return substr($this->nip, 0, 3).'-'.substr($this->nip, 3, 3).'-'.substr($this->nip, 6, 2).'-'.substr($this->nip, 8, 2);
    }
}
