<?php

namespace Database\Seeders;

use App\Enums\ContractorType;
use App\Enums\DocumentType;
use App\Enums\Unit;
use App\Models\Category;
use App\Models\Contractor;
use App\Models\Document;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Documents\DocumentService;
use App\Services\Layout\RackService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Dane demonstracyjne: użytkownicy, magazyny, kartoteka towarów i 2 tygodnie obrotu
 * wygenerowane przez DocumentService – stany i kartoteka są spójne z dokumentami.
 */
class DatabaseSeeder extends Seeder
{
    private DocumentService $documents;

    private User $storekeeper;

    private User $manager;

    /** @var array<string, Product> */
    private array $products = [];

    public function run(): void
    {
        $this->documents = app(DocumentService::class);

        // Role i uprawnienia tworzy migracja (dane referencyjne RBAC).
        $role = fn (string $slug) => Role::where('slug', $slug)->value('id');

        $admin = User::create(['name' => 'Anna Kowalska', 'email' => 'admin@regal.test', 'password' => 'haslo1234', 'role_id' => $role('admin')]);
        $this->manager = User::create(['name' => 'Piotr Wiśniewski', 'email' => 'kierownik@regal.test', 'password' => 'haslo1234', 'role_id' => $role('manager')]);
        $this->storekeeper = User::create(['name' => 'Marek Zieliński', 'email' => 'magazynier@regal.test', 'password' => 'haslo1234', 'role_id' => $role('storekeeper')]);
        User::create(['name' => 'Ewa Nowak', 'email' => 'ksiegowosc@regal.test', 'password' => 'haslo1234', 'role_id' => $role('viewer')]);

        $mg1 = Warehouse::create(['code' => 'MG1', 'color' => 'sky', 'name' => 'Magazyn główny', 'address' => 'ul. Przemysłowa 12, 30-701 Kraków']);
        $mg2 = Warehouse::create(['code' => 'MG2', 'color' => 'amber', 'name' => 'Magazyn buforowy', 'address' => 'ul. Składowa 4, 32-020 Wieliczka']);
        $skl = Warehouse::create(['code' => 'SKL', 'color' => 'violet', 'name' => 'Punkt wydań', 'address' => 'ul. Handlowa 1, 30-702 Kraków']);

        $this->seedProducts();
        [$sup1, $sup2, $sup3, $cus1, $cus2, $both] = $this->seedContractors();

        // ── Dwa tygodnie obrotu ──
        $this->doc(13, DocumentType::PZ, $mg1, ['contractor' => $sup1, 'ext' => 'FV/2611/0412'], [
            ['WKR-18V', 12, 289.00], ['WIE-08', 150, 6.50], ['SRB-M8', 40, 24.90], ['KOL-08', 60, 9.90], ['DRB-3x8', 6, 399.00],
        ]);
        $this->doc(13, DocumentType::PZ, $mg1, ['contractor' => $sup3, 'ext' => 'FV/OP/1187'], [
            ['TAS-48', 240, 5.20], ['FOL-23', 80, 18.40], ['KAR-600', 300, 4.10], ['PAL-EUR', 40, 45.00],
        ]);
        $this->doc(12, DocumentType::PZ, $mg1, ['contractor' => $sup2, 'ext' => 'EH/2026/5531'], [
            ['YDY-325', 500, 4.35], ['PRZ-25', 10, 139.00], ['LED-E27', 200, 7.99],
        ]);
        $this->doc(11, DocumentType::WZ, $mg1, ['contractor' => $cus1, 'ext' => 'ZAM/BM/301'], [
            ['WKR-18V', 3], ['WIE-08', 40], ['SRB-M8', 10], ['YDY-325', 120],
        ]);
        $this->doc(10, DocumentType::PZ, $mg1, ['contractor' => $sup1, 'ext' => 'FV/2611/0467'], [
            ['FAR-10L', 20, 119.00], ['SIL-280', 60, 14.50], ['PIA-750', 48, 22.90], ['KLE-25', 30, 32.00], ['CEM-25', 40, 21.50],
            ['RUR-110', 25, 26.40], ['ZAW-12', 30, 17.90], ['WAZ-25', 8, 59.00], ['RKW-NIT', 200, 3.80], ['KSK-397', 15, 29.00], ['KAM-ODB', 40, 8.90],
        ]);
        $this->doc(9, DocumentType::MM, $mg1, ['target' => $skl, 'notes' => 'Zatowarowanie punktu wydań'], [
            ['WKR-18V', 2], ['LED-E27', 60], ['SIL-280', 20], ['PIA-750', 12], ['RKW-NIT', 80], ['TAS-48', 40],
        ]);
        $this->doc(8, DocumentType::WZ, $mg1, ['contractor' => $cus2], [
            ['FAR-10L', 6], ['KLE-25', 12], ['CEM-25', 16], ['PIA-750', 10], ['KOL-08', 15],
        ]);
        $this->doc(7, DocumentType::RW, $mg1, ['notes' => 'Zużycie własne – pakowanie wysyłek'], [
            ['TAS-48', 36], ['FOL-23', 14], ['KAR-600', 70],
        ]);
        $this->doc(6, DocumentType::PZ, $mg1, ['contractor' => $sup2, 'ext' => 'EH/2026/5702'], [
            ['LED-E27', 150, 7.49], ['YDY-325', 300, 4.55], ['PRZ-25', 4, 135.00],
        ]);
        $this->doc(5, DocumentType::WZ, $mg1, ['contractor' => $both, 'ext' => 'TL/88412'], [
            ['PAL-EUR', 18], ['FOL-23', 30], ['KAR-600', 120], ['TAS-48', 60],
        ]);
        $this->doc(4, DocumentType::MM, $mg1, ['target' => $mg2, 'notes' => 'Odciążenie magazynu głównego'], [
            ['CEM-25', 14], ['KLE-25', 10], ['PAL-EUR', 12],
        ]);
        $this->doc(3, DocumentType::WZ, $mg1, ['contractor' => $cus1, 'ext' => 'ZAM/BM/322'], [
            ['WKR-18V', 4], ['DRB-3x8', 2], ['WIE-08', 60], ['ZAW-12', 12], ['RUR-110', 10], ['YDY-325', 240],
        ]);
        $this->doc(2, DocumentType::WZ, $skl, ['contractor' => $cus2], [
            ['LED-E27', 24], ['SIL-280', 9], ['RKW-NIT', 30],
        ]);

        // Inwentaryzacja punktu wydań z różnicami → automatyczne PW i RW
        $this->inventory(2, $skl, ['LED-E27' => 34, 'SIL-280' => 12, 'PIA-750' => 11, 'RKW-NIT' => 50, 'TAS-48' => 40, 'WKR-18V' => 2]);

        $this->doc(1, DocumentType::WZ, $mg1, ['contractor' => $both], [
            ['KAM-ODB', 25], ['KSK-397', 9], ['RKW-NIT', 60], ['WAZ-25', 5],
        ]);
        $this->doc(1, DocumentType::PW, $mg1, ['notes' => 'Zwrot z budowy – towar pełnowartościowy'], [
            ['WIE-08', 6, 6.50], ['KOL-08', 3, 9.90],
        ]);
        $this->doc(0, DocumentType::PZ, $mg1, ['contractor' => $sup3, 'ext' => 'FV/OP/1240'], [
            ['TAS-48', 120, 5.05], ['KAR-600', 150, 4.25],
        ]);

        // Szkice „w toku” – do demonstracji skanera
        $draft = $this->documents->createDraft(DocumentType::PZ, ['warehouse_id' => $mg1->id, 'contractor_id' => $sup1->id, 'external_number' => 'FV/2611/0503'], $this->storekeeper);
        $this->documents->addProduct($draft, $this->products['WKR-18V'], '4', '279.00');
        $this->documents->createDraft(DocumentType::WZ, ['warehouse_id' => $mg1->id, 'contractor_id' => $cus1->id, 'notes' => 'Kompletacja zamówienia ZAM/BM/340'], $this->storekeeper);

        $admin->forceFill(['scanner_document_id' => $draft->id])->save();

        $this->seedRacks($mg1, $mg2, $skl);
    }

    /**
     * Regały z konfiguratora (REG-01…REG-16, B-01…B-03, A-01…A-04) i rozmieszczenie towarów
     * zgodne ze stanami – każdy towar ze stanem leży na półce, część także na drugim miejscu.
     */
    private function seedRacks(Warehouse $mg1, Warehouse $mg2, Warehouse $skl): void
    {
        $racks = app(RackService::class);

        $layouts = [
            [$mg1, $racks->createBatch($mg1, $racks->codes('REG-', 1, 16, 2), 4, 3, 8)],
            [$mg2, $racks->createBatch($mg2, $racks->codes('B-', 1, 3, 2), 3, 3, 3)],
            [$skl, $racks->createBatch($skl, $racks->codes('A-', 1, 4, 2), 3, 2, 4)],
        ];

        $fills = [25, 50, 75, 40, 60, 30];

        foreach ($layouts as [$warehouse, $warehouseRacks]) {
            $i = 0;
            foreach ($this->products as $product) {
                $quantity = (string) $product->stocks()->where('warehouse_id', $warehouse->id)->value('quantity');

                if (bccomp($quantity ?: '0', '0', 3) <= 0) {
                    continue;
                }

                $rack = $warehouseRacks[$i % $warehouseRacks->count()];
                $level = intdiv($i, $warehouseRacks->count()) % $rack->levels + 1;
                $bay = $i % $rack->bays + 1;
                $split = $i % 5 === 0 && bccomp($quantity, '1', 3) > 0;

                $racks->place($product, $rack, $level, $bay, $fills[$i % count($fills)], $split ? bcdiv($quantity, '2', 3) : $quantity);

                // Co piąty towar ma zapas na drugim regale (pokazuje wiele lokalizacji).
                if ($split) {
                    $overflow = $warehouseRacks[($i + 3) % $warehouseRacks->count()];
                    $racks->place($product, $overflow, $overflow->levels, $overflow->bays, 25, bcsub($quantity, bcdiv($quantity, '2', 3), 3));
                }

                $i++;
            }
        }
    }

    private function seedProducts(): void
    {
        $categories = collect(['Elektronarzędzia', 'Akcesoria i złączki', 'Opakowania', 'BHP', 'Elektryka', 'Chemia budowlana', 'Hydraulika', 'Ogród'])
            ->mapWithKeys(fn ($name) => [$name => Category::create(['name' => $name])]);

        $rows = [
            ['WKR-18V', 'Wkrętarka akumulatorowa 18 V, 2 aku', 'Elektronarzędzia', Unit::Piece, 289.00, 5],
            ['DRB-3x8', 'Drabina aluminiowa 3 × 8 stopni', 'Elektronarzędzia', Unit::Piece, 399.00, 2],
            ['WIE-08', 'Wiertło do betonu SDS 8 × 160 mm', 'Akcesoria i złączki', Unit::Piece, 6.50, 50],
            ['SRB-M8', 'Śruby M8 × 40 ocynk (100 szt.)', 'Akcesoria i złączki', Unit::Package, 24.90, 10],
            ['KOL-08', 'Kołki rozporowe 8 × 40 (50 szt.)', 'Akcesoria i złączki', Unit::Package, 9.90, 20],
            ['TAS-48', 'Taśma pakowa 48 mm × 66 m, brązowa', 'Opakowania', Unit::Piece, 5.20, 60],
            ['FOL-23', 'Folia stretch 23 µm, 1,5 kg', 'Opakowania', Unit::Piece, 18.40, 30],
            ['KAR-600', 'Karton klapowy 600 × 400 × 400 mm', 'Opakowania', Unit::Piece, 4.10, 100],
            ['PAL-EUR', 'Paleta EUR 1200 × 800 mm', 'Opakowania', Unit::Pallet, 45.00, 10],
            ['RKW-NIT', 'Rękawice robocze nitrylowe, rozm. 9', 'BHP', Unit::Piece, 3.80, 80],
            ['KSK-397', 'Kask ochronny EN 397', 'BHP', Unit::Piece, 29.00, 8],
            ['KAM-ODB', 'Kamizelka odblaskowa, żółta', 'BHP', Unit::Piece, 8.90, 20],
            ['YDY-325', 'Przewód YDY 3 × 2,5 mm²', 'Elektryka', Unit::Meter, 4.35, 200],
            ['PRZ-25', 'Przedłużacz bębnowy 25 m, 4 gniazda', 'Elektryka', Unit::Piece, 139.00, 3],
            ['LED-E27', 'Żarówka LED E27 9 W, 806 lm', 'Elektryka', Unit::Piece, 7.99, 50],
            ['FAR-10L', 'Farba akrylowa biała, 10 l', 'Chemia budowlana', Unit::Piece, 119.00, 6],
            ['SIL-280', 'Silikon sanitarny bezbarwny 280 ml', 'Chemia budowlana', Unit::Piece, 14.50, 20],
            ['PIA-750', 'Pianka montażowa 750 ml', 'Chemia budowlana', Unit::Piece, 22.90, 15],
            ['KLE-25', 'Klej do płytek C2TE, 25 kg', 'Chemia budowlana', Unit::Piece, 32.00, 10],
            ['CEM-25', 'Cement portlandzki CEM I 42,5R, 25 kg', 'Chemia budowlana', Unit::Piece, 21.50, 10],
            ['RUR-110', 'Rura kanalizacyjna PCV 110 mm × 2 m', 'Hydraulika', Unit::Piece, 26.40, 8],
            ['ZAW-12', 'Zawór kulowy 1/2" GW-GW', 'Hydraulika', Unit::Piece, 17.90, 10],
            ['WAZ-25', 'Wąż ogrodowy 1/2", 25 m', 'Ogród', Unit::Piece, 59.00, 4],
            ['PIA-KG', 'Piasek kwarcowy suszony', 'Ogród', Unit::Kilogram, 0.85, 0],
        ];

        foreach ($rows as $i => [$sku, $name, $category, $unit, $price, $min]) {
            $this->products[$sku] = Product::create([
                'sku' => $sku,
                'ean' => self::ean13('590'.str_pad((string) (4417000 + $i * 37), 9, '0', STR_PAD_LEFT)),
                'name' => $name,
                'category_id' => $categories[$category]->id,
                'unit' => $unit,
                'default_price' => $price,
                'min_stock' => $min,
                'vat_rate' => 23,
            ]);
        }
    }

    /** @return list<Contractor> */
    private function seedContractors(): array
    {
        $rows = [
            ['Hurtownia Narzędzi Kowalczyk sp. z o.o.', '677012', ContractorType::Supplier, 'ul. Mogilska 80', '31-546', 'Kraków'],
            ['Elektro-Hurt Śląsk S.A.', '634201', ContractorType::Supplier, 'ul. Chorzowska 150', '40-101', 'Katowice'],
            ['Opakowania Pomorze sp. z o.o.', '583145', ContractorType::Supplier, 'ul. Elbląska 54', '80-724', 'Gdańsk'],
            ['Budomax sp.j.', '898210', ContractorType::Customer, 'ul. Legnicka 55', '54-203', 'Wrocław'],
            ['Remonty i Wykończenia Nowak', '779233', ContractorType::Customer, 'ul. Głogowska 210', '60-104', 'Poznań'],
            ['TransLog Polska sp. z o.o.', '727280', ContractorType::Both, 'ul. Rokicińska 162', '92-412', 'Łódź'],
        ];

        return array_map(fn ($r) => Contractor::create([
            'name' => $r[0],
            'nip' => self::nip($r[1]),
            'type' => $r[2],
            'street' => $r[3],
            'postal_code' => $r[4],
            'city' => $r[5],
            'email' => 'biuro@'.str($r[0])->before(' ')->ascii()->lower().'.example',
        ]), $rows);
    }

    /**
     * @param  list<array{0: string, 1: int|float, 2?: float}>  $lines  [sku, ilość, cena?]
     */
    private function doc(int $daysAgo, DocumentType $type, Warehouse $warehouse, array $opts, array $lines): Document
    {
        $at = Carbon::today()->subDays($daysAgo)->setTime(random_int(7, 15), random_int(0, 59));
        Carbon::setTestNow($at);

        $author = $daysAgo % 3 === 0 ? $this->manager : $this->storekeeper;

        $document = $this->documents->createDraft($type, [
            'warehouse_id' => $warehouse->id,
            'target_warehouse_id' => ($opts['target'] ?? null)?->id,
            'contractor_id' => ($opts['contractor'] ?? null)?->id,
            'issued_at' => $at->toDateString(),
            'external_number' => $opts['ext'] ?? null,
            'notes' => $opts['notes'] ?? null,
        ], $author);

        foreach ($lines as $line) {
            $this->documents->addProduct($document, $this->products[$line[0]], (string) $line[1], isset($line[2]) ? (string) $line[2] : null);
        }

        Carbon::setTestNow($at->copy()->addMinutes(random_int(5, 50)));
        $document = $this->documents->approve($document, $author);
        Carbon::setTestNow();

        return $document;
    }

    /** @param array<string, int|float> $counted */
    private function inventory(int $daysAgo, Warehouse $warehouse, array $counted): void
    {
        $at = Carbon::today()->subDays($daysAgo)->setTime(16, 30);
        Carbon::setTestNow($at);

        $inw = $this->documents->createDraft(DocumentType::INW, [
            'warehouse_id' => $warehouse->id,
            'issued_at' => $at->toDateString(),
            'notes' => 'Inwentaryzacja okresowa punktu wydań',
            'prefill' => true,
        ], $this->manager);

        foreach ($counted as $sku => $quantity) {
            $inw->items()->where('product_id', $this->products[$sku]->id)->update(['quantity' => $quantity]);
        }

        $this->documents->approve($inw, $this->manager);
        Carbon::setTestNow();
    }

    private static function ean13(string $twelve): string
    {
        $twelve = substr($twelve, 0, 12);
        $sum = 0;
        foreach (str_split($twelve) as $i => $digit) {
            $sum += (int) $digit * ($i % 2 === 0 ? 1 : 3);
        }

        return $twelve.((10 - $sum % 10) % 10);
    }

    /** Fikcyjny, ale poprawny formalnie NIP (suma kontrolna). */
    private static function nip(string $seed): string
    {
        $weights = [6, 5, 7, 2, 3, 4, 5, 6, 7];
        for ($suffix = 0; $suffix < 1000; $suffix++) {
            $nine = substr($seed.str_pad((string) $suffix, 3, '0', STR_PAD_LEFT), 0, 9);
            $sum = 0;
            foreach (str_split($nine) as $i => $digit) {
                $sum += (int) $digit * $weights[$i];
            }
            if ($sum % 11 !== 10) {
                return $nine.($sum % 11);
            }
        }

        return $seed.'0000';
    }
}
