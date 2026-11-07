<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Szkic',
            self::Approved => 'Zatwierdzony',
            self::Cancelled => 'Anulowany',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Approved => 'success',
            self::Cancelled => 'danger',
        };
    }
}
