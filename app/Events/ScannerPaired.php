<?php

namespace App\Events;

use App\Models\ScannerDevice;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class ScannerPaired implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public ScannerDevice $device) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("scanner.{$this->device->user_id}")];
    }

    public function broadcastAs(): string
    {
        return 'scanner.paired';
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->device->id, 'name' => $this->device->name];
    }
}
