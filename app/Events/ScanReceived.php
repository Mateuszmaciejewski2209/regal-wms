<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Skan z telefonu – trafia na prywatny kanał użytkownika, który sparował urządzenie.
 */
class ScanReceived implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public int $userId, public array $scan) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("scanner.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'scan.received';
    }

    public function broadcastWith(): array
    {
        return $this->scan;
    }
}
