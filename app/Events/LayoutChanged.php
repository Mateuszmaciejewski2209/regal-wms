<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Zmiana układu regałów lub rozmieszczenia towarów – plan magazynu odświeża się u wszystkich. */
class LayoutChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public int $warehouseId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('warehouse')];
    }

    public function broadcastAs(): string
    {
        return 'layout.changed';
    }

    public function broadcastWith(): array
    {
        return ['warehouse_id' => $this->warehouseId];
    }
}
