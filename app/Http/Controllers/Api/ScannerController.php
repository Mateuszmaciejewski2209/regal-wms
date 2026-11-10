<?php

namespace App\Http\Controllers\Api;

use App\Enums\Ability;
use App\Enums\DocumentStatus;
use App\Enums\Unit;
use App\Exceptions\WarehouseException;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Product;
use App\Models\ScannerDevice;
use App\Rules\GtinCheckDigit;
use App\Services\Scanner\ScannerService;
use App\Support\Num;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * API mobilnego skanera (PWA na telefonie).
 */
class ScannerController extends Controller
{
    public function __construct(private readonly ScannerService $scanner) {}

    /** POST /api/scanner/pair – wymiana jednorazowego kodu z panelu na token urządzenia. */
    public function pair(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['nullable', 'string', 'max:60'],
        ]);

        $paired = $this->scanner->pair($data['code'], $data['name'] ?? 'Telefon', $request->ip(), $request->userAgent());

        if (! $paired) {
            return response()->json(['message' => 'Kod parowania jest nieprawidłowy lub wygasł. Wygeneruj nowy w panelu.'], 422);
        }

        return response()->json([
            'token' => $paired['token'],
            'device' => ['id' => $paired['device']->id, 'name' => $paired['device']->name],
            'user' => ['name' => $paired['device']->user->name],
        ], 201);
    }

    /** GET /api/scanner/me – stan urządzenia i aktywny dokument. */
    public function me(Request $request): JsonResponse
    {
        $device = $this->device($request);
        $target = $this->scanner->currentTarget($device->user);

        return response()->json([
            'device' => ['id' => $device->id, 'name' => $device->name],
            'user' => [
                'name' => $device->user->name,
                'can_manage_products' => $device->user->hasAbility(Ability::ProductsManage),
            ],
            'units' => collect(Unit::cases())->map(fn (Unit $unit) => ['value' => $unit->value, 'label' => $unit->label()])->all(),
            'target' => $target ? $this->scanner->documentSummary($target) : null,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /** POST /api/scanner/products – nowy towar z nieznanym kodem EAN zeskanowanym telefonem (np. przy przyjęciu dostawy). */
    public function createProduct(Request $request): JsonResponse
    {
        if (! $this->device($request)->user->hasAbility(Ability::ProductsManage)) {
            return response()->json(['message' => 'Twoja rola nie może dodawać towarów do kartoteki. Poproś kierownika magazynu.'], 403);
        }

        $data = $request->validate([
            'ean' => ['required', 'string', 'regex:/^\d{8}$|^\d{12,14}$/', new GtinCheckDigit, Rule::unique('products', 'ean')],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9._\-\/]+$/', Rule::unique('products', 'sku')],
            'unit' => ['required', Rule::enum(Unit::class)],
        ], [
            'ean.regex' => 'EAN musi mieć 8 lub 12–14 cyfr.',
            'ean.unique' => 'Towar z tym kodem EAN już jest w kartotece.',
            'sku.regex' => 'Indeks może zawierać litery, cyfry oraz znaki . _ - /',
            'sku.unique' => 'Taki indeks już istnieje.',
        ]);

        $product = Product::create([
            'ean' => $data['ean'],
            'name' => trim($data['name']),
            'sku' => ($data['sku'] ?? '') !== '' ? $data['sku'] : Product::nextSku(),
            'unit' => $data['unit'],
            'min_stock' => '0',
            'vat_rate' => 23,
            'is_active' => true,
        ]);

        return response()->json([
            'product' => ['id' => $product->id, 'sku' => $product->sku, 'name' => $product->name, 'ean' => $product->ean],
        ], 201);
    }

    /** GET /api/scanner/documents – otwarte szkice, które można wybrać jako cel skanowania. */
    public function documents(Request $request): JsonResponse
    {
        if (! $this->device($request)->user->hasAbility(Ability::DocumentsCreate)) {
            return response()->json(['data' => []]);
        }

        $documents = Document::query()
            ->with('warehouse:id,code')
            ->withCount('items')
            ->where('status', DocumentStatus::Draft)
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn (Document $d) => [
                'id' => $d->id,
                'type' => $d->type->value,
                'type_label' => $d->type->label(),
                'number' => $d->displayNumber(),
                'warehouse' => $d->warehouse?->code,
                'items_count' => $d->items_count,
                'created_at' => $d->created_at->diffForHumans(),
            ]);

        return response()->json(['data' => $documents]);
    }

    /** POST /api/scanner/target – ustaw/wyczyść dokument docelowy. */
    public function target(Request $request): JsonResponse
    {
        $data = $request->validate(['document_id' => ['nullable', 'integer', 'exists:documents,id']]);
        $user = $this->device($request)->user;
        $document = isset($data['document_id']) ? Document::find($data['document_id']) : null;

        if ($document && ! $user->hasAbility(Ability::DocumentsCreate)) {
            return response()->json(['message' => 'Twoja rola nie może dodawać pozycji do dokumentów.'], 403);
        }

        try {
            $this->scanner->setTarget($user, $document);
        } catch (WarehouseException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'target' => $document ? $this->scanner->documentSummary($document) : null,
        ]);
    }

    /** POST /api/scanner/scans – pojedynczy skan (webhook z telefonu). */
    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:250'],
            'quantity' => ['nullable'],
            'mode' => ['nullable', 'in:add,lookup,putaway'],
            // Dokument wybrany w telefonie w chwili skanu – skany z kolejki offline nie trafią do innego dokumentu.
            'document_id' => ['nullable', 'integer'],
            'location' => ['nullable', 'string', 'max:120'],
            'fill' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $quantity = Num::parse($data['quantity'] ?? '1') ?? '1';

        if (bccomp($quantity, '0', 3) <= 0) {
            return response()->json(['message' => 'Ilość musi być większa od zera.'], 422);
        }

        $device = $this->device($request);
        $target = isset($data['document_id']) ? Document::find($data['document_id']) : null;

        $payload = $this->scanner->handleScan(
            user: $device->user,
            code: $data['code'],
            quantity: $quantity,
            mode: $data['mode'] ?? 'add',
            device: $device,
            target: $target,
            location: $data['location'] ?? null,
            fill: (int) ($data['fill'] ?? 25),
        );

        return response()->json($payload);
    }

    /** DELETE /api/scanner/session – telefon sam się odłącza. */
    public function unpair(Request $request): JsonResponse
    {
        $this->device($request)->forceFill(['revoked_at' => now()])->save();

        return response()->json(['ok' => true]);
    }

    private function device(Request $request): ScannerDevice
    {
        return $request->attributes->get('scanner_device');
    }
}
