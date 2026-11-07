@php use App\Support\Num; @endphp

<div>
    <x-ui.page-header :title="'Dzień dobry, '.str(auth()->user()->name)->before(' ')" :description="now()->translatedFormat('l, j F Y')">
        <x-slot:actions>
            @can('stock.view')<x-ui.button :href="route('stock.index')" icon="boxes">Stany</x-ui.button>@endcan
            @can('documents.create')<x-new-document-menu />@endcan
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Szybkie akcje magazyniera: najczęstsze czynności jednym kliknięciem --}}
    @can('documents.create')
        <section class="mb-3 grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Szybkie akcje">
            @foreach ([
                ['PZ', 'Przyjmij towar', 'Dostawa od dostawcy', 'arrow-down-left', 'in'],
                ['WZ', 'Wydaj towar', 'Wydanie do odbiorcy', 'arrow-up-right', 'out'],
                ['MM', 'Przesuń towar', 'Między magazynami', 'arrow-left-right', 'transfer'],
            ] as [$type, $title, $hint, $icon, $tone])
                <a href="{{ route('documents.create', $type) }}" wire:navigate class="card group flex items-center gap-3 p-4 transition-colors hover:border-line-strong">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-{{ $tone }}-soft text-{{ $tone }}"><x-dynamic-component :component="'lucide-'.$icon" class="size-5" aria-hidden="true" /></span>
                    <span class="min-w-0">
                        <span class="block font-semibold text-ink">{{ $title }}</span>
                        <span class="block text-[0.8125rem] text-ink-3">{{ $hint }} · <span class="font-mono">{{ $type }}</span></span>
                    </span>
                </a>
            @endforeach
            <a href="{{ route('layout.plan') }}" wire:navigate class="card group flex items-center gap-3 p-4 transition-colors hover:border-line-strong">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-surface-3 text-ink-2"><x-lucide-map class="size-5" aria-hidden="true" /></span>
                <span class="min-w-0">
                    <span class="block font-semibold text-ink">Plan magazynu</span>
                    <span class="block text-[0.8125rem] text-ink-3">Regały i półki</span>
                </span>
            </a>
        </section>

        @if ($myDrafts->isNotEmpty())
            <section class="card mb-3" aria-labelledby="drafts-title">
                <div class="flex items-center justify-between border-b border-line px-4 py-3">
                    <h2 id="drafts-title" class="font-semibold">Do dokończenia</h2>
                    <span class="text-[0.8125rem] text-ink-3">{{ Num::plural($myDrafts->count(), 'Twój szkic', 'Twoje szkice', 'Twoich szkiców') }}</span>
                </div>
                <ul class="divide-y divide-line">
                    @foreach ($myDrafts as $draft)
                        <li class="relative flex items-center gap-3 px-4 py-2.5">
                            <x-ui.doc-type :type="$draft->type" />
                            <a href="{{ route('documents.show', $draft) }}" wire:navigate class="code min-w-0 truncate font-medium text-ink after:absolute after:inset-0">{{ $draft->displayNumber() }}</a>
                            <span class="hidden truncate text-[0.8125rem] text-ink-3 sm:inline">{{ $draft->contractor?->name ?? $draft->warehouse->code }}</span>
                            <span class="ml-auto shrink-0 text-[0.8125rem] text-ink-3">{{ Num::plural($draft->items_count, 'pozycja', 'pozycje', 'pozycji') }} · {{ $draft->updated_at->diffForHumans() }}</span>
                            <x-lucide-chevron-right class="size-4 shrink-0 text-ink-4" aria-hidden="true" />
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endcan

    {{-- KPI --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-ui.stat label="Wartość magazynu" :value="Num::money($stockValue)" hint="wg cen średnich ważonych" icon="banknote" />
        <x-ui.stat label="Aktywne towary" :value="Num::qty($productCount)" icon="package" :href="route('products.index')" />
        <x-ui.stat label="Poniżej minimum" :value="Num::qty($lowStock->count())" :hint="$lowStock->isEmpty() ? 'wszystkie stany w normie' : 'wymaga zamówienia'"
                   icon="triangle-alert" :tone="$lowStock->isNotEmpty() ? 'warn' : null" :href="route('stock.index', ['low' => 1])" />
        <x-ui.stat label="Szkice w toku" :value="Num::qty($draftCount)" :hint="'zatwierdzone dziś: '.$todayCount" icon="file-pen" :href="route('documents.index', ['status' => 'draft'])" />
    </div>

    <div class="mt-3 grid gap-3 xl:grid-cols-3">
        {{-- Przepływ wartości: przyjęcia nad osią, wydania pod osią (jedna oś, ta sama skala). --}}
        <section class="card p-4 xl:col-span-2" aria-labelledby="flow-title">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id="flow-title" class="font-semibold text-ink">Przyjęcia i wydania</h2>
                    <p class="text-[0.8125rem] text-ink-3">Wartość netto, ostatnie {{ \App\Livewire\Dashboard::CHART_DAYS }} dni · bez przesunięć MM</p>
                </div>
                <dl class="flex gap-5 text-[0.8125rem]">
                    <div>
                        <dt class="flex items-center gap-1.5 text-ink-3"><span class="size-2.5 rounded-[3px] bg-[var(--chart-in)]"></span>Przyjęcia</dt>
                        <dd class="num text-left font-semibold text-ink">{{ Num::money($chart['totalIn']) }}</dd>
                    </div>
                    <div>
                        <dt class="flex items-center gap-1.5 text-ink-3"><span class="size-2.5 rounded-[3px] bg-[var(--chart-out)]"></span>Wydania</dt>
                        <dd class="num text-left font-semibold text-ink">{{ Num::money($chart['totalOut']) }}</dd>
                    </div>
                </dl>
            </div>

            @php
                $axis = $chart['max'] >= 1000
                    ? number_format($chart['max'] / 1000, 1, ',', '').' tys.'
                    : number_format($chart['max'], 0, ',', ' ');
            @endphp
            <div class="flex gap-2" x-data="{ active: null }" x-on:mouseleave="active = null">
                {{-- Oś Y: jedna skala dla obu kierunków --}}
                <div class="num flex h-56 w-12 shrink-0 flex-col justify-between text-right text-[0.6875rem] leading-none text-ink-4" aria-hidden="true">
                    <span>{{ $axis }}</span><span>0</span><span>{{ $axis }}</span>
                </div>
                <div class="relative min-w-0 flex-1">
                <div class="flex h-56 items-stretch gap-[2px] border-y border-dashed border-line" role="img" aria-label="Wykres słupkowy przyjęć (nad osią) i wydań (pod osią) w ostatnich dniach; dane w tabeli poniżej">
                    @foreach ($chart['days'] as $i => $day)
                        @php
                            $inPct = $day['in'] / $chart['max'] * 100;
                            $outPct = $day['out'] / $chart['max'] * 100;
                        @endphp
                        <div class="group relative flex flex-1 cursor-default flex-col" x-on:mouseenter="active = {{ $i }}" x-on:focus="active = {{ $i }}" tabindex="-1">
                            <div class="flex flex-1 items-end justify-center border-b border-line-strong pb-px">
                                <div class="w-full max-w-7 rounded-t-[4px] bg-[var(--chart-in)] transition-opacity duration-150"
                                     :class="active !== null && active !== {{ $i }} && 'opacity-40'"
                                     style="height: {{ $day['in'] > 0 ? max($inPct, 1.5) : 0 }}%"></div>
                            </div>
                            <div class="flex flex-1 items-start justify-center pt-px">
                                <div class="w-full max-w-7 rounded-b-[4px] bg-[var(--chart-out)] transition-opacity duration-150"
                                     :class="active !== null && active !== {{ $i }} && 'opacity-40'"
                                     style="height: {{ $day['out'] > 0 ? max($outPct, 1.5) : 0 }}%"></div>
                            </div>

                            {{-- Podpowiedź dla kolumny --}}
                            <div x-show="active === {{ $i }}" x-cloak
                                 @class(['pointer-events-none absolute top-2 z-10 w-44 rounded-lg border border-line bg-surface p-2.5 text-[0.8125rem] shadow-pop', 'left-full ml-2' => $i < 9, 'right-full mr-2' => $i >= 9])>
                                <div class="mb-1 font-medium text-ink">{{ $day['date']->translatedFormat('D, j M') }}</div>
                                <div class="flex justify-between gap-2 text-ink-3"><span class="flex items-center gap-1.5"><span class="size-2 rounded-[2px] bg-[var(--chart-in)]"></span>Przyjęcia</span><span class="num text-ink">{{ Num::money($day['in']) }}</span></div>
                                <div class="flex justify-between gap-2 text-ink-3"><span class="flex items-center gap-1.5"><span class="size-2 rounded-[2px] bg-[var(--chart-out)]"></span>Wydania</span><span class="num text-ink">{{ Num::money($day['out']) }}</span></div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-2 flex gap-[2px] text-center text-[0.6875rem] text-ink-4">
                    @foreach ($chart['days'] as $i => $day)
                        <div class="num flex-1 text-center">{{ $i % 2 === 1 || $loop->last ? $day['date']->format('d.m') : '' }}</div>
                    @endforeach
                </div>
                </div>
            </div>

            <details class="mt-3 text-[0.8125rem]">
                <summary class="cursor-pointer text-ink-3 hover:text-ink">Pokaż dane w tabeli</summary>
                <div class="mt-2 max-h-64 overflow-auto rounded-lg border border-line">
                    <table class="table">
                        <thead><tr><th>Dzień</th><th class="num">Przyjęcia</th><th class="num">Wydania</th></tr></thead>
                        <tbody>
                            @foreach ($chart['days'] as $day)
                                <tr><td>{{ $day['date']->format('d.m.Y') }}</td><td class="num">{{ Num::money($day['in']) }}</td><td class="num">{{ Num::money($day['out']) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        </section>

        {{-- Skany na żywo --}}
        <section class="card flex flex-col" aria-labelledby="scans-title">
            <div class="flex items-center justify-between border-b border-line px-4 py-3">
                <h2 id="scans-title" class="flex items-center gap-2 font-semibold text-ink">
                    <span class="live-dot" x-show="$store.live.state === 'connected'"></span>
                    Ostatnie skany
                </h2>
                @can('scanner.use')
                    <a href="{{ route('scanner.devices') }}" wire:navigate class="text-[0.8125rem] font-medium text-ink-3 hover:text-ink">Skanery</a>
                @endcan
            </div>
            @if ($recentScans->isEmpty())
                @can('scanner.use')
                    <x-ui.empty icon="scan-barcode" title="Brak skanów" description="Sparuj telefon i zeskanuj etykietę QR towaru – skan pojawi się tu natychmiast.">
                        <x-ui.button :href="route('scanner.devices')" size="sm" icon="smartphone">Sparuj telefon</x-ui.button>
                    </x-ui.empty>
                @else
                    <x-ui.empty icon="scan-barcode" title="Brak skanów" description="Skany z telefonów magazynierów pojawią się tu na żywo." />
                @endcan
            @else
                <ul class="divide-y divide-line">
                    @foreach ($recentScans as $scan)
                        <li wire:key="scan-{{ $scan->id }}" @class(['flex items-start gap-3 px-4 py-2.5', 'enter-up' => $loop->first])>
                            <span @class([
                                'mt-0.5 grid size-7 shrink-0 place-items-center rounded-lg',
                                'bg-accent-soft text-accent' => $scan->resultTone() === 'success',
                                'bg-surface-3 text-ink-3' => $scan->resultTone() === 'neutral',
                                'bg-warn-soft text-warn' => $scan->resultTone() === 'warn',
                                'bg-danger-soft text-danger' => $scan->resultTone() === 'danger',
                            ])>
                                <x-dynamic-component :component="$scan->source === 'keyboard' ? 'lucide-keyboard' : 'lucide-smartphone'" class="size-3.5" aria-hidden="true" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[0.8125rem] font-medium text-ink">{{ $scan->product?->name ?? 'Nieznany kod' }}</p>
                                <p class="truncate text-xs text-ink-4">
                                    <span class="code">{{ $scan->code }}</span>
                                    @if ($scan->document) · {{ $scan->document->displayNumber() }} @endif
                                </p>
                            </div>
                            <time class="shrink-0 text-xs text-ink-4 num" datetime="{{ $scan->created_at->toIso8601String() }}">{{ $scan->created_at->format('H:i:s') }}</time>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    <div class="mt-3 grid gap-3 xl:grid-cols-3">
        <section class="card overflow-hidden xl:col-span-2" aria-labelledby="docs-title">
            <div class="flex items-center justify-between border-b border-line px-4 py-3">
                <h2 id="docs-title" class="font-semibold text-ink">Ostatnie dokumenty</h2>
                <a href="{{ route('documents.index') }}" wire:navigate class="text-[0.8125rem] font-medium text-ink-3 hover:text-ink">Wszystkie</a>
            </div>
            @if ($recentDocuments->isEmpty())
                <x-ui.empty icon="file-text" title="Brak dokumentów" description="Zacznij od przyjęcia towaru dokumentem PZ." />
            @else
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr><th class="w-16">Typ</th><th>Numer</th><th class="hidden whitespace-nowrap sm:table-cell">Data</th><th class="hidden md:table-cell">Kontrahent</th><th>Magazyn</th><th>Status</th><th class="num">Wartość</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($recentDocuments as $doc)
                                <tr class="relative" wire:key="doc-{{ $doc->id }}">
                                    <td><x-ui.doc-type :type="$doc->type" /></td>
                                    <td><a href="{{ route('documents.show', $doc) }}" wire:navigate class="row-link code">{{ $doc->displayNumber() }}</a></td>
                                    <td class="num hidden text-left whitespace-nowrap text-ink-3 sm:table-cell">{{ $doc->dateTimeLabel() }}</td>
                                    <td class="hidden max-w-56 truncate text-ink-2 md:table-cell">{{ $doc->contractor?->name ?? '—' }}</td>
                                    <td><x-ui.warehouse :warehouse="$doc->warehouse" /></td>
                                    <td><x-ui.status :status="$doc->status" /></td>
                                    <td class="num">{{ $doc->isDraft() ? '—' : Num::money($doc->total_value) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="card overflow-hidden" aria-labelledby="low-title">
            <div class="flex items-center justify-between border-b border-line px-4 py-3">
                <h2 id="low-title" class="font-semibold text-ink">Poniżej stanu minimalnego</h2>
                @if ($lowStock->isNotEmpty())<x-ui.badge tone="warn">{{ $lowStock->count() }}</x-ui.badge>@endif
            </div>
            @if ($lowStock->isEmpty())
                <x-ui.empty icon="circle-check" title="Stany w normie" description="Żaden towar nie spadł poniżej minimum." />
            @else
                <ul class="divide-y divide-line">
                    @foreach ($lowStock->take(7) as $product)
                        @php $ratio = min(1, (float) $product->total_quantity / max((float) $product->min_stock, 0.001)); @endphp
                        <li class="relative px-4 py-2.5">
                            <div class="flex items-baseline justify-between gap-3">
                                <a href="{{ route('products.show', $product) }}" wire:navigate class="truncate text-[0.8125rem] font-medium text-ink after:absolute after:inset-0">{{ $product->name }}</a>
                                <span class="num shrink-0 text-xs text-ink-3">{{ Num::qty($product->total_quantity ?? 0) }} / {{ Num::qty($product->min_stock, $product->unit) }}</span>
                            </div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-surface-3" role="presentation">
                                <div class="h-full rounded-full bg-warn" style="width: {{ max(3, $ratio * 100) }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</div>
