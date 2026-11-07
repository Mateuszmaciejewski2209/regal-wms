<div wire:poll.10s.visible>
    <x-ui.page-header title="Webhooki" description="Powiadomienia HTTP do systemów zewnętrznych (ERP, sklep, Slack) o zdarzeniach magazynowych.">
        <x-slot:actions>
            <x-ui.button variant="primary" icon="plus" wire:click="create">Nowy webhook</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($revealedSecret)
        <div class="mb-3 rounded-xl border border-accent-line bg-accent-soft p-4" role="status" x-data="{ copied: false }">
            <p class="font-medium text-ink">Sekret podpisu – zapisz go teraz, później nie będzie widoczny.</p>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <code class="code rounded-md bg-surface px-2 py-1 break-all text-ink">{{ $revealedSecret }}</code>
                <x-ui.button size="sm" icon="copy" x-on:click="navigator.clipboard.writeText('{{ $revealedSecret }}'); copied = true">
                    <span x-text="copied ? 'Skopiowano' : 'Kopiuj'">Kopiuj</span>
                </x-ui.button>
                <x-ui.button size="sm" variant="ghost" wire:click="$set('revealedSecret', null)">Ukryj</x-ui.button>
            </div>
        </div>
    @endif

    <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <section class="card overflow-hidden" aria-labelledby="ep-title">
            <div class="border-b border-line px-4 py-3"><h2 id="ep-title" class="font-semibold">Odbiorcy</h2></div>
            @if ($endpoints->isEmpty())
                <x-ui.empty icon="webhook" title="Brak webhooków" description="Dodaj adres przyciskiem „Nowy webhook” – Regał wyśle tam zdarzenia. Do testów użyj np. webhook.site." />
            @else
                <ul class="divide-y divide-line">
                    @foreach ($endpoints as $ep)
                        <li class="px-4 py-3" wire:key="ep-{{ $ep->id }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 font-medium text-ink">
                                        {{ $ep->name }}
                                        @unless ($ep->is_active)<x-ui.badge tone="outline">wyłączony</x-ui.badge>@endunless
                                    </div>
                                    <div class="code truncate text-xs text-ink-3">{{ $ep->url }}</div>
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        @foreach ($ep->events as $event)<x-ui.badge class="font-mono">{{ $event }}</x-ui.badge>@endforeach
                                    </div>
                                </div>
                                <x-ui.dropdown>
                                    <x-slot:trigger><x-ui.button size="sm" icon-only icon="ellipsis" aria-label="Akcje: {{ $ep->name }}" /></x-slot:trigger>
                                    <x-ui.dropdown-item icon="send" wire:click="ping({{ $ep->id }})">Wyślij test (ping)</x-ui.dropdown-item>
                                    <x-ui.dropdown-item icon="square-pen" wire:click="edit({{ $ep->id }})">Edytuj</x-ui.dropdown-item>
                                    <x-ui.dropdown-item icon="key-round"
                                        x-on:click="$confirm({{ Js::from(['title' => 'Wygenerować nowy sekret?', 'message' => 'Dotychczasowy sekret przestanie działać – odbiorca „'.$ep->name.'” musi dostać nowy, by weryfikować podpisy.', 'confirm' => 'Generuj nowy']) }}).then(ok => ok && $wire.rotateSecret({{ $ep->id }}))">Nowy sekret</x-ui.dropdown-item>
                                    <x-ui.dropdown-item icon="trash-2" danger
                                        x-on:click="$confirm({{ Js::from(['title' => 'Usunąć webhook „'.$ep->name.'”?', 'message' => 'Odbiorca przestanie dostawać zdarzenia, a historia doręczeń zostanie usunięta.', 'confirm' => 'Usuń webhook']) }}).then(ok => ok && $wire.delete({{ $ep->id }}))">Usuń</x-ui.dropdown-item>
                                </x-ui.dropdown>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            <details class="border-t border-line bg-surface-2/50 px-4 py-3 text-[0.8125rem]">
                <summary class="cursor-pointer font-medium text-ink-2">Weryfikacja podpisu po stronie odbiorcy</summary>
                <div class="mt-2 space-y-2 text-ink-3">
                    <p>Nagłówki: <span class="code">X-Regal-Event</span>, <span class="code">X-Regal-Delivery</span>, <span class="code">X-Regal-Timestamp</span>, <span class="code">X-Regal-Signature</span>.</p>
                    <pre class="code overflow-x-auto rounded-lg bg-surface p-3 text-ink-2">$expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);
hash_equals($expected, $signature) &amp;&amp; abs(time() - $timestamp) &lt; 300;</pre>
                    <p>Odpowiedź 2xx = doręczono. Inaczej 5 prób z rosnącym odstępem (10 s → 10 min).</p>
                </div>
            </details>
        </section>

        <section class="card overflow-hidden" aria-labelledby="del-title">
            <div class="flex items-center justify-between border-b border-line px-4 py-3">
                <h2 id="del-title" class="font-semibold">Ostatnie doręczenia</h2>
                <span class="text-xs text-ink-4">odświeżane co 10 s</span>
            </div>
            @if ($deliveries->isEmpty())
                <x-ui.empty icon="history" title="Brak doręczeń" description="Zatwierdź dokument lub wyślij test, by zobaczyć wysyłkę." />
            @else
                <div class="max-h-[36rem] overflow-auto">
                    <table class="table">
                        <thead><tr><th>Czas</th><th>Zdarzenie</th><th class="hidden sm:table-cell">Odbiorca</th><th>Status</th><th class="num">HTTP</th><th class="w-10"></th></tr></thead>
                        <tbody>
                            @foreach ($deliveries as $d)
                                <tr wire:key="dl-{{ $d->id }}">
                                    <td class="num text-left whitespace-nowrap text-ink-3">{{ $d->created_at->format('H:i:s') }}</td>
                                    <td class="code">{{ $d->event }}</td>
                                    <td class="hidden max-w-32 truncate text-ink-3 sm:table-cell">{{ $d->endpoint?->name }}</td>
                                    <td>
                                        <x-ui.badge :tone="['success' => 'success', 'failed' => 'danger', 'pending' => 'warn'][$d->status] ?? 'neutral'">
                                            {{ ['success' => 'doręczono', 'failed' => 'błąd', 'pending' => 'w kolejce'][$d->status] ?? $d->status }}
                                        </x-ui.badge>
                                        @if ($d->attempts > 1)<span class="ml-1 text-xs text-ink-4">×{{ $d->attempts }}</span>@endif
                                    </td>
                                    <td class="num text-ink-3">{{ $d->response_status ?? '—' }}</td>
                                    <td class="text-right">
                                        <button type="button" wire:click="$set('inspectId', {{ $d->id }})" class="btn btn-ghost btn-icon btn-sm" aria-label="Pokaż treść doręczenia"><x-lucide-eye aria-hidden="true" /></button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>

    @if ($inspected)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/45 sm:items-center sm:p-6" x-data x-on:keydown.escape.window="$wire.set('inspectId', null)" x-on:click.self="$wire.set('inspectId', null)" role="dialog" aria-modal="true" aria-label="Szczegóły doręczenia">
            <div class="enter-up max-h-[90dvh] w-full overflow-y-auto rounded-t-2xl border border-line bg-surface shadow-pop sm:max-w-2xl sm:rounded-2xl" x-trap.noscroll="true">
                <div class="flex items-center justify-between border-b border-line px-5 py-4">
                    <div>
                        <h2 class="font-semibold">{{ $inspected->event }}</h2>
                        <p class="code text-xs text-ink-4">{{ $inspected->uuid }}</p>
                    </div>
                    <div class="flex gap-2">
                        @if ($inspected->status === 'failed')<x-ui.button size="sm" icon="refresh-cw" wire:click="retry({{ $inspected->id }})">Ponów</x-ui.button>@endif
                        <button type="button" class="btn btn-ghost btn-icon btn-sm" wire:click="$set('inspectId', null)" aria-label="Zamknij"><x-lucide-x aria-hidden="true" /></button>
                    </div>
                </div>
                <div class="space-y-3 px-5 py-4 text-[0.8125rem]">
                    <div class="text-ink-3">Próby: {{ $inspected->attempts }} · czas: {{ $inspected->duration_ms ?? '—' }} ms · HTTP {{ $inspected->response_status ?? '—' }}</div>
                    <div>
                        <div class="label">Wysłana treść (JSON)</div>
                        <pre class="code max-h-72 overflow-auto rounded-lg bg-surface-2 p-3 text-ink-2">{{ json_encode($inspected->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                    @if ($inspected->response_body)
                        <div>
                            <div class="label">Odpowiedź odbiorcy</div>
                            <pre class="code max-h-40 overflow-auto rounded-lg bg-surface-2 p-3 whitespace-pre-wrap text-ink-2">{{ $inspected->response_body }}</pre>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <x-ui.modal wire:model="showForm" :title="$editingId ? 'Edycja webhooka' : 'Nowy webhook'" max-width="lg">
        <form wire:submit="save" id="wh-form" class="grid gap-4">
            <x-ui.field label="Nazwa" for="wh-name" error="form.name" required>
                <input id="wh-name" type="text" wire:model="form.name" class="control" placeholder="np. ERP – Comarch" autocomplete="off">
            </x-ui.field>
            <x-ui.field label="Adres URL odbiorcy" for="wh-url" error="form.url" required hint="Żądania POST z JSON i podpisem HMAC-SHA256.">
                <input id="wh-url" type="url" wire:model="form.url" class="control font-mono" placeholder="https://…" autocomplete="off" spellcheck="false">
            </x-ui.field>
            <fieldset>
                <legend class="label">Zdarzenia</legend>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($events as $event)
                        <label class="flex items-start gap-2.5 rounded-lg border border-line p-2.5 has-[:checked]:border-ink-4 has-[:checked]:bg-surface-2">
                            <input type="checkbox" value="{{ $event->value }}" wire:model="form.events" class="mt-0.5">
                            <span><span class="block text-[0.8125rem] font-medium text-ink">{{ $event->label() }}</span><span class="code block text-xs text-ink-4">{{ $event->value }}</span></span>
                        </label>
                    @endforeach
                </div>
                @error('form.events')<p class="mt-1.5 text-[0.8125rem] text-danger">{{ $message }}</p>@enderror
            </fieldset>
            <label class="flex items-center gap-2 text-ink-2"><input type="checkbox" wire:model="form.is_active"> Aktywny</label>
        </form>
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="$wire.showForm = false">Anuluj</x-ui.button>
            <x-ui.button type="submit" form="wh-form" variant="primary">Zapisz</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
