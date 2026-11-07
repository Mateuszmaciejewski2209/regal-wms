<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\WebhookEvent;
use App\Models\Contractor;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WebhookEndpoint;
use App\Services\Documents\DocumentService;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_sends_signed_webhook(): void
    {
        Http::fake(['https://erp.example.test/*' => Http::response(['ok' => true])]);

        $endpoint = WebhookEndpoint::create([
            'name' => 'ERP',
            'url' => 'https://erp.example.test/hooks/wms',
            'secret' => 'whsec_test',
            'events' => [WebhookEvent::DocumentApproved->value],
        ]);

        $user = User::factory()->create();
        $service = app(DocumentService::class);
        $pz = $service->createDraft(DocumentType::PZ, [
            'warehouse_id' => Warehouse::factory()->create()->id,
            'contractor_id' => Contractor::factory()->create()->id,
        ], $user);
        $service->addProduct($pz, Product::factory()->create(), '3', '9.99');
        $service->approve($pz, $user);

        Http::assertSent(function (Request $request) {
            $expected = WebhookDispatcher::sign($request->body(), 'whsec_test', (int) $request->header('X-Regal-Timestamp')[0]);

            return $request->header('X-Regal-Event')[0] === 'document.approved'
                && $request->header('X-Regal-Signature')[0] === $expected
                && $request['data']['type'] === 'PZ'
                && $request['data']['items'][0]['quantity'] === '3.000';
        });

        $this->assertSame('success', $endpoint->deliveries()->first()->status);
    }

    public function test_endpoint_receives_only_subscribed_events(): void
    {
        Http::fake();

        WebhookEndpoint::create([
            'name' => 'Tylko alerty',
            'url' => 'https://alerts.example.test',
            'secret' => 's',
            'events' => [WebhookEvent::StockLow->value],
        ]);

        app(WebhookDispatcher::class)->dispatch(WebhookEvent::DocumentApproved, ['id' => 1]);

        Http::assertNothingSent();
    }
}
