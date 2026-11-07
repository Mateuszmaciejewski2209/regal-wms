<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Wysyłka zdarzeń w czasie rzeczywistym (Reverb/WebSocket) z łagodną degradacją:
 * brak serwera WebSocket nie może przerwać operacji magazynowej ani skanu.
 */
final class Live
{
    public static function broadcast(object $event): void
    {
        try {
            broadcast($event);
        } catch (Throwable $e) {
            Log::warning('Broadcast nieudany: '.$e->getMessage(), ['event' => $event::class]);
        }
    }
}
