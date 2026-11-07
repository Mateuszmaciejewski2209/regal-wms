<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Pozycje szkicu zmieniły się (np. skan z telefonu) – otwarty edytor dokumentu odświeża tabelę na żywo.
 */
class DocumentItemsChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public int $documentId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("documents.{$this->documentId}")];
    }

    public function broadcastAs(): string
    {
        return 'items.changed';
    }

    public function broadcastWith(): array
    {
        return ['document_id' => $this->documentId];
    }
}
