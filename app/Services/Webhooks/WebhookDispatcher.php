<?php

namespace App\Services\Webhooks;

use App\Enums\WebhookEvent;
use App\Jobs\SendWebhook;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Str;

/**
 * Webhooki wychodzące: każde zdarzenie trafia do kolejki osobno dla każdego subskrybenta.
 *
 * Podpis (jak w Stripe/GitHub): X-Regal-Signature = sha256=HMAC_SHA256(secret, "{timestamp}.{body}").
 * Odbiorca weryfikuje podpis i odrzuca wiadomości ze znacznikiem czasu starszym niż 5 minut (ochrona przed replay).
 */
class WebhookDispatcher
{
    public function dispatch(WebhookEvent $event, array $data): void
    {
        $endpoints = WebhookEndpoint::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (WebhookEndpoint $endpoint) => $endpoint->subscribesTo($event));

        foreach ($endpoints as $endpoint) {
            SendWebhook::dispatch($this->createDelivery($endpoint, $event, $data)->id);
        }
    }

    /** Test połączenia – wysyłany synchronicznie, by od razu pokazać wynik w panelu. */
    public function ping(WebhookEndpoint $endpoint): WebhookDelivery
    {
        $delivery = $this->createDelivery($endpoint, WebhookEvent::Ping, ['message' => 'Test połączenia z Regał WMS']);

        try {
            SendWebhook::dispatchSync($delivery->id);
        } catch (\Throwable) {
            $delivery->update(['status' => 'failed']);
        }

        return $delivery->fresh();
    }

    public static function sign(string $body, string $secret, int $timestamp): string
    {
        return 'sha256='.hash_hmac('sha256', "{$timestamp}.{$body}", $secret);
    }

    public static function generateSecret(): string
    {
        return 'whsec_'.Str::random(40);
    }

    private function createDelivery(WebhookEndpoint $endpoint, WebhookEvent $event, array $data): WebhookDelivery
    {
        $uuid = (string) Str::uuid();

        return $endpoint->deliveries()->create([
            'uuid' => $uuid,
            'event' => $event->value,
            'payload' => [
                'id' => $uuid,
                'event' => $event->value,
                'created_at' => now()->toIso8601String(),
                'data' => $data,
            ],
            'status' => 'pending',
        ]);
    }
}
