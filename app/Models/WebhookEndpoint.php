<?php

namespace App\Models;

use App\Enums\WebhookEvent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'url', 'secret', 'events', 'is_active'])]
#[Hidden(['secret'])]
class WebhookEndpoint extends Model
{
    /** Domyślne wartości w pamięci (zgodne z domyślnymi kolumn w bazie). */
    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class)->latest('id');
    }

    public function subscribesTo(WebhookEvent $event): bool
    {
        return $event === WebhookEvent::Ping || in_array($event->value, $this->events ?? [], true);
    }
}
