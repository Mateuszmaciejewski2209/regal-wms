<?php

namespace App\Enums;

enum WebhookEvent: string
{
    case DocumentApproved = 'document.approved';
    case DocumentCancelled = 'document.cancelled';
    case StockLow = 'stock.low';
    case ScanReceived = 'scan.received';
    case Ping = 'ping';

    public function label(): string
    {
        return match ($this) {
            self::DocumentApproved => 'Zatwierdzenie dokumentu',
            self::DocumentCancelled => 'Anulowanie dokumentu',
            self::StockLow => 'Stan poniżej minimum',
            self::ScanReceived => 'Skan z urządzenia',
            self::Ping => 'Test połączenia',
        };
    }

    /** @return list<self> */
    public static function subscribable(): array
    {
        return [self::DocumentApproved, self::DocumentCancelled, self::StockLow, self::ScanReceived];
    }
}
