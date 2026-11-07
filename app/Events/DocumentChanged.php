<?php

namespace App\Events;

use App\Models\Document;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Zmiana dokumentu (utworzenie, zatwierdzenie, anulowanie) – odświeża pulpit i listy u wszystkich użytkowników.
 */
class DocumentChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public Document $document, public string $action) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('warehouse')];
    }

    public function broadcastAs(): string
    {
        return 'document.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->document->id,
            'type' => $this->document->type->value,
            'number' => $this->document->displayNumber(),
            'status' => $this->document->status->value,
            'action' => $this->action,
        ];
    }
}
