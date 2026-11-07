@php use App\Support\Num; @endphp

<div>
    <x-ui.page-header title="Stany magazynowe" description="Ilości i wartość wg ceny średniej ważonej. Aktualizują się na żywo po każdym zatwierdzeniu.">
        <x-slot:actions>
            @can('stock.export')
                <x-ui.button :href="route('stock.export', ['warehouse' => $warehouse ?: null])" :navigate="false" icon="download">Eksport CSV</x-ui.button>
            @endcan
            @can('documents.create')
                <x-ui.button :href="route('documents.create', 'INW')" icon="clipboard-check">Inwentaryzacja</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_13rem_13rem_auto_auto]">
        <x-ui.search wire:model.live.debounce.300ms="search" placeholder="Nazwa, indeks lub EAN…" />
        <x-ui.select wire:model.live="warehouse" :options="$warehouseOptions" label="Magazyn" />
        <x-ui.select wire:model.live="category" :options="$categoryOptions" label="Kategoria" />
        <label class="flex h-9 items-center gap-2 rounded-lg border border-line-strong bg-surface px-3 text-[0.8125rem] whitespace-nowrap text-ink-2 hover:border-ink-4">
            <input type="checkbox" wire:model.live="low"> Poniżej minimum
        </label>
        <label class="flex h-9 items-center gap-2 rounded-lg border border-line-strong bg-surface px-3 text-[0.8125rem] whitespace-nowrap text-ink-2 hover:border-ink-4">
            <input type="checkbox" wire:model.live="zero"> Pokaż zerowe
            <span class="badge h-5 bg-surface-3 px-1.5 text-ink-3 tabular-nums" title="Aktywne towary bez stanu{{ $warehouseId ? ' w tym magazynie' : '' }}">{{ $zeroCount }}</span>
        </label>
    </div>

    <div class="card overflow-hidden">
        <div class="flex flex-wrap items-center gap-x-6 gap-y-1 border-b border-line px-4 py-3 text-[0.8125rem] text-ink-3">
            <span>Towarów: <span class="num font-medium text-ink">{{ Num::qty($products->total()) }}</span>@if ($zero && $zeroCount) <span class="text-ink-4">(w tym bez stanu: {{ $zeroCount }})</span>@endif</span>
            @if (! $zero && ! $low && $zeroCount)
                <span class="text-ink-4">Ukryto {{ Num::plural($zeroCount, 'towar', 'towary', 'towarów') }} bez stanu</span>
            @endif
            <span>Wartość{{ $warehouseId ? ' w magazynie' : '' }}: <span class="num font-semibold text-ink">{{ Num::money($totalValue) }}</span></span>
        </div>

        @if ($products->isEmpty())
            <x-ui.empty icon="boxes" title="Brak stanów"
                        :description="$low ? 'Żaden towar nie jest poniżej stanu minimalnego.' : 'Brak towarów spełniających filtry. Stany pojawiają się po zatwierdzeniu przyjęcia (PZ lub PW).'" />
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="w-28">Indeks</th>
                            <th>Towar</th>
                            @unless ($warehouseId)<th class="hidden lg:table-cell">Magazyny</th>@endunless
                            <th class="num w-32">Na stanie</th>
                            <th class="num hidden w-28 sm:table-cell">
                                <x-ui.tooltip focusable text="Założone minimum dla produktu na stanie magazynowym (suma wszystkich magazynów). Spadek poniżej = alert na pulpicie i webhook stock.low." position="bottom">
                                    Minimum <x-lucide-info class="size-3.5 text-ink-4" aria-hidden="true" />
                                </x-ui.tooltip>
                            </th>
                            <th class="num hidden w-28 md:table-cell">Cena śr.</th>
                            <th class="num w-32">Wartość</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($products as $p)
                            @php
                                $isLow = $p->min_stock > 0 && bccomp((string) $p->total_on_hand, (string) $p->min_stock, 3) < 0;
                                $isZero = bccomp((string) $p->on_hand, '0', 3) === 0;
                            @endphp
                            <tr @class(['relative', 'bg-surface-2/60' => $isZero]) wire:key="stock-{{ $p->id }}">
                                <td class="code whitespace-nowrap text-ink-3">{{ $p->sku }}</td>
                                <td class="min-w-56">
                                    <a href="{{ route('products.show', $p) }}" wire:navigate class="row-link">{{ $p->name }}</a>
                                    @if ($isZero)<span class="badge ml-1.5 h-5 bg-surface-3 text-ink-3">brak na stanie</span>@endif
                                    <div class="text-xs text-ink-4">{{ $p->category?->name ?? 'Bez kategorii' }}</div>
                                </td>
                                @unless ($warehouseId)
                                    <td class="hidden lg:table-cell">
                                        <div class="flex flex-wrap gap-1">
                                            @forelse ($p->stocks->sortBy('warehouse.code') as $s)
                                                <x-ui.warehouse :warehouse="$s->warehouse" :qty="Num::qty($s->quantity)" />
                                            @empty
                                                <span class="text-xs text-ink-4">—</span>
                                            @endforelse
                                        </div>
                                    </td>
                                @endunless
                                <td @class(['num font-semibold', 'text-warn' => $isLow, 'text-ink-4 font-normal' => $isZero && ! $isLow])>
                                    @if ($isLow)<x-lucide-triangle-alert class="mr-1 inline size-3.5 -translate-y-px" aria-label="Poniżej minimum" />@endif
                                    {{ Num::qty($p->on_hand, $p->unit) }}
                                </td>
                                <td class="num hidden text-ink-3 sm:table-cell">{{ $p->min_stock > 0 ? Num::qty($p->min_stock, $p->unit) : '—' }}</td>
                                <td class="num hidden text-ink-3 md:table-cell">
                                    {{ bccomp((string) $p->on_hand, '0', 3) > 0 ? Num::money(bcdiv((string) $p->on_hand_value, (string) $p->on_hand, 4)) : '—' }}
                                </td>
                                <td class="num">{{ Num::money($p->on_hand_value) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <x-ui.pagination :paginator="$products" />
    </div>
</div>
