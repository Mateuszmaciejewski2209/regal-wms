<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Models\Contractor;
use App\Models\Document;
use App\Models\Product;
use App\Models\Role;
use App\Models\ScannerDevice;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Documents\DocumentService;
use App\Services\Scanner\ScannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScannerApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->product = Product::factory()->create(['sku' => 'TW-00001', 'ean' => '5901234123457']);
    }

    public function test_phone_pairs_with_one_time_code(): void
    {
        $code = app(ScannerService::class)->createPairingCode($this->user);

        $response = $this->postJson('/api/scanner/pair', ['code' => $code, 'name' => 'Pixel 9'])
            ->assertCreated()
            ->assertJsonPath('device.name', 'Pixel 9');

        $token = $response->json('token');
        $this->assertStringStartsWith('rgl_', $token);
        $this->assertDatabaseMissing('scanner_devices', ['token_hash' => $token]); // tylko skrót w bazie

        $this->withToken($token)->getJson('/api/scanner/me')
            ->assertOk()
            ->assertJsonPath('user.name', $this->user->name);

        // kod jest jednorazowy
        $this->postJson('/api/scanner/pair', ['code' => $code])->assertUnprocessable();
    }

    public function test_scan_adds_item_to_target_document(): void
    {
        $token = $this->pairedToken();
        $document = $this->draft();
        $this->withToken($token)->postJson('/api/scanner/target', ['document_id' => $document->id])->assertOk();

        $this->withToken($token)->postJson('/api/scanner/scans', ['code' => 'RGL:TW-00001'])
            ->assertOk()
            ->assertJsonPath('result', 'added')
            ->assertJsonPath('product.sku', 'TW-00001');

        $this->withToken($token)->postJson('/api/scanner/scans', ['code' => '5901234123457', 'quantity' => '2,5'])
            ->assertOk()
            ->assertJsonPath('item_quantity', '3.500');

        $this->assertSame('3.500', $document->items()->first()->quantity);
        $this->assertDatabaseCount('scan_events', 2);
    }

    public function test_lookup_mode_does_not_modify_document(): void
    {
        $token = $this->pairedToken();
        $document = $this->draft();
        $this->user->update(['scanner_document_id' => $document->id]);

        $this->withToken($token)->postJson('/api/scanner/scans', ['code' => 'TW-00001', 'mode' => 'lookup'])
            ->assertOk()
            ->assertJsonPath('result', 'found');

        $this->assertSame(0, $document->items()->count());
    }

    public function test_unknown_code_is_reported(): void
    {
        $this->withToken($this->pairedToken())->postJson('/api/scanner/scans', ['code' => 'NIEZNANY'])
            ->assertOk()
            ->assertJsonPath('result', 'not_found');
    }

    public function test_manager_adds_new_product_from_unknown_ean_and_rescan_adds_it_to_document(): void
    {
        $this->user->update(['role_id' => Role::where('slug', 'manager')->value('id')]);
        $token = $this->pairedToken();
        $document = $this->draft();
        $this->withToken($token)->postJson('/api/scanner/target', ['document_id' => $document->id])->assertOk();
        $this->withToken($token)->getJson('/api/scanner/me')->assertJsonPath('user.can_manage_products', true);

        $this->withToken($token)->postJson('/api/scanner/scans', ['code' => '4006381333931', 'document_id' => $document->id])
            ->assertJsonPath('result', 'not_found');

        $this->withToken($token)->postJson('/api/scanner/products', ['ean' => '4006381333931', 'name' => 'Kasa fiskalna', 'unit' => 'szt'])
            ->assertCreated()
            ->assertJsonPath('product.ean', '4006381333931');

        $product = Product::where('ean', '4006381333931')->firstOrFail();
        $this->assertMatchesRegularExpression('/^TW-\d{5}$/', $product->sku);

        $this->withToken($token)->postJson('/api/scanner/scans', ['code' => '4006381333931', 'document_id' => $document->id])
            ->assertJsonPath('result', 'added');
        $this->assertDatabaseHas('document_items', ['document_id' => $document->id, 'product_id' => $product->id]);
    }

    public function test_new_product_from_scanner_requires_product_rights_and_valid_unique_ean(): void
    {
        $token = $this->pairedToken(); // magazynier: bez prawa do kartoteki
        $this->withToken($token)->getJson('/api/scanner/me')->assertJsonPath('user.can_manage_products', false);
        $this->withToken($token)->postJson('/api/scanner/products', ['ean' => '4006381333931', 'name' => 'Kasa', 'unit' => 'szt'])
            ->assertForbidden();
        $this->assertDatabaseMissing('products', ['ean' => '4006381333931']);

        $this->user->update(['role_id' => Role::where('slug', 'manager')->value('id')]);
        $this->withToken($token)->postJson('/api/scanner/products', ['ean' => '4006381333932', 'name' => 'Kasa', 'unit' => 'szt'])
            ->assertUnprocessable()->assertJsonValidationErrors('ean'); // zła cyfra kontrolna
        $this->withToken($token)->postJson('/api/scanner/products', ['ean' => '5901234123457', 'name' => 'Duplikat', 'unit' => 'szt'])
            ->assertUnprocessable()->assertJsonValidationErrors('ean'); // kod już w kartotece
    }

    public function test_revoked_device_is_rejected(): void
    {
        $token = $this->pairedToken();
        ScannerDevice::query()->update(['revoked_at' => now()]);

        $this->withToken($token)->getJson('/api/scanner/me')->assertUnauthorized();
        $this->postJson('/api/scanner/scans', ['code' => 'x'])->assertUnauthorized();
    }

    public function test_scan_requests_do_not_use_up_the_pairing_limit(): void
    {
        $token = $this->pairedToken();

        for ($i = 0; $i < 12; $i++) {
            $this->withToken($token)->getJson('/api/scanner/me')->assertOk();
        }

        $this->assertNotNull($this->pairedToken());
    }

    private function pairedToken(): string
    {
        $code = app(ScannerService::class)->createPairingCode($this->user);

        return $this->postJson('/api/scanner/pair', ['code' => $code])->json('token');
    }

    private function draft(): Document
    {
        return app(DocumentService::class)->createDraft(DocumentType::PZ, [
            'warehouse_id' => Warehouse::factory()->create()->id,
            'contractor_id' => Contractor::factory()->create()->id,
        ], $this->user);
    }
}
