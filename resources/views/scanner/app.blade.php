<!DOCTYPE html>
<html lang="pl" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0B0F14">
    <meta name="color-scheme" content="dark">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Skaner">
    <title>Skaner · Regał WMS</title>
    <link rel="manifest" href="{{ route('scanner.manifest') }}">
    <link rel="icon" href="{{ asset('icons/favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('icons/scanner-192.png') }}">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/scanner.js'])
    <style>
        /* Dolne 6rem zajmuje panel sterowania – dzięki temu środek obrazu pokrywa się ze środkiem celownika (pb-24).
           !important: html5-qrcode przy starcie ustawia kontenerowi position: relative, a wtedy jego wysokość zależała
           od proporcji strumienia i po powrocie do przeglądarki obraz zajmował tylko część ekranu. */
        #reader { position: absolute !important; inset: 0 0 6rem 0; overflow: hidden; }
        #reader video { position: absolute; inset: 0; width: 100% !important; height: 100% !important; object-fit: cover; }
        #reader > div, #reader img { display: none !important; }
        .safe-top { padding-top: max(env(safe-area-inset-top), 0.75rem); }
        .safe-bottom { padding-bottom: max(env(safe-area-inset-bottom), 0.75rem); }
        .reticle { width: min(72vw, 20rem); aspect-ratio: 1.45; }
        .reticle i { position: absolute; width: 1.75rem; height: 1.75rem; border-color: #fff; border-style: solid; border-width: 0; }
        .reticle i:nth-child(1) { top: 0; left: 0; border-top-width: 3px; border-left-width: 3px; border-top-left-radius: 0.9rem; }
        .reticle i:nth-child(2) { top: 0; right: 0; border-top-width: 3px; border-right-width: 3px; border-top-right-radius: 0.9rem; }
        .reticle i:nth-child(3) { bottom: 0; left: 0; border-bottom-width: 3px; border-left-width: 3px; border-bottom-left-radius: 0.9rem; }
        .reticle i:nth-child(4) { bottom: 0; right: 0; border-bottom-width: 3px; border-right-width: 3px; border-bottom-right-radius: 0.9rem; }
        .reticle.armed i { border-color: #34D399; }
        .sheet { transition: transform 320ms var(--ease-drawer); }
        .tap { -webkit-tap-highlight-color: transparent; touch-action: manipulation; }
    </style>
</head>
<body class="tap h-dvh overflow-hidden bg-black text-ink" x-data="scannerApp()" x-cloak>

    {{-- Warstwa kamery (wspólna dla skanowania i parowania) --}}
    <div class="fixed inset-0 bg-black" x-show="screen === 'scan' || camera !== 'idle'">
        <div id="reader" wire:ignore></div>
        <div class="pointer-events-none absolute inset-x-0 top-0 h-40 bg-gradient-to-b from-black/70 to-transparent"></div>
        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-72 bg-gradient-to-t from-black/85 to-transparent"></div>

        {{-- Celownik --}}
        <div class="pointer-events-none absolute inset-0 grid place-items-center pb-24">
            <div class="reticle relative transition-transform duration-150 ease-out" :class="{ 'scale-[0.97]': flash, 'armed': armed }">
                <i></i><i></i><i></i><i></i>
                <div class="absolute inset-x-6 top-1/2 h-px -translate-y-1/2 transition-colors duration-150" :class="armed ? 'bg-[#34D399]' : 'bg-white/30'" x-show="camera === 'running'"></div>
                <p class="absolute inset-x-0 top-full mt-3 text-center text-[0.8125rem] text-white/85 drop-shadow" x-show="screen === 'scan' && camera === 'running'"
                   x-text="armed ? 'Szukam kodu w ramce…' : (missed ? 'Nie odczytano kodu. Wyceluj w ramkę i spróbuj ponownie.' : 'Wyceluj kod w ramkę i naciśnij „Skanuj”')"></p>
            </div>
        </div>

        {{-- Błysk potwierdzenia --}}
        <div class="pointer-events-none absolute inset-0 transition-opacity duration-200 ease-out"
             :class="flash === 'success' ? 'opacity-100 shadow-[inset_0_0_0_6px_#34D399]' : (flash === 'error' ? 'opacity-100 shadow-[inset_0_0_0_6px_#F87171]' : 'opacity-0')"></div>

        {{-- Stany kamery --}}
        <div class="absolute inset-0 grid place-items-center px-8 pb-24 text-center" x-show="camera !== 'running'">
            <div class="max-w-xs">
                <template x-if="camera === 'starting' || camera === 'idle'">
                    <p class="text-ink-3">Uruchamiam kamerę…</p>
                </template>
                <template x-if="camera === 'denied' || camera === 'error' || camera === 'unsupported'">
                    <div>
                        <p class="text-pretty text-ink-2" x-text="cameraMessage"></p>
                        <button type="button" class="btn btn-secondary mt-4 h-11" x-show="camera !== 'unsupported'" x-on:click="startCamera(screen === 'scan' ? (t) => onDecoded(t) : () => {})">Spróbuj ponownie</button>
                        <button type="button" class="btn btn-primary mt-4 h-11" x-show="camera === 'unsupported' && screen === 'scan'" x-on:click="sheet = 'manual'">Wpisz kod ręcznie</button>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- ═══════════════ PAROWANIE ═══════════════ --}}
    <section class="relative flex h-dvh flex-col bg-canvas px-5 safe-top safe-bottom" x-show="screen === 'pair' && camera === 'idle'" aria-labelledby="pair-title">
        <div class="flex flex-1 flex-col justify-center">
            <div class="mb-8 flex items-center gap-3">
                <x-logo class="size-11 text-ink [--logo-ink:var(--canvas)]" />
                <div>
                    <div class="text-lg font-semibold tracking-tight">Regał · Skaner</div>
                    <div class="text-[0.8125rem] text-ink-3">Telefon jako bezprzewodowy czytnik kodów</div>
                </div>
            </div>

            <h1 id="pair-title" class="text-xl font-semibold tracking-tight">Sparuj z panelem</h1>
            <p class="mt-1 text-pretty text-ink-3">W panelu wejdź w <span class="text-ink-2">Skanery mobilne → Sparuj telefon</span> i zeskanuj wyświetlony kod QR albo przepisz kod.</p>

            <form class="mt-6 space-y-4" x-on:submit.prevent="pair()">
                <div>
                    <label for="pair-code" class="label">Kod parowania</label>
                    <input id="pair-code" x-model="pairCode" class="control h-12 text-center font-mono text-xl tracking-[0.25em] uppercase" placeholder="XXXX-XXXX"
                           autocomplete="one-time-code" autocapitalize="characters" spellcheck="false" maxlength="9" enterkeyhint="go">
                </div>
                <div>
                    <label for="device-name" class="label">Nazwa urządzenia</label>
                    <input id="device-name" x-ref="deviceName" x-model="deviceName" class="control h-12" maxlength="60" autocomplete="off" enterkeyhint="go">
                </div>
                <p class="rounded-lg bg-danger-soft p-3 text-[0.8125rem] text-danger" x-show="pairError" x-text="pairError" role="alert"></p>
                <button type="submit" class="btn btn-primary h-12 w-full text-base" :disabled="pairing">
                    <span x-show="!pairing">Połącz</span>
                    <span x-show="pairing">Łączenie…</span>
                </button>
                <button type="button" class="btn btn-secondary h-12 w-full text-base" x-on:click="scanPairingCode()" x-show="secure">
                    <x-lucide-qr-code aria-hidden="true" /> Zeskanuj kod z ekranu
                </button>
            </form>
        </div>
        <p class="text-center text-xs text-ink-4" x-show="!secure">Połączenie bez HTTPS – kamera niedostępna, kody wpiszesz ręcznie.</p>
    </section>

    {{-- Anuluj skanowanie kodu parowania --}}
    <div class="fixed inset-x-0 bottom-0 z-20 px-5 safe-bottom" x-show="screen === 'pair' && camera !== 'idle'">
        <p class="mb-3 text-center text-ink-2">Nakieruj na kod QR w panelu</p>
        <button type="button" class="btn btn-secondary h-12 w-full" x-on:click="stopCamera()">Anuluj</button>
    </div>

    {{-- ═══════════════ SKANOWANIE ═══════════════ --}}
    <template x-if="screen === 'scan'">
        <div>
            {{-- Pasek górny --}}
            <header class="fixed inset-x-0 top-0 z-20 flex items-center gap-2 px-3 safe-top">
                {{-- Tryb „Rozmieść”: pokazuje półkę docelową; pozostałe tryby: dokument docelowy --}}
                <template x-if="mode === 'putaway'">
                    <div class="flex h-11 min-w-0 flex-1 items-center gap-2.5 rounded-xl bg-black/45 px-3 backdrop-blur-md">
                        <x-lucide-map-pin class="size-4 shrink-0" ::class="location ? 'text-[#34D399]' : 'text-white/50'" aria-hidden="true" />
                        <span class="min-w-0 flex-1 leading-tight">
                            <span class="block text-[0.6875rem] text-white/60" x-text="location ? 'Odkładasz na półkę' : 'Krok 1'"></span>
                            <span class="block truncate text-sm font-medium text-white" x-text="location ? location.code + ' · wolne ' + location.free + '%' : 'Zeskanuj etykietę półki'"></span>
                        </span>
                        <button type="button" class="text-xs text-white/70 underline" x-show="location" x-on:click="clearLocation()">zmień</button>
                    </div>
                </template>
                <template x-if="mode !== 'putaway'">
                    <button type="button" x-on:click="openTargets()"
                            class="flex h-11 min-w-0 flex-1 items-center gap-2.5 rounded-xl bg-black/45 px-3 text-left backdrop-blur-md transition-transform duration-150 active:scale-[0.98]"
                            aria-label="Wybierz dokument docelowy">
                        <span class="size-2 shrink-0 rounded-full" :class="target && mode === 'add' ? 'bg-[#34D399]' : 'bg-white/40'"></span>
                        <span class="min-w-0 flex-1 leading-tight">
                            <span class="block text-[0.6875rem] text-white/60" x-text="mode === 'lookup' ? 'Tryb sprawdzania' : (target ? target.type_label : 'Brak dokumentu')"></span>
                            <span class="block truncate text-sm font-medium text-white" x-text="mode === 'lookup' ? 'Skany nie zmieniają dokumentów' : (target ? target.number + ' · ' + target.items_count + ' poz.' : 'Dotknij, aby wybrać dokument')"></span>
                        </span>
                        <x-lucide-chevron-down class="size-4 shrink-0 text-white/60" aria-hidden="true" />
                    </button>
                </template>

                <span class="flex h-11 items-center gap-1.5 rounded-xl bg-black/45 px-3 text-xs text-white backdrop-blur-md" x-show="!online || queue.length" x-cloak>
                    <x-lucide-wifi-off class="size-4" aria-hidden="true" x-show="!online" />
                    <span x-text="queue.length ? queue.length + ' w kolejce' : 'offline'"></span>
                </span>

                <button type="button" class="grid size-11 shrink-0 place-items-center rounded-xl bg-black/45 text-white backdrop-blur-md active:scale-95" x-on:click="sheet = 'menu'" aria-label="Menu">
                    <x-lucide-ellipsis class="size-5" aria-hidden="true" />
                </button>
            </header>

            {{-- Dół: wynik + sterowanie --}}
            <div class="fixed inset-x-0 bottom-0 z-20 space-y-3 px-3 safe-bottom">
                {{-- Karta wyniku --}}
                <div x-show="result" x-transition:enter="transition duration-200 ease-[cubic-bezier(0.23,1,0.32,1)]" x-transition:enter-start="opacity-0 translate-y-2"
                     x-on:click="if (! $event.target.closest('button, a')) dismissResult()" title="Dotknij, aby zamknąć"
                     class="max-h-[40dvh] overflow-y-auto rounded-2xl border bg-surface/95 p-3.5 backdrop-blur-md"
                     :class="{ 'border-[#34D399]/50': resultTone === 'ok', 'border-line': resultTone === 'info', 'border-[#F87171]/60': resultTone === 'bad', 'border-[#FBBF24]/50': resultTone === 'warn' }"
                     aria-live="polite">
                    <template x-if="result">
                        <div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="badge"
                                      :class="{ 'bg-accent-soft text-accent': resultTone === 'ok', 'bg-surface-3 text-ink-2': resultTone === 'info', 'bg-danger-soft text-danger': resultTone === 'bad', 'bg-warn-soft text-warn': resultTone === 'warn' }"
                                      x-text="resultLabel"></span>
                                <span class="code truncate text-xs text-ink-4" x-text="result.code"></span>
                            </div>

                            {{-- Zeskanowana półka: co na niej leży --}}
                            <template x-if="result.result === 'location' && result.location">
                                <div>
                                    <p class="mt-2 text-base leading-snug font-semibold text-ink" x-text="result.location.code"></p>
                                    <p class="text-[0.8125rem] text-ink-3" x-text="result.location.warehouse + ' · ' + result.location.label + ' · wolne ' + result.location.free + '%'"></p>
                                    <ul class="mt-2 space-y-1">
                                        <template x-for="item in result.location.items" :key="item.sku">
                                            <li class="flex items-center justify-between gap-2 text-[0.8125rem]">
                                                <span class="min-w-0 truncate text-ink" x-text="item.name"></span>
                                                <span class="shrink-0 text-ink-3 tabular-nums" x-text="(item.quantity ? item.quantity + ' · ' : '') + item.fill + '%'"></span>
                                            </li>
                                        </template>
                                        <li class="text-[0.8125rem] text-ink-3" x-show="! result.location.items.length">Półka jest pusta.</li>
                                    </ul>
                                    <p class="mt-2 text-xs text-ink-4" x-show="mode === 'putaway'">Teraz skanuj towary, które odkładasz na tę półkę.</p>
                                </div>
                            </template>

                            <template x-if="result.result !== 'location'">
                                <div>
                                    <p class="mt-2 text-base leading-snug font-semibold text-ink" x-text="result.product ? result.product.name : result.message"></p>
                                    <p class="mt-0.5 text-[0.8125rem] text-ink-3" x-show="result.product && result.message !== result.product.name" x-text="result.message"></p>
                                    <div class="mt-2.5 flex flex-wrap gap-1.5" x-show="result.product && result.product.stocks.length">
                                        <template x-for="s in (result.product ? result.product.stocks : [])" :key="s.warehouse">
                                            <span class="wh-badge" :class="'wh-' + s.color"><span class="wh-dot"></span><span class="wh-code" x-text="s.warehouse"></span><span class="tabular-nums text-ink-2" x-text="s.formatted"></span></span>
                                        </template>
                                    </div>
                                    {{-- Gdzie leży towar --}}
                                    <div class="mt-1.5 flex flex-wrap gap-1.5" x-show="result.product && result.product.locations && result.product.locations.length">
                                        <template x-for="l in (result.product ? result.product.locations : [])" :key="l.warehouse + l.code">
                                            <span class="wh-badge" :class="'wh-' + l.color">
                                                <x-lucide-map-pin class="size-3 text-[var(--wh)]" aria-hidden="true" />
                                                <span class="code" x-text="l.code"></span>
                                            </span>
                                        </template>
                                    </div>
                                    {{-- Nieznany kod EAN: nowy towar w dostawie --}}
                                    <div class="mt-3" x-show="result.result === 'not_found' && isGtin(result.code)">
                                        <button type="button" class="btn btn-primary h-11 w-full" x-show="canManageProducts" x-on:click="openNewProduct(result.code)">
                                            <x-lucide-package-plus class="size-4" aria-hidden="true" />Dodaj nowy towar z tym kodem
                                        </button>
                                        <p class="text-[0.8125rem] text-ink-3" x-show="! canManageProducts">Nowy towar do kartoteki dodaje kierownik magazynu.</p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- Sterowanie --}}
                <div class="rounded-2xl bg-black/55 p-2 backdrop-blur-md">
                    <div class="grid grid-cols-3 gap-1 rounded-xl bg-white/10 p-1" role="radiogroup" aria-label="Tryb skanowania">
                        <button type="button" role="radio" :aria-checked="mode === 'add'" x-on:click="setMode('add')"
                                class="h-10 rounded-lg text-sm font-medium transition-colors duration-150" :class="mode === 'add' ? 'bg-white text-black' : 'text-white/80'">Dodaj</button>
                        <button type="button" role="radio" :aria-checked="mode === 'lookup'" x-on:click="setMode('lookup')"
                                class="h-10 rounded-lg text-sm font-medium transition-colors duration-150" :class="mode === 'lookup' ? 'bg-white text-black' : 'text-white/80'">Sprawdź</button>
                        <button type="button" role="radio" :aria-checked="mode === 'putaway'" x-on:click="setMode('putaway')"
                                class="h-10 rounded-lg text-sm font-medium transition-colors duration-150" :class="mode === 'putaway' ? 'bg-white text-black' : 'text-white/80'">Rozmieść</button>
                    </div>

                    {{-- Rozmieść: ile miejsca zajmie towar na półce --}}
                    <div class="mt-2 flex items-center gap-1" x-show="mode === 'putaway'" role="radiogroup" aria-label="Ile miejsca zajmie towar na polu półki">
                        <span class="mr-1 text-xs text-white/60">Zajmie</span>
                        <template x-for="f in [10, 25, 50, 75, 100]" :key="f">
                            <button type="button" role="radio" :aria-checked="fill === f" x-on:click="setFill(f)"
                                    class="h-9 flex-1 rounded-lg text-sm font-medium tabular-nums transition-colors duration-150"
                                    :class="fill === f ? 'bg-white text-black' : 'bg-white/10 text-white/80'" x-text="f + '%'"></button>
                        </template>
                    </div>

                    <div class="mt-2 flex items-center gap-2">
                        <div class="flex h-12 flex-1 items-center rounded-xl bg-white/10" x-show="mode !== 'lookup'">
                            <button type="button" class="grid h-12 w-12 place-items-center text-white active:scale-90 disabled:opacity-40" x-on:click="stepQuantity(-1)" :disabled="quantity <= 1" aria-label="Zmniejsz ilość"><x-lucide-minus class="size-5" aria-hidden="true" /></button>
                            <label class="sr-only" for="qty">Ilość na skan</label>
                            <input id="qty" type="number" inputmode="decimal" min="1" step="any" x-model.number="quantity" class="h-12 w-full min-w-0 bg-transparent text-center text-lg font-semibold text-white tabular-nums outline-none">
                            <button type="button" class="grid h-12 w-12 place-items-center text-white active:scale-90" x-on:click="stepQuantity(1)" aria-label="Zwiększ ilość"><x-lucide-plus class="size-5" aria-hidden="true" /></button>
                        </div>
                        <div class="flex-1 px-2 text-[0.8125rem] text-white/70" x-show="mode === 'lookup'">Skan towaru pokaże stan i półki, skan etykiety półki – jej zawartość.</div>

                        <button type="button" class="grid size-12 shrink-0 place-items-center rounded-xl bg-white/10 text-white active:scale-95" x-on:click="sheet = 'manual'; $nextTick(() => $refs.manual.focus())" aria-label="Wpisz kod ręcznie">
                            <x-lucide-keyboard class="size-5" aria-hidden="true" />
                        </button>
                        <button type="button" class="grid size-12 shrink-0 place-items-center rounded-xl text-white active:scale-95" x-show="torchSupported" x-on:click="toggleTorch()"
                                :class="torchOn ? 'bg-white text-black' : 'bg-white/10'" :aria-pressed="torchOn" aria-label="Latarka">
                            <x-lucide-flashlight class="size-5" aria-hidden="true" />
                        </button>
                    </div>

                    {{-- Spust: kod z ramki jest przyjmowany dopiero po naciśnięciu --}}
                    <button type="button" class="mt-2 flex h-14 w-full items-center justify-center gap-2 rounded-xl text-base font-semibold transition-colors duration-150 active:scale-[0.98] disabled:opacity-40"
                            :class="armed ? 'bg-[#34D399] text-black' : 'bg-white text-black'" x-on:click="trigger()" :disabled="camera !== 'running'">
                        <x-lucide-scan-line class="size-5" aria-hidden="true" />
                        <span x-text="armed ? 'Szukam kodu…' : 'Skanuj'">Skanuj</span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{-- ═══════════════ ARKUSZE ═══════════════ --}}
    <div class="fixed inset-0 z-30 bg-black/50 transition-opacity duration-200" x-show="sheet" x-transition.opacity x-on:click="sheet = null"></div>

    <div class="sheet fixed inset-x-0 bottom-0 z-40 max-h-[80dvh] overflow-y-auto overscroll-contain rounded-t-3xl border-t border-line bg-surface safe-bottom"
         :class="sheet ? 'translate-y-0' : 'translate-y-full'" role="dialog" aria-modal="true" x-on:keydown.escape.window="sheet = null">
        <div class="sticky top-0 bg-surface pt-2.5 pb-1"><div class="mx-auto h-1 w-10 rounded-full bg-line-strong"></div></div>

        {{-- Wybór dokumentu --}}
        <div x-show="sheet === 'targets'" class="px-4 pb-2">
            <h2 class="py-2 text-base font-semibold">Gdzie trafiają skany?</h2>
            <button type="button" x-on:click="setMode('lookup'); chooseTarget(null)" class="mb-2 flex w-full items-center gap-3 rounded-xl border border-line p-3 text-left active:bg-surface-2">
                <x-lucide-search class="size-5 text-ink-3" aria-hidden="true" />
                <span><span class="block font-medium">Tylko sprawdzanie</span><span class="block text-[0.8125rem] text-ink-3">Podgląd towaru i stanów, bez zmian</span></span>
            </button>
            <p class="py-2 text-xs font-medium tracking-wide text-ink-4 uppercase">Otwarte szkice</p>
            <p class="py-4 text-center text-ink-3" x-show="loadingDocuments">Wczytuję…</p>
            <p class="py-4 text-center text-[0.8125rem] text-ink-3" x-show="!loadingDocuments && !documents.length">Brak szkiców. Utwórz dokument w panelu.</p>
            <ul class="space-y-1.5">
                <template x-for="d in documents" :key="d.id">
                    <li>
                        <button type="button" x-on:click="chooseTarget(d.id)" class="flex w-full items-center gap-3 rounded-xl border p-3 text-left active:bg-surface-2"
                                :class="target && target.id === d.id ? 'border-accent-line bg-accent-soft' : 'border-line'">
                            <span class="badge w-12 justify-center bg-surface-3 font-mono text-ink" x-text="d.type"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium" x-text="d.number"></span>
                                <span class="block text-[0.8125rem] text-ink-3" x-text="d.warehouse + ' · ' + d.items_count + ' poz. · ' + d.created_at"></span>
                            </span>
                            <x-lucide-check class="size-5 text-accent" aria-hidden="true" x-show="target && target.id === d.id" />
                        </button>
                    </li>
                </template>
            </ul>
        </div>

        {{-- Ręczne wpisanie --}}
        <form x-show="sheet === 'manual'" class="px-4 pb-2" x-on:submit.prevent="submitManual()">
            <h2 class="py-2 text-base font-semibold">Wpisz kod</h2>
            <label for="manual" class="sr-only">Kod towaru</label>
            <input id="manual" x-ref="manual" x-model="manualCode" class="control h-12 font-mono text-lg" placeholder="Indeks, EAN lub RGL:…" autocomplete="off" autocapitalize="characters" spellcheck="false" enterkeyhint="send">
            <button type="submit" class="btn btn-primary mt-3 h-12 w-full text-base">Wyślij</button>
        </form>

        {{-- Nowy towar z nieznanym kodem EAN --}}
        <form x-show="sheet === 'product'" class="space-y-3 px-4 pb-2" x-on:submit.prevent="saveNewProduct()" novalidate>
            <div class="py-2">
                <h2 class="text-base font-semibold">Nowy towar</h2>
                <p class="text-[0.8125rem] text-ink-3">EAN <span class="code" x-text="productForm.code"></span></p>
            </div>
            <div>
                <label for="product-name" class="label">Nazwa</label>
                <input id="product-name" x-ref="productName" x-model="productForm.name" class="control h-12 text-base" maxlength="255" autocomplete="off" enterkeyhint="next" required>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="product-sku" class="label">Indeks</label>
                    <input id="product-sku" x-model="productForm.sku" class="control h-12 font-mono" placeholder="automatycznie" maxlength="40" autocomplete="off" autocapitalize="characters" spellcheck="false">
                </div>
                <div>
                    <label for="product-unit" class="label">Jednostka</label>
                    <select id="product-unit" x-model="productForm.unit" class="control h-12">
                        <template x-for="u in units" :key="u.value">
                            <option :value="u.value" x-text="u.value + ' · ' + u.label"></option>
                        </template>
                    </select>
                </div>
            </div>
            <p class="rounded-lg bg-danger-soft p-2.5 text-[0.8125rem] text-ink-2" x-show="productError" x-text="productError" role="alert"></p>
            <button type="submit" class="btn btn-primary h-12 w-full text-base" :disabled="savingProduct"
                    x-text="savingProduct ? 'Zapisuję…' : (mode === 'add' && target ? 'Zapisz i dodaj do dokumentu' : (mode === 'putaway' && location ? 'Zapisz i odłóż na półkę' : 'Zapisz w kartotece'))"></button>
            <p class="text-xs text-ink-4">Cenę, kategorię i stan minimalny uzupełnisz później w kartotece w panelu.</p>
        </form>

        {{-- Menu / historia --}}
        <div x-show="sheet === 'menu'" class="px-4 pb-2">
            <div class="flex items-center gap-3 py-2">
                <x-logo class="size-9 text-ink [--logo-ink:var(--surface)]" />
                <div class="min-w-0">
                    <div class="truncate font-semibold" x-text="device ? device.name : 'Skaner'"></div>
                    <div class="truncate text-[0.8125rem] text-ink-3" x-text="device ? 'Użytkownik: ' + device.user : ''"></div>
                </div>
            </div>

            <p class="pt-3 pb-1.5 text-xs font-medium tracking-wide text-ink-4 uppercase">Ostatnie skany</p>
            <p class="py-3 text-[0.8125rem] text-ink-3" x-show="!history.length">Brak skanów.</p>
            <ul class="divide-y divide-line rounded-xl border border-line" x-show="history.length">
                <template x-for="h in history.slice(0, 12)" :key="h.id + h.at">
                    <li class="flex items-center gap-3 px-3 py-2.5">
                        <span class="size-2 shrink-0 rounded-full" :class="['added', 'placed'].includes(h.result) ? 'bg-accent' : (['found', 'location', 'queued'].includes(h.result) ? 'bg-ink-4' : (h.result === 'not_found' ? 'bg-danger' : 'bg-warn'))"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium" x-text="h.name"></span>
                            <span class="code block truncate text-xs text-ink-4" x-text="h.code"></span>
                        </span>
                        <span class="text-xs text-ink-4 tabular-nums" x-text="h.at"></span>
                    </li>
                </template>
            </ul>

            <button type="button" x-on:click="sheet = 'unpair'" class="btn btn-secondary mt-4 h-12 w-full text-danger">
                <x-lucide-unlink aria-hidden="true" /> Odłącz telefon
            </button>
        </div>

        {{-- Potwierdzenie odłączenia (zamiast natywnego confirm) --}}
        <div x-show="sheet === 'unpair'" class="px-4 pb-2" role="alertdialog" aria-labelledby="unpair-title">
            <div class="flex gap-3 py-2">
                <span class="grid size-10 shrink-0 place-items-center rounded-full bg-danger-soft text-danger"><x-lucide-unlink class="size-5" aria-hidden="true" /></span>
                <div>
                    <h2 id="unpair-title" class="text-base font-semibold">Odłączyć ten telefon?</h2>
                    <p class="mt-0.5 text-[0.8125rem] text-ink-3">Token urządzenia przestanie działać. Aby znów skanować, sparuj telefon kodem z panelu.</p>
                </div>
            </div>
            <div class="mt-3 grid grid-cols-2 gap-2">
                <button type="button" class="btn btn-secondary h-12" x-on:click="sheet = 'menu'">Anuluj</button>
                <button type="button" class="btn btn-danger h-12" x-on:click="unpair()">Odłącz</button>
            </div>
        </div>
    </div>
</body>
</html>
