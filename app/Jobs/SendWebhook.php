<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Doręczenie pojedynczego webhooka z ponawianiem (backoff wykładniczy).
 */
class SendWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 20;

    public function __construct(public int $deliveryId) {}

    /** @return list<int> sekundy między kolejnymi próbami */
    public function backoff(): array
    {
        return [10, 30, 120, 600];
    }

    public function handle(): void
    {
        $delivery = WebhookDelivery::with('endpoint')->find($this->deliveryId);

        if (! $delivery || ! $delivery->endpoint) {
            return;
        }

        $body = json_encode($delivery->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = now()->timestamp;
        $started = hrtime(true);

        $delivery->increment('attempts');

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'RegalWMS-Webhooks/1.0',
                    'X-Regal-Event' => $delivery->event,
                    'X-Regal-Delivery' => $delivery->uuid,
                    'X-Regal-Timestamp' => (string) $timestamp,
                    'X-Regal-Signature' => WebhookDispatcher::sign($body, $delivery->endpoint->secret, $timestamp),
                ])
                ->withBody($body, 'application/json')
                ->post($delivery->endpoint->url);
        } catch (Throwable $e) {
            $this->record($delivery, null, $e->getMessage(), $started);

            throw $e;
        }

        $this->record($delivery, $response->status(), $response->body(), $started);

        if (! $response->successful()) {
            throw new RuntimeException("Webhook zwrócił HTTP {$response->status()}");
        }

        $delivery->update(['status' => 'success', 'delivered_at' => now()]);
    }

    public function failed(?Throwable $exception): void
    {
        WebhookDelivery::whereKey($this->deliveryId)->update(['status' => 'failed']);
    }

    private function record(WebhookDelivery $delivery, ?int $status, string $body, int $started): void
    {
        $delivery->update([
            'response_status' => $status,
            'response_body' => Str::limit($body, 2000),
            'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
        ]);
    }
}
