@php use App\Support\Num; @endphp

<div>
    <x-ui.page-header title="Towary" description="Kartoteka towarów. Każdy towar ma etykietę QR z indeksem – do skanowania telefonem lub czytnikiem.">
        <x-slot:actions>
            @can('products.manage')
                <x-ui.button :href="route('products.create')" variant="primary" icon="plus">Nowy towar</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4 grid gap-2 sm:grid-cols-[minmax(0,1fr)_13rem_11rem]">
        <x-ui.search wire:model.live.debounce.300ms="search" placeholder="Nazwa, indeks lub EAN…" />
        <x-ui.select wire:model.live="category" :options="$categoryOptions" label="Kategoria" />
        <x-ui.select wire:model.live="status" :options="['active' => 'Aktywne', 'inactive' => 'Nieaktywne', 'all' => 'Wszystkie']" label="Status" />
    </div>

    <div class="card overflow-hidden">
        {{-- Pasek zaznaczenia --}}
        <div class="flex min-h-12 flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-2 text-[0.8125rem]">
            @if (count($selected))
                <p class="text-ink-2">
                    Zaznaczono <span class="num font-semibold text-ink">{{ count($selected) }}</span>
                    @if ($pageState === 'all' && count($selected) < $filteredCount)
                        · <button type="button" wire:click="selectAllFiltered" class="font-medium text-ink underline underline-offset-2">Zaznacz wszystkie pasujące ({{ $filteredCount }})</button>
                    @endif
                </p>
                <div class="flex gap-2">
                    <x-ui.button size="sm" variant="ghost" wire:click="clearSelection">Wyczyść</x-ui.button>
                    <x-ui.button size="sm" variant="primary" icon="printer" :href="route('labels', ['ids' => $selected])" :navigate="false" target="_blank">Drukuj etykiety QR</x-ui.button>
                </div>
            @else
                <p class="text-ink-4">Kliknij wiersz, aby zaznaczyć towar – np. do wydruku etykiet QR.</p>
                <x-ui.button size="sm" icon="qr-code" :href="route('labels')" :navigate="false" target="_blank">Etykiety wszystkich aktywnych</x-ui.button>
            @endif
        </div>

        @if ($products->isEmpty())
            <x-ui.empty icon="package" title="Brak towarów" :description="$search ? 'Nic nie pasuje do wyszukiwania.' : 'Kartoteka jest pusta.'" />
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="w-10">
                                <input type="checkbox" aria-label="Zaznacz wszystkie na stronie"
                                       wire:click="togglePage({{ Js::from($pageIds) }})"
                                       @checked($pageState === 'all')
                                       x-init="$el.indeterminate = {{ $pageState === 'some' ? 'true' : 'false' }}"
                                       wire:key="head-{{ $pageState }}-{{ $products->currentPage() }}">
                            </th>
                            <th class="w-28">Indeks</th>
                            <th>Nazwa</th>
                            <th class="hidden lg:table-cell">EAN</th>
                            <th class="hidden md:table-cell">Kategoria</th>
                            <th class="num w-32">Na stanie</th>
                            <th class="num hidden w-28 sm:table-cell">Cena zakupu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($products as $product)
                            @php
                                $isSelected = in_array((string) $product->id, $selected, true);
                                $isLow = $product->min_stock > 0 && bccomp((string) ($product->total_quantity ?? 0), (string) $product->min_stock, 3) < 0;
                            @endphp
                            <tr wire:key="p-{{ $product->id }}" aria-selected="{{ $isSelected ? 'true' : 'false' }}" class="cursor-pointer"
                                x-on:click="if (! $event.target.closest('a, input, button')) $el.querySelector('input[type=checkbox]').click()">
                                <td>
                                    <input type="checkbox" value="{{ $product->id }}" wire:model.live="selected" aria-label="Zaznacz {{ $product->name }}">
                                </td>
                                <td class="code whitespace-nowrap text-ink-2">{{ $product->sku }}</td>
                                <td class="min-w-56">
                                    <a href="{{ route('products.show', $product) }}" wire:navigate class="font-medium text-ink hover:underline">{{ $product->name }}</a>
                                    @unless ($product->is_active)<x-ui.badge tone="outline" class="ml-1">nieaktywny</x-ui.badge>@endunless
                                </td>
                                <td class="code hidden text-ink-3 lg:table-cell">{{ $product->ean ?? '—' }}</td>
                                <td class="hidden text-ink-3 md:table-cell">{{ $product->category?->name ?? '—' }}</td>
                                <td @class(['num font-medium', 'text-warn' => $isLow])>
                                    @if ($isLow)<x-lucide-triangle-alert class="mr-1 inline size-3.5 -translate-y-px" aria-label="Poniżej minimum" />@endif
                                    {{ Num::qty($product->total_quantity ?? 0, $product->unit) }}
                                </td>
                                <td class="num hidden text-ink-3 sm:table-cell">{{ $product->default_price !== null ? Num::money($product->default_price) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <x-ui.pagination :paginator="$products" />
    </div>
</div>
