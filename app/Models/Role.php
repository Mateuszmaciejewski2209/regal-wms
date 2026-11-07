<?php

namespace App\Models;

use App\Enums\Ability;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable(['slug', 'name', 'description', 'level', 'is_system'])]
class Role extends Model
{
    public const MAX_LEVEL = 100;

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'is_system' => 'boolean',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return Collection<int, string> klucze uprawnień roli */
    public function abilityKeys(): Collection
    {
        if ($this->is_system) {
            return collect(Ability::cases())->map->value;
        }

        return $this->permissions->pluck('key');
    }

    public function allows(Ability|string $ability): bool
    {
        $key = $ability instanceof Ability ? $ability->value : $ability;

        return $this->is_system || $this->abilityKeys()->contains($key);
    }

    /** @param list<string> $keys */
    public function syncAbilities(array $keys): void
    {
        $this->permissions()->sync(Permission::query()->whereIn('key', $keys)->pluck('id'));
        $this->unsetRelation('permissions');
    }

    public function tone(): string
    {
        return match (true) {
            $this->is_system => 'inventory',
            $this->level >= 50 => 'transfer',
            $this->level >= 10 => 'neutral',
            default => 'outline',
        };
    }
}
