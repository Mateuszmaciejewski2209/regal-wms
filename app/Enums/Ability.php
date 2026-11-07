<?php

namespace App\Enums;

/**
 * Uprawnienia systemu (RBAC). Wartość = klucz w tabeli permissions i nazwa bramki Gate.
 */
enum Ability: string
{
    case DocumentsView = 'documents.view';
    case DocumentsCreate = 'documents.create';
    case DocumentsApprove = 'documents.approve';
    case InventoryApprove = 'inventory.approve';
    case DocumentsCancel = 'documents.cancel';
    case StockView = 'stock.view';
    case StockExport = 'stock.export';
    case ProductsManage = 'products.manage';
    case ContractorsManage = 'contractors.manage';
    case WarehousesManage = 'warehouses.manage';
    case ScannerUse = 'scanner.use';
    case LocationsManage = 'locations.manage';
    case LayoutManage = 'layout.manage';
    case WebhooksManage = 'webhooks.manage';
    case UsersManage = 'users.manage';
    case RolesManage = 'roles.manage';

    public function label(): string
    {
        return match ($this) {
            self::DocumentsView => 'Podgląd dokumentów',
            self::DocumentsCreate => 'Tworzenie i edycja szkiców',
            self::DocumentsApprove => 'Zatwierdzanie PZ, WZ, PW, RW, MM',
            self::InventoryApprove => 'Zatwierdzanie inwentaryzacji',
            self::DocumentsCancel => 'Storno zatwierdzonych dokumentów',
            self::StockView => 'Podgląd stanów i kartotek',
            self::StockExport => 'Eksport stanów do CSV',
            self::ProductsManage => 'Zarządzanie towarami',
            self::ContractorsManage => 'Zarządzanie kontrahentami',
            self::WarehousesManage => 'Magazyny i kategorie',
            self::ScannerUse => 'Skaner mobilny i czytnik',
            self::LocationsManage => 'Rozmieszczanie towarów na półkach',
            self::LayoutManage => 'Konfiguracja regałów i planu hali',
            self::WebhooksManage => 'Integracje (webhooki)',
            self::UsersManage => 'Zarządzanie kontami',
            self::RolesManage => 'Role i uprawnienia',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::DocumentsView, self::DocumentsCreate, self::DocumentsApprove, self::InventoryApprove, self::DocumentsCancel => 'Dokumenty',
            self::StockView, self::StockExport, self::ScannerUse, self::LocationsManage, self::LayoutManage => 'Magazyn',
            self::ProductsManage, self::ContractorsManage, self::WarehousesManage => 'Kartoteki',
            self::WebhooksManage, self::UsersManage, self::RolesManage => 'Administracja',
        };
    }

    /** @return array<string, list<self>> */
    public static function grouped(): array
    {
        $groups = [];
        foreach (self::cases() as $case) {
            $groups[$case->group()][] = $case;
        }

        return $groups;
    }
}
