<?php

namespace App\Exceptions;

use DomainException;

/**
 * Naruszenie reguły biznesowej gospodarki magazynowej.
 * Komunikat jest przeznaczony bezpośrednio dla użytkownika.
 */
class WarehouseException extends DomainException
{
    public static function insufficientStock(string $product, string $warehouse, string $available, string $requested): self
    {
        return new self("Niewystarczający stan towaru „{$product}” w magazynie {$warehouse}: dostępne {$available}, wymagane {$requested}.");
    }

    public static function notDraft(): self
    {
        return new self('Dokument nie jest szkicem – nie można go zmieniać.');
    }

    public static function notApproved(): self
    {
        return new self('Anulować (storno) można tylko zatwierdzony dokument.');
    }

    public static function emptyDocument(): self
    {
        return new self('Dokument nie ma żadnych pozycji.');
    }

    public static function missing(string $what): self
    {
        return new self("Uzupełnij pole: {$what}.");
    }

    public static function sameWarehouse(): self
    {
        return new self('Magazyn docelowy musi być inny niż źródłowy.');
    }

    public static function invalidQuantity(string $product): self
    {
        return new self("Nieprawidłowa ilość dla pozycji „{$product}”.");
    }

    public static function inactiveProduct(string $product): self
    {
        return new self("Towar „{$product}” jest nieaktywny.");
    }

    public static function inventoryInProgress(string $warehouse, string $inventory): self
    {
        return new self("Magazyn {$warehouse} jest zablokowany – trwa inwentaryzacja {$inventory}. Zatwierdź lub usuń arkusz spisu, aby księgować inne dokumenty.");
    }

    public static function inventoryAlreadyOpen(string $warehouse, string $inventory): self
    {
        return new self("W magazynie {$warehouse} trwa już inwentaryzacja {$inventory}.");
    }

    public static function inventoryWarehouseFixed(): self
    {
        return new self('Magazynu inwentaryzacji nie można zmienić – usuń arkusz i utwórz nowy dla innego magazynu.');
    }

    public static function rackCodesTaken(array $codes): self
    {
        $list = implode(', ', array_slice($codes, 0, 5)).(count($codes) > 5 ? '…' : '');

        return new self("Regały o tych kodach już istnieją w magazynie: {$list}.");
    }

    public static function rackOutsidePlan(): self
    {
        return new self('Regał wychodzi poza plan magazynu – powiększ plan albo przesuń go bliżej.');
    }

    public static function rackOverlap(string $other): self
    {
        return new self("Regał nachodzi na {$other}. Wybierz wolne miejsce na planie.");
    }

    public static function slotOutsideRack(): self
    {
        return new self('Taka półka lub pole nie istnieje w tym regale.');
    }

    public static function slotFull(int $free): self
    {
        return new self("Na tym polu zostało tylko {$free}% wolnego miejsca.");
    }

    public static function rackShrinkWouldOrphan(): self
    {
        return new self('Na usuwanych półkach lub polach leżą towary – najpierw je przenieś.');
    }

    public static function generatedDocument(): self
    {
        return new self('Ten dokument powstał z inwentaryzacji – anuluj dokument INW.');
    }
}
