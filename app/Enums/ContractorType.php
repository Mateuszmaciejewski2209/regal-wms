<?php

namespace App\Enums;

enum ContractorType: string
{
    case Supplier = 'supplier';
    case Customer = 'customer';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Supplier => 'Dostawca',
            self::Customer => 'Odbiorca',
            self::Both => 'Dostawca i odbiorca',
        };
    }
}
