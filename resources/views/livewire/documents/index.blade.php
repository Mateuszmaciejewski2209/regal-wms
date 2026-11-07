@php use App\Support\Num; @endphp

<div>
    <x-ui.page-header title="Dokumenty magazynowe" description="Przyjęcia, wydania, przesunięcia i inwentaryzacje.">
        <x-slot:actions>
            @can('documents.create')<x-new-document-menu />@endcan
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Zakładki typów --}}
    <div class="-mx-4 mb-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
        <div class="inline-flex gap-1 rounded-xl border border-line bg-surface p-1" role="tablist" aria-label="Typ dokumentu">
            <button type="button" role="tab" wire:click="$set('type', '')" aria-selected="{{ $type === '' ? 'true' : 'false' }}"
                    @class(['flex h-8 items-center gap-2 rounded-lg px-3 text-[0.8125rem] font-medium whitespace-nowrap transition-colors', 'bg-surface-3 text-ink' => $type === '', 'text-ink-3 hover:text-ink' => $type !== ''])>
                Wszystkie <span class="num text-xs text-ink-4">{{ $counts->sum() }}</span>
            </button>
            @foreach ($types as $t)
                <button type="button" role="tab" wire:click="$set('type', '{{ $t->value }}')" aria-selected="{{ $type === $t->value ? 'true' : 'false' }}" title="{{ $t->label() }}"
                        @class(['flex h-8 items-center gap-2 rounded-lg px-3 text-[0.8125rem] font-medium whitespace-nowrap transition-colors', 'bg-surface-3 text-ink' => $type === $t->value, 'text-ink-3 hover:text-ink' => $type !== $t->value])>
                    <span class="font-mono">{{ $t->value }}</span>
                    <span class="num text-xs text-ink-4">{{ $counts[$t->value] ?? 0 }}</span>
                </button>
            @endforeach
        </div>
    </div>

    {{-- Filtry w jednym wierszu --}}
    <div class="mb-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_10rem_11rem_9.5rem_9.5rem_auto]">
        <x-ui.search wire:model.live.debounce.300ms="search" placeholder="Numer, kontrahent, NIP, nr obcy…" />
        <x-ui.select wire:model.live="status" :options="$statusOptions" label="Status" />
        <x-ui.select wire:model.live="warehouse" :options="$warehouseOptions" label="Magazyn" />
        <input type="date" wire:model.live="from" class="control" aria-label="Data od">
        <input type="date" wire:model.live="to" class="control" aria-label="Data do">
        @if ($hasFilters)
            <x-ui.button wire:click="clearFilters" variant="ghost" icon="x">Wyczyść</x-ui.button>
        @endif
    </div>

    <div class="card overflow-hidden">
        @if ($documents->isEmpty())
            <x-ui.empty icon="file-text" title="Brak dokumentów" :description="$hasFilters || $type ? 'Żaden dokument nie pasuje do filtrów.' : (auth()->user()->can('documents.create') ? 'Utwórz pierwszy dokument przyciskiem „Nowy dokument”.' : 'Nie ma jeszcze dokumentów.')" />
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="w-16">Typ</th>
                            <th>Numer</th>
                            <th>Data</th>
                            <th class="hidden lg:table-cell">Kontrahent / cel</th>
                            <th>Magazyn</th>
                            <th class="num hidden sm:table-cell">Poz.</th>
                            <th>Status</th>
                            <th class="num">Wartość</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($documents as $doc)
                            <tr class="relative" wire:key="doc-{{ $doc->id }}">
                                <td><x-ui.doc-type :type="$doc->type" /></td>
                                <td>
                                    <a href="{{ route('documents.show', $doc) }}" wire:navigate class="row-link code">{{ $doc->displayNumber() }}</a>
                                    @if ($doc->external_number)<div class="text-xs text-ink-4">{{ $doc->external_number }}</div>@endif
                                </td>
                                <td class="num text-left whitespace-nowrap text-ink-2">
                                    {{ $doc->dateTimeLabel() }}
                                    @if ($doc->stampedAt() && ! $doc->issued_at->isSameDay($doc->stampedAt()))
                                        <div class="text-xs text-ink-4">{{ $doc->isDraft() ? 'utw.' : 'zatw.' }} {{ $doc->stampedAt()->format('d.m.Y H:i') }}</div>
                                    @endif
                                </td>
                                <td class="hidden max-w-64 truncate text-ink-2 lg:table-cell">
                                    @if ($doc->contractor) {{ $doc->contractor->name }}
                                    @elseif ($doc->targetWarehouse) <span class="text-ink-3">→</span> <x-ui.warehouse :warehouse="$doc->targetWarehouse" />
                                    @else <span class="text-ink-4">—</span> @endif
                                </td>
                                <td><x-ui.warehouse :warehouse="$doc->warehouse" /></td>
                                <td class="num hidden text-ink-3 sm:table-cell">{{ $doc->items_count }}</td>
                                <td><x-ui.status :status="$doc->status" /></td>
                                <td class="num">{{ $doc->isDraft() ? '—' : Num::money($doc->total_value) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <x-ui.pagination :paginator="$documents" />
    </div>
</div>
