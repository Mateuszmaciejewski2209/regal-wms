<div>
    <x-ui.page-header title="Skanery mobilne" description="Twój telefon działa jak bezprzewodowy czytnik kodów – zeskanowany towar pojawia się w dokumencie od razu.">
        <x-slot:actions>
            <x-ui.button :href="route('scanner.app')" :navigate="false" target="_blank" icon="external-link">Otwórz skaner</x-ui.button>
            <x-ui.button variant="primary" icon="qr-code" wire:click="startPairing">Sparuj telefon</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Jak zacząć – kroki dla magazyniera --}}
    <section class="card mb-3 p-4" aria-label="Jak zacząć">
        <ol class="grid gap-4 text-[0.8125rem] sm:grid-cols-4">
            @foreach ([
                ['qr-code', 'Sparuj telefon', 'Kliknij „Sparuj telefon” i zeskanuj kod aparatem telefonu.'],
                ['file-plus-2', 'Wybierz dokument', 'W dokumencie kliknij „Podłącz skaner” albo wybierz dokument w telefonie.'],
                ['scan-line', 'Skanuj', 'Etykieta QR towaru lub kod EAN – każdy skan dodaje pozycję.'],
                ['map-pin', 'Odkładaj na półki', 'Tryb „Rozmieść”: zeskanuj etykietę półki, potem towary.'],
            ] as [$icon, $title, $text])
                <li class="flex gap-3">
                    <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-surface-3 text-ink-2"><x-dynamic-component :component="'lucide-'.$icon" class="size-4" aria-hidden="true" /></span>
                    <div><div class="font-medium text-ink">{{ $loop->iteration }}. {{ $title }}</div><p class="text-ink-3">{{ $text }}</p></div>
                </li>
            @endforeach
        </ol>
    </section>

    <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)]">
        <section class="card overflow-hidden" aria-labelledby="dev-title">
            <div class="border-b border-line px-4 py-3"><h2 id="dev-title" class="font-semibold">Moje urządzenia</h2></div>
            @if ($devices->isEmpty())
                <x-ui.empty icon="smartphone" title="Brak sparowanych telefonów" description="Użyj „Sparuj telefon” u góry, by skanować etykiety i dodawać pozycje do dokumentów." />
            @else
                <ul class="divide-y divide-line">
                    @foreach ($devices as $device)
                        <li class="flex items-center gap-3 px-4 py-3" wire:key="dev-{{ $device->id }}">
                            <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-surface-3 text-ink-2"><x-lucide-smartphone class="size-4" aria-hidden="true" /></span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 font-medium text-ink">
                                    {{ $device->name }}
                                    @if ($device->isOnline())<x-ui.badge tone="success"><span class="live-dot"></span> połączony</x-ui.badge>@endif
                                </div>
                                <div class="truncate text-xs text-ink-4">
                                    Sparowano {{ $device->created_at->format('d.m.Y H:i') }}
                                    · ostatnio {{ $device->last_seen_at?->diffForHumans() ?? 'nigdy' }}
                                    @if ($device->last_ip) · {{ $device->last_ip }} @endif
                                </div>
                            </div>
                            <x-ui.button size="sm" variant="ghost" icon="unlink"
                                x-on:click="$confirm({{ Js::from(['title' => 'Odłączyć „'.$device->name.'”?', 'message' => 'Token urządzenia przestanie działać – telefon trzeba będzie sparować ponownie.', 'confirm' => 'Odłącz']) }}).then(ok => ok && $wire.revoke({{ $device->id }}))">Odłącz</x-ui.button>
                        </li>
                    @endforeach
                </ul>
            @endif

            <div class="border-t border-line bg-surface-2/50 p-4 text-[0.8125rem] text-ink-3">
                <p class="font-medium text-ink-2">Adres dla telefonu: <span class="code text-ink">{{ $baseUrl }}/skaner</span></p>
                @if (! $isSecure && auth()->user()->isAdmin())
                    <p class="mt-1.5 text-pretty">
                        <x-lucide-info class="mr-1 inline size-3.5 text-warn" aria-hidden="true" />
                        Kamera w przeglądarce telefonu wymaga HTTPS. W Dockerze uruchom
                        <span class="code">docker compose --profile tunnel up -d</span> – adres tunelu pojawi się tu automatycznie.
                        Bez Dockera: <span class="code">cloudflared tunnel --url http://localhost:8000</span> i <span class="code">SCANNER_PUBLIC_URL</span> w <span class="code">.env</span>.
                    </p>
                @elseif (! $isSecure)
                    <p class="mt-1.5 text-pretty"><x-lucide-info class="mr-1 inline size-3.5 text-warn" aria-hidden="true" />Aparat w telefonie działa tylko przez bezpieczne połączenie (HTTPS). Jeśli skaner nie widzi kamery, poproś administratora o uruchomienie tunelu – kody można też wpisywać ręcznie.</p>
                @endif
            </div>
        </section>

        <section class="card overflow-hidden" aria-labelledby="log-title">
            <div class="flex items-center justify-between border-b border-line px-4 py-3">
                <h2 id="log-title" class="flex items-center gap-2 font-semibold"><span class="live-dot" x-show="$store.live.state === 'connected'"></span> Dziennik skanów</h2>
                <span class="text-xs text-ink-4">ostatnie 25</span>
            </div>
            @if ($scans->isEmpty())
                <x-ui.empty icon="scan-barcode" title="Jeszcze nic nie zeskanowano" />
            @else
                <div class="max-h-[32rem] overflow-auto">
                    <table class="table">
                        <thead><tr><th>Czas</th><th>Kod</th><th>Towar</th><th class="hidden md:table-cell">Dokument</th><th>Wynik</th></tr></thead>
                        <tbody>
                            @foreach ($scans as $scan)
                                <tr wire:key="se-{{ $scan->id }}" @class(['enter-up' => $loop->first])>
                                    <td class="num text-left whitespace-nowrap text-ink-3">{{ $scan->created_at->format('H:i:s') }}</td>
                                    <td class="code max-w-36 truncate">{{ $scan->code }}</td>
                                    <td class="max-w-48 truncate">
                                        @if ($scan->product)
                                            {{ $scan->product->name }}
                                        @elseif ($scan->locationLabel())
                                            <span class="text-ink-3"><x-lucide-map-pin class="mr-0.5 inline size-3.5 -translate-y-px" aria-hidden="true" />{{ $scan->locationLabel() }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="code hidden text-ink-3 md:table-cell">{{ $scan->document?->displayNumber() ?? '—' }}</td>
                                    <td>
                                        <x-ui.badge :tone="$scan->resultTone()" :icon="$scan->source === 'keyboard' ? 'keyboard' : 'smartphone'"
                                                    title="{{ $scan->message }}">
                                            {{ $scan->resultLabel() }}
                                        </x-ui.badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>

    <x-ui.modal wire:model="showPairing" title="Sparuj telefon" description="Zeskanuj kod aparatem telefonu lub otwórz adres i wpisz kod ręcznie." max-width="md">
        @if ($qr)
            {{-- Odpytywanie co 3 s: okno zamknie się po sparowaniu nawet bez WebSocketu. --}}
            <div class="flex flex-col items-center gap-4 text-center" wire:poll.3s="checkPaired"
                 x-data="{ left: {{ max(0, $expiresIn) }}, timer: null }"
                 x-init="timer = setInterval(() => left = Math.max(0, left - 1), 1000)" x-on:remove="clearInterval(timer)">
                <div class="rounded-xl bg-white p-4 text-black transition-opacity" :class="left === 0 && 'opacity-20'">{!! $qr !!}</div>
                <div>
                    <div class="text-xs font-medium tracking-wide text-ink-4 uppercase">Kod parowania</div>
                    <div class="code mt-1 text-2xl font-medium tracking-[0.2em] text-ink" :class="left === 0 && 'line-through text-ink-4'">{{ $pairingCode }}</div>
                    <div class="mt-1 text-[0.8125rem] text-ink-3" x-show="left > 0">
                        Ważny jeszcze <span class="tabular-nums" x-text="Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0')"></span> · jednorazowy
                    </div>
                    <div class="mt-1 text-[0.8125rem] text-danger" x-show="left === 0" x-cloak>Kod wygasł.</div>
                </div>
                <div class="w-full rounded-lg bg-surface-2 p-2.5 text-left text-[0.8125rem]">
                    <span class="text-ink-4">Adres:</span> <span class="code break-all text-ink-2">{{ $pairingUrl }}</span>
                </div>
                <p class="flex items-center gap-2 text-[0.8125rem] text-ink-3" x-show="left > 0"><x-lucide-loader-circle class="size-4 animate-spin" aria-hidden="true" /> Czekam na telefon… okno zamknie się samo.</p>
                <x-ui.button icon="refresh-cw" wire:click="startPairing" x-show="left === 0" x-cloak>Wygeneruj nowy kod</x-ui.button>
            </div>
        @endif
    </x-ui.modal>
</div>
