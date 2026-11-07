<?php

namespace App\Enums;

/**
 * Typy dokumentów magazynowych (zgodnie z praktyką obrotu towarowego w Polsce).
 */
enum DocumentType: string
{
    case PZ = 'PZ';   // Przyjęcie zewnętrzne – dostawa od dostawcy
    case WZ = 'WZ';   // Wydanie zewnętrzne – wydanie do odbiorcy
    case PW = 'PW';   // Przychód wewnętrzny – np. z produkcji, nadwyżka
    case RW = 'RW';   // Rozchód wewnętrzny – np. zużycie, niedobór, szkoda
    case MM = 'MM';   // Przesunięcie międzymagazynowe
    case INW = 'INW'; // Inwentaryzacja (arkusz spisu z natury)

    public function label(): string
    {
        return match ($this) {
            self::PZ => 'Przyjęcie zewnętrzne',
            self::WZ => 'Wydanie zewnętrzne',
            self::PW => 'Przychód wewnętrzny',
            self::RW => 'Rozchód wewnętrzny',
            self::MM => 'Przesunięcie międzymagazynowe',
            self::INW => 'Inwentaryzacja',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PZ => 'Dostawa towaru od dostawcy',
            self::WZ => 'Wydanie towaru do odbiorcy',
            self::PW => 'Przyjęcie z produkcji lub nadwyżki',
            self::RW => 'Zużycie własne, szkoda, niedobór',
            self::MM => 'Przeniesienie między magazynami',
            self::INW => 'Spis z natury i rozliczenie różnic',
        };
    }

    /** Kierunek ruchu: in – przychód, out – rozchód, transfer – MM, inventory – INW. */
    public function direction(): string
    {
        return match ($this) {
            self::PZ, self::PW => 'in',
            self::WZ, self::RW => 'out',
            self::MM => 'transfer',
            self::INW => 'inventory',
        };
    }

    public function isIncoming(): bool
    {
        return $this->direction() === 'in';
    }

    public function isOutgoing(): bool
    {
        return $this->direction() === 'out';
    }

    public function requiresContractor(): bool
    {
        return in_array($this, [self::PZ, self::WZ], true);
    }

    public function requiresTargetWarehouse(): bool
    {
        return $this === self::MM;
    }

    /** Czy użytkownik wpisuje cenę na pozycji (przychody). Rozchody wyceniane są średnią ważoną. */
    public function hasEnteredPrices(): bool
    {
        return $this->isIncoming();
    }

    public function contractorLabel(): string
    {
        return $this === self::PZ ? 'Dostawca' : 'Odbiorca';
    }

    /** Klasa koloru dla odznaki (tokeny zdefiniowane w app.css). */
    public function tone(): string
    {
        return match ($this->direction()) {
            'in' => 'in',
            'out' => 'out',
            'transfer' => 'transfer',
            'inventory' => 'inventory',
        };
    }
}
