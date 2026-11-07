<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'token_hash', 'last_seen_at', 'last_ip', 'user_agent', 'revoked_at'])]
#[Hidden(['token_hash'])]
class ScannerDevice extends Model
{
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(ScanEvent::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at');
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findByToken(?string $token): ?self
    {
        if (! $token) {
            return null;
        }

        return static::query()->active()->where('token_hash', self::hashToken($token))->first();
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null && $this->last_seen_at->gt(now()->subMinutes(2));
    }
}
