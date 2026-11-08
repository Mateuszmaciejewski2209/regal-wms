<?php

namespace App\Services\Scanner;

use App\Enums\Ability;
use App\Enums\DocumentType;
use App\Enums\WebhookEvent;
use App\Events\ScannerPaired;
use App\Events\ScanReceived;
use App\Exceptions\WarehouseException;
use App\Models\Document;
use App\Models\Product;
use App\Models\ProductLocation;
use App\Models\Rack;
use App\Models\ScanEvent;
use App\Models\ScannerDevice;
use App\Models\Stock;
use App\Models\User;
use App\Services\Documents\DocumentService;
use App\Services\Layout\RackService;
use App\Services\Webhooks\WebhookDispatcher;
use App\Support\Live;
use App\Support\Num;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Telefon jako bezprzewodowy skaner kodów.
 *
 * Przepływ: telefon → POST /api/scanner/scans (webhook HTTP z tokenem urządzenia)
 *           → dopisanie pozycji do aktywnego dokumentu
 *           → broadcast WebSocket (Reverb) do panelu przeglądarki → tabela odświeża się na żywo.
 */
class ScannerService
{
    public const PAIRING_TTL_MINUTES = 10;

    public function __construct(
        private readonly DocumentService $documents,
        private readonly WebhookDispatcher $webhooks,
        private readonly RackService $racks,
    ) {}

    // ───────────── Parowanie ─────────────

    /** Jednorazowy kod parowania (np. „K7P2-9XQM”) ważny 10 minut. */
    public function createPairingCode(User $user): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // bez 0/O, 1/I – łatwe do przepisania
        $raw = '';
        for ($i = 0; $i < 8; $i++) {
            $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $code = substr($raw, 0, 4).'-'.substr($raw, 4);

        Cache::put($this->pairingKey($code), $user->id, now()->addMinutes(self::PAIRING_TTL_MINUTES));

        return $code;
    }

    /**
     * Wymienia kod parowania na stały token urządzenia. Token zwracany jest tylko raz,
     * w bazie przechowywany jest wyłącznie jego skrót SHA-256.
     *
     * @return array{device: ScannerDevice, token: string}|null
     */
    public function pair(string $code, string $deviceName, ?string $ip, ?string $userAgent): ?array
    {
        $userId = Cache::pull($this->pairingKey($code));
        $user = $userId ? User::query()->where('is_active', true)->find($userId) : null;

        if (! $user || ! $user->hasAbility(Ability::ScannerUse)) {
            return null;
        }

        $token = 'rgl_'.Str::random(48);

        $device = $user->scannerDevices()->create([
            'name' => Str::limit(trim($deviceName) ?: 'Telefon', 60, ''),
            'token_hash' => ScannerDevice::hashToken($token),
            'last_seen_at' => now(),
            'last_ip' => $ip,
            'user_agent' => Str::limit((string) $userAgent, 250, ''),
        ]);

        Live::broadcast(new ScannerPaired($device));

        return ['device' => $device, 'token' => $token];
    }

    // ───────────── Cel skanowania ─────────────

    public function setTarget(User $user, ?Document $document): void
    {
        if ($document && ! $document->isDraft()) {
            throw WarehouseException::notDraft();
        }

        $user->forceFill(['scanner_document_id' => $document?->id])->save();

        Live::broadcast(new ScanReceived($user->id, [
            'kind' => 'target',
            'document' => $document ? $this->documentSummary($document) : null,
        ]));
    }

    public function currentTarget(User $user): ?Document
    {
        $document = $user->scannerDocument;

        if ($document && ! $document->isDraft()) {
            $user->forceFill(['scanner_document_id' => null])->save();

            return null;
        }

        return $document;
    }

    // ───────────── Skan ─────────────

    /**
     * @param  string  $mode  add – dopisz do dokumentu, lookup – sprawdź towar i stan, putaway – odłóż na półkę
     * @param  string  $source  phone | keyboard (czytnik USB w panelu) | api
     * @param  string|null  $location  treść QR etykiety półki (tryb putaway)
     * @param  int  $fill  ile miejsca pola zajmie towar (tryb putaway)
     */
    public function handleScan(
        User $user,
        string $code,
        string $quantity = '1',
        string $mode = 'add',
        ?ScannerDevice $device = null,
        ?Document $target = null,
        string $source = 'phone',
        ?string $location = null,
        int $fill = 25,
    ): array {
        $code = trim($code);
        $slot = ProductLocation::parseQr($code);
        $product = $slot ? null : Product::findByCode($code);
        $document = null;
        $item = null;
        $placedAt = null;

        if ($slot) {
            // Etykieta półki: pokazujemy, co na niej leży (w trybie „Rozmieść” telefon zapamiętuje półkę).
            $result = 'location';
            $message = ProductLocation::label($slot['rack'], $slot['level'], $slot['bay']);
        } elseif (! $product) {
            $result = 'not_found';
            $message = str_starts_with($code, ProductLocation::QR_PREFIX)
                ? 'Nie znaleziono takiej półki – etykieta mogła zostać wydrukowana dla usuniętego regału'
                : "Nie znaleziono towaru o kodzie {$code}";
        } elseif ($mode === 'putaway') {
            [$result, $message, $placedAt] = $this->putaway($user, $product, $location, $fill, $quantity);
        } elseif ($mode === 'lookup') {
            $result = 'found';
            $message = $product->name;
        } elseif (! $user->hasAbility(Ability::DocumentsCreate)) {
            $result = 'rejected';
            $message = 'Twoja rola nie może dodawać pozycji do dokumentów – użyj trybu „Sprawdź”';
        } else {
            $document = $target ?? $this->currentTarget($user);

            if (! $document) {
                // Osobny wynik (nie „found”) – telefon sygnalizuje błąd i prosi o wybór dokumentu.
                $result = 'no_target';
                $message = 'Nie wybrano dokumentu – skan nie został dodany. Wybierz dokument u góry ekranu.';
            } elseif (! $document->isDraft()) {
                $result = 'rejected';
                $message = "Dokument {$document->displayNumber()} jest już zatwierdzony – wybierz inny";
                $document = null;
            } else {
                try {
                    $item = $this->documents->addProduct($document, $product, $quantity);
                    $result = 'added';
                    $message = sprintf(
                        '%s %s · razem %s',
                        Num::signedQty($quantity),
                        $product->unit->value,
                        Num::qty($item->quantity, $product->unit),
                    );
                } catch (WarehouseException $e) {
                    $result = 'rejected';
                    $message = $e->getMessage();
                }
            }
        }

        $event = ScanEvent::create([
            'user_id' => $user->id,
            'scanner_device_id' => $device?->id,
            'code' => Str::limit($code, 250, ''),
            'product_id' => $product?->id,
            'document_id' => $document?->id,
            'quantity' => $quantity,
            'result' => $result,
            'source' => $source,
            'message' => Str::limit($message, 250, ''),
        ]);

        $payload = [
            'kind' => 'scan',
            'id' => $event->id,
            'code' => $code,
            'result' => $result,
            'message' => $message,
            'quantity' => $quantity,
            'source' => $source,
            'device' => $device?->name,
            'product' => $product ? $this->productSummary($product, $document) : null,
            'document' => $document ? $this->documentSummary($document) : null,
            'item_quantity' => $item?->quantity !== null ? (string) $item->quantity : null,
            'location' => $slot ? $this->locationSummary($slot['rack'], $slot['level'], $slot['bay']) : $placedAt,
            'at' => $event->created_at->toIso8601String(),
        ];

        Live::broadcast(new ScanReceived($user->id, $payload));
        $this->webhooks->dispatch(WebhookEvent::ScanReceived, $payload);

        return $payload;
    }

    // ───────────── Prezentacja ─────────────

    public function productSummary(Product $product, ?Document $document = null): array
    {
        $stocks = Stock::query()
            ->with('warehouse:id,code,name,color')
            ->where('product_id', $product->id)
            ->get()
            ->map(fn (Stock $stock) => [
                'warehouse' => $stock->warehouse->code,
                'warehouse_name' => $stock->warehouse->name,
                'color' => $stock->warehouse->colorKey(),
                'quantity' => (string) $stock->quantity,
                'formatted' => Num::qty($stock->quantity, $product->unit),
            ])
            ->values();

        $available = $document
            ? (string) ($stocks->firstWhere('warehouse', $document->warehouse?->code)['quantity'] ?? '0')
            : null;

        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'ean' => $product->ean,
            'name' => $product->name,
            'unit' => $product->unit->value,
            'is_active' => $product->is_active,
            'stocks' => $stocks->all(),
            'total' => Num::qty($stocks->sum(fn ($s) => (float) $s['quantity']), $product->unit),
            'available_in_document_warehouse' => $available,
            'locations' => $this->racks->locationsOf($product, $document?->warehouse_id)
                ->map(fn (ProductLocation $l) => ['code' => $l->displayCode(), 'warehouse' => $l->rack->warehouse->code, 'color' => $l->rack->warehouse->colorKey(), 'fill' => $l->fill])
                ->values()->all(),
        ];
    }

    /** Zawartość pola półki – dla telefonu po zeskanowaniu etykiety półki. */
    public function locationSummary(Rack $rack, int $level, int $bay): array
    {
        $rack->loadMissing('warehouse');
        $items = ProductLocation::query()
            ->with('product:id,name,sku,unit')
            ->where(['rack_id' => $rack->id, 'level' => $level, 'bay' => $bay])
            ->get();

        return [
            'qr' => ProductLocation::qrPayload($rack, $level, $bay),
            'code' => ProductLocation::code($rack, $level, $bay),
            'label' => ProductLocation::label($rack, $level, $bay),
            'warehouse' => $rack->warehouse->code,
            'free' => max(0, 100 - (int) $items->sum('fill')),
            'items' => $items->map(fn (ProductLocation $l) => [
                'name' => $l->product->name,
                'sku' => $l->product->sku,
                'fill' => $l->fill,
                'quantity' => $l->quantity !== null ? Num::qty($l->quantity, $l->product->unit) : null,
            ])->values()->all(),
        ];
    }

    /**
     * Tryb „Rozmieść”: towar trafia na ostatnio zeskanowaną półkę. Ponowny skan zwiększa ilość na półce.
     *
     * @return array{0: string, 1: string, 2: array|null}
     */
    private function putaway(User $user, Product $product, ?string $location, int $fill, string $quantity): array
    {
        if (! $user->hasAbility(Ability::LocationsManage)) {
            return ['rejected', 'Twoja rola nie może rozmieszczać towarów na półkach', null];
        }

        $slot = $location ? ProductLocation::parseQr($location) : null;

        if (! $slot) {
            return ['rejected', 'Najpierw zeskanuj etykietę półki, na którą odkładasz towar', null];
        }

        $existing = ProductLocation::query()
            ->where(['rack_id' => $slot['rack']->id, 'level' => $slot['level'], 'bay' => $slot['bay'], 'product_id' => $product->id])
            ->first();
        $newQuantity = bcadd((string) ($existing?->quantity ?? '0'), $quantity, 3);

        try {
            $this->racks->place($product, $slot['rack'], $slot['level'], $slot['bay'], $existing?->fill ?? $fill, $newQuantity);
        } catch (WarehouseException $e) {
            return ['rejected', $e->getMessage(), $this->locationSummary($slot['rack'], $slot['level'], $slot['bay'])];
        }

        return [
            'placed',
            sprintf('Odłożono na %s · razem %s', ProductLocation::code($slot['rack'], $slot['level'], $slot['bay']), Num::qty($newQuantity, $product->unit)),
            $this->locationSummary($slot['rack'], $slot['level'], $slot['bay']),
        ];
    }

    public function documentSummary(Document $document): array
    {
        $document->loadMissing('warehouse:id,code,name');

        return [
            'id' => $document->id,
            'type' => $document->type->value,
            'type_label' => $document->type->label(),
            'number' => $document->displayNumber(),
            'warehouse' => $document->warehouse?->code,
            'items_count' => $document->items()->count(),
            'counts_inventory' => $document->type === DocumentType::INW,
            'url' => route('documents.show', $document),
        ];
    }

    private function pairingKey(string $code): string
    {
        return 'scanner-pair:'.strtoupper(str_replace([' ', '-'], '', $code));
    }
}
