<?php

namespace App\Enums;

enum Unit: string
{
    case Piece = 'szt';
    case Kilogram = 'kg';
    case Meter = 'm';
    case SquareMeter = 'm2';
    case Liter = 'l';
    case Package = 'opak';
    case Set = 'kpl';
    case Pallet = 'pal';

    public function label(): string
    {
        return match ($this) {
            self::Piece => 'sztuka',
            self::Kilogram => 'kilogram',
            self::Meter => 'metr',
            self::SquareMeter => 'metr kw.',
            self::Liter => 'litr',
            self::Package => 'opakowanie',
            self::Set => 'komplet',
            self::Pallet => 'paleta',
        };
    }

    /** Jednostki niepodzielne – ilości całkowite. */
    public function isInteger(): bool
    {
        return in_array($this, [self::Piece, self::Package, self::Set, self::Pallet], true);
    }
}
