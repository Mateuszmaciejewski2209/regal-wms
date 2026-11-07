<?php

namespace App\Livewire\Webhooks;

use App\Enums\WebhookEvent;
use App\Jobs\SendWebhook;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Webhooki')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public ?string $revealedSecret = null;

    public ?int $inspectId = null;

    /** boot() działa przy każdym żądaniu Livewire – chroni też akcje (ping, usuń, nowy sekret…). */
    public function boot(): void
    {
        Gate::authorize('webhooks.manage');
    }

    public function create(): void
    {
        $this->editingId = null;
        $this->form = ['name' => '', 'url' => '', 'events' => [WebhookEvent::DocumentApproved->value], 'is_active' => true];
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $endpoint = WebhookEndpoint::findOrFail($id);
        $this->editingId = $id;
        $this->form = $endpoint->only(['name', 'url', 'events', 'is_active']);
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function save(): void
    {
        Gate::authorize('webhooks.manage');

        $data = $this->validate([
            'form.name' => ['required', 'string', 'max:100'],
            'form.url' => ['required', 'url:http,https', 'max:500'],
            'form.events' => ['required', 'array', 'min:1'],
            'form.events.*' => [Rule::enum(WebhookEvent::class)],
            'form.is_active' => ['boolean'],
        ], ['form.events.required' => 'Wybierz co najmniej jedno zdarzenie.'], ['form.name' => 'nazwa', 'form.url' => 'adres URL'])['form'];

        if ($this->editingId) {
            WebhookEndpoint::findOrFail($this->editingId)->update($data);
        } else {
            $secret = WebhookDispatcher::generateSecret();
            WebhookEndpoint::create($data + ['secret' => $secret]);
            $this->revealedSecret = $secret;
        }

        $this->showForm = false;
        $this->dispatch('toast', title: 'Zapisano webhook', message: $data['url'], tone: 'success');
    }

    public function ping(int $id, WebhookDispatcher $dispatcher): void
    {
        $delivery = $dispatcher->ping(WebhookEndpoint::findOrFail($id));

        $this->dispatch('toast',
            title: $delivery->status === 'success' ? 'Odbiorca odpowiedział' : 'Test nieudany',
            message: $delivery->response_status ? "HTTP {$delivery->response_status} · {$delivery->duration_ms} ms" : 'Brak odpowiedzi serwera',
            tone: $delivery->status === 'success' ? 'success' : 'danger',
        );
    }

    public function rotateSecret(int $id): void
    {
        $secret = WebhookDispatcher::generateSecret();
        WebhookEndpoint::findOrFail($id)->update(['secret' => $secret]);
        $this->revealedSecret = $secret;
    }

    public function delete(int $id): void
    {
        WebhookEndpoint::whereKey($id)->delete();
    }

    public function retry(int $deliveryId): void
    {
        $delivery = WebhookDelivery::findOrFail($deliveryId);
        $delivery->update(['status' => 'pending']);
        SendWebhook::dispatch($delivery->id);
    }

    public function render()
    {
        return view('livewire.webhooks.index', [
            'endpoints' => WebhookEndpoint::query()->withCount('deliveries')->orderBy('name')->get(),
            'deliveries' => WebhookDelivery::query()->with('endpoint:id,name')->latest('id')->limit(30)->get(),
            'inspected' => $this->inspectId ? WebhookDelivery::find($this->inspectId) : null,
            'events' => WebhookEvent::subscribable(),
        ]);
    }
}
