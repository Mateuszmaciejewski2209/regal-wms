@php use App\Support\Num; @endphp

<div>
    <x-ui.page-header :title="$product->name" :back="route('products.index')" back-label="Towary">
        <x-slot:meta>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-[0.8125rem] text-ink-3">
                <span class="code text-ink-2">{{ $product->sku }}</span>
                @if ($product->ean)<span class="code">EAN {{ $product->ean }}</span>@endif
                @if ($product->category)<x-ui.badge>{{ $product->category->name }}</x-ui.badge>@endif
                @unless ($product->is_active)<x-ui.badge tone="outline">nieaktywny</x-ui.badge>@endunless
            </div>
        </x-slot:meta>
        <x-slot:actions>
            <x-ui.button :href="route('labels', ['ids' => [$product->id], 'copies' => 6])" :navigate="false" target="_blank" icon="printer">Drukuj etykiety</x-ui.button>
            @can('products.manage')
                <x-ui.button :href="route('products.edit', $product)" icon="square-pen">Edytuj</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-3 lg:grid-cols-[17rem_minmax(0,1fr)]">
        <div class="space-y-3">
            <section class="card p-4 text-center" aria-label="Etykieta QR">
                <div class="mx-auto w-fit rounded-lg bg-white p-3 text-black">{!! $qr !!}</div>
                <p class="code mt-3 text-ink-2">{{ $product->qrPayload() }}</p>
                <p class="mt-1 text-xs text-ink-4">Zeskanuj telefonem w aplikacji Skaner</p>
            </section>

            <section class="card divide-y divide-line text-[0.8125rem]" aria-label="Parametry">
                <div class="flex justify-between gap-3 px-4 py-2.5"><span class="text-ink-3">Jednostka</span><span class="font-medium">{{ $product->unit->value }} · {{ $product->unit->label() }}</span></div>
                <div class="flex justify-between gap-3 px-4 py-2.5"><span class="text-ink-3">Stan minimalny</span><span class="num font-medium">{{ $product->min_stock > 0 ? Num::qty($product->min_stock, $product->unit) : '—' }}</span></div>
                <div class="flex justify-between gap-3 px-4 py-2.5"><span class="text-ink-3">Cena zakupu</span><span class="num font-medium">{{ $product->default_price !== null ? Num::money($product->default_price) : '—' }}</span></div>
                <div class="flex justify-between gap-3 px-4 py-2.5"><span class="text-ink-3">VAT</span><span class="num font-medium">{{ $product->vat_rate }}%</span></div>
                @if ($product->description)
                    <p class="px-4 py-2.5 text-pretty text-ink-2">{{ $product->description }}</p>
                @endif
            </section>
        </div>

        <div class="min-w-0 space-y-3">
            {{-- Gdzie leży: odpowiedź na najczęstsze pytanie magazyniera --}}
            <section class="card overflow-hidden" aria-labelledby="loc-title">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-3">
                    <h2 id="loc-title" class="flex items-center gap-2 font-semibold"><x-lucide-map-pin class="size-4 text-ink-3" aria-hidden="true" /> Gdzie leży</h2>
                    <x-ui.button size="sm" icon="map" :href="route('layout.plan', ['znajdz' => $product->sku, 'magazyn' => $locations->first()?->rack->warehouse_id])">Pokaż na planie</x-ui.button>
                </div>
                @if ($locations->isEmpty())
                    <p class="px-4 py-4 text-[0.8125rem] text-ink-3">
                        Towar nie ma przypisanej półki.
                        @can('locations.manage') Otwórz plan magazynu, kliknij regał i pole półki, aby go tam położyć – albo zeskanuj etykietę półki telefonem w trybie „Rozmieść”. @endcan
                    </p>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($locations as $loc)
                            <li class="flex items-center justify-between gap-3 px-4 py-2.5">
                                <a href="{{ route('layout.plan', ['magazyn' => $loc->rack->warehouse_id, 'regal' => $loc->rack_id, 'znajdz' => $product->sku]) }}" wire:navigate class="min-w-0 hover:underline">
                                    <span class="code font-medium text-ink">{{ $loc->displayCode() }}</span>
                                    <x-ui.warehouse :warehouse="$loc->rack->warehouse" class="ml-1" /> <span class="text-[0.8125rem] text-ink-3">półka {{ $loc->level }}, pole {{ $loc->bay }}</span>
                                </a>
                                <span class="flex shrink-0 items-center gap-3 text-[0.8125rem] text-ink-3">
                                    @if ($loc->quantity !== null)<span class="num">{{ Num::qty($loc->quantity, $product->unit) }}</span>@endif
                                    <span class="flex items-center gap-1.5" title="Zajmuje {{ $loc->fill }}% pola">
                                        <span class="h-1.5 w-12 overflow-hidden rounded-full bg-surface-3"><span class="block h-full rounded-full bg-accent" style="width: {{ $loc->fill }}%"></span></span>
                                        <span class="num w-9">{{ $loc->fill }}%</span>
                                    </span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="card overflow-hidden" aria-labelledby="stocks-title">
                <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-line px-4 py-3">
                    <h2 id="stocks-title" class="font-semibold">Stany w magazynach</h2>
                    <span class="text-[0.8125rem] text-ink-3">Razem <span class="num font-semibold text-ink">{{ Num::qty($total, $product->unit) }}</span> · {{ Num::money($totalValue) }}</span>
                </div>
                @if ($product->stocks->where('quantity', '!=', 0)->isEmpty())
                    <x-ui.empty icon="boxes" title="Brak stanu" description="Towar nie był jeszcze przyjęty na żaden magazyn." class="py-8" />
                @else
                    <table class="table">
                        <thead><tr><th>Magazyn</th><th class="num">Na stanie</th><th class="num">Cena śr.</th><th class="num">Wartość</th></tr></thead>
                        <tbody>
                            @foreach ($product->stocks->sortBy('warehouse.code') as $stock)
                                @continue(bccomp((string) $stock->quantity, '0', 3) === 0)
                                <tr>
                                    <td><x-ui.warehouse :warehouse="$stock->warehouse" name /></td>
                                    <td class="num font-medium">{{ Num::qty($stock->quantity, $product->unit) }}</td>
                                    <td class="num text-ink-3">{{ Num::money($stock->averagePrice()) }}</td>
                                    <td class="num">{{ Num::money($stock->value) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>

            <section class="card overflow-hidden" aria-labelledby="ledger-title">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-3">
                    <h2 id="ledger-title" class="font-semibold">Kartoteka ruchów</h2>
                    <x-ui.select wire:model.live="warehouse" size="sm" class="w-44" label="Filtr magazynu"
                                 :options="['' => 'Wszystkie magazyny'] + $warehouses->pluck('code', 'id')->all()" />
                </div>
                @if ($movements->isEmpty())
                    <x-ui.empty icon="history" title="Brak ruchów" class="py-8" />
                @else
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead><tr><th>Data</th><th>Dokument</th><th>Mag.</th><th class="num">Przychód</th><th class="num">Rozchód</th><th class="num">Cena</th><th class="num">Stan po</th></tr></thead>
                            <tbody>
                                @foreach ($movements as $m)
                                    <tr class="relative">
                                        <td class="num text-left text-ink-3">{{ $m->created_at->format('d.m.Y H:i') }}</td>
                                        <td>
                                            <a href="{{ route('documents.show', $m->document_id) }}" wire:navigate class="row-link code">{{ $m->document->displayNumber() }}</a>
                                            @if ($m->is_reversal)<x-ui.badge tone="danger" class="ml-1">storno</x-ui.badge>@endif
                                        </td>
                                        <td><x-ui.warehouse :warehouse="$m->warehouse" /></td>
                                        <td class="num text-in">{{ $m->quantity > 0 ? Num::qty($m->quantity) : '' }}</td>
                                        <td class="num text-out">{{ $m->quantity < 0 ? Num::qty(abs((float) $m->quantity)) : '' }}</td>
                                        <td class="num text-ink-3">{{ Num::money($m->unit_price) }}</td>
                                        <td class="num font-medium">{{ Num::qty($m->balance_quantity) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                <x-ui.pagination :paginator="$movements" />
            </section>
        </div>
    </div>
</div>
