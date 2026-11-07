<?php

namespace App\Models;

use App\Enums\Ability;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role_id', 'is_active', 'scanner_document_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Domyślne wartości w pamięci (zgodne z domyślnymi kolumn w bazie). */
    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** Dokument, do którego trafiają skany z telefonów tego użytkownika. */
    public function scannerDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'scanner_document_id');
    }

    public function scannerDevices(): HasMany
    {
        return $this->hasMany(ScannerDevice::class);
    }

    public function hasAbility(Ability|string $ability): bool
    {
        return (bool) $this->role?->loadMissing('permissions')->allows($ability);
    }

    public function isAdmin(): bool
    {
        return (bool) $this->role?->is_system;
    }

    public function level(): int
    {
        return $this->isAdmin() ? Role::MAX_LEVEL + 1 : (int) $this->role?->level;
    }

    /** Hierarchia: zarządzać można tylko kontami o niższym poziomie roli (administrator – wszystkimi). */
    public function outranks(User $other): bool
    {
        return $this->isAdmin() || $this->level() > $other->level();
    }

    public function canAssignRole(Role $role): bool
    {
        return $this->isAdmin() || (! $role->is_system && $role->level < $this->level());
    }

    public function initials(): string
    {
        return collect(explode(' ', $this->name))
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }
}
