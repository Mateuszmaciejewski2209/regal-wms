@php
    use App\Models\ProductLocation;
    use App\Support\Num;
    $canLayout = auth()->user()->can('layout.manage');
    $canPlace = auth()->user()->can('locations.manage');
    $occClass = fn (int $p) => match (true) {
        $p === 0 => 'occ-0',
        $p <= 33 => 'occ-1',
        $p <= 66 => 'occ-2',
        default => 'occ-3',
    };
@endphp

<div>
    <x-ui.page-header title="Plan magazynu" description="Regały na hali i rozmieszczenie towarów na półkach. Kliknij regał, aby zobaczyć jego półki.">
        <x-slot:actions>
            @if ($canLayout && $warehouse)
                <x-ui.button variant="ghost" icon="ruler" wire:click="openPlanSize">Rozmiar hali</x-ui.button>
                @if ($racks->isNotEmpty())
                    <x-ui.button :icon="$editMode ? 'check' : 'move'" wire:click="$toggle('editMode')" :aria-pressed="$editMode ? 'true' : 'false'"
                                 :class="$editMode ? 'border-ring! bg-surface-2!' : ''">
                        {{ $editMode ? 'Zakończ układanie' : 'Ułóż regały' }}
                    </x-ui.button>
                @endif
                <x-ui.button variant="primary" icon="plus" wire:click="openConfigurator">Dodaj regały</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4 grid gap-2 sm:grid-cols-[16rem_minmax(0,1fr)]">
        <x-ui.select wire:model.live="warehouseId" :options="$warehouseOptions" label="Magazyn" />
        <x-ui.search wire:model.live.debounce.300ms="find" placeholder="Znajdź towar na planie – nazwa, indeks lub EAN…" />
    </div>

    @if ($racks->isEmpty())
        <div class="card">
            <x-ui.empty icon="layout-grid" title="Ten magazyn nie ma jeszcze regałów"
                        :description="$canLayout ? 'Użyj „Dodaj regały” – konfigurator utworzy całą serię (np. REG-01 … REG-40) i ułoży ją na planie.' : 'Regały konfiguruje kierownik magazynu.'" />
        </div>
    @else
        <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_28rem]">
            {{-- Widok z góry --}}
            <section class="card min-w-0 overflow-hidden" aria-labelledby="plan-title">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-3">
                    <div>
                        <h2 id="plan-title" class="flex items-center gap-2 font-semibold"><span class="wh-{{ $warehouse->colorKey() }} wh-dot size-2.5" aria-hidden="true"></span>{{ $warehouse->code }} · {{ $warehouse->name }}</h2>
                        <p class="text-[0.8125rem] text-ink-3">
                            {{ Num::plural($stats['racks'], 'regał', 'regały', 'regałów') }} ·
                            {{ Num::plural($stats['slots'], 'pole', 'pola', 'pól') }} ·
                            zajętość {{ $stats['occupancy'] }}% ·
                            {{ Num::plural($stats['placed'], 'towar rozmieszczony', 'towary rozmieszczone', 'towarów rozmieszczonych') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3 text-xs text-ink-3" aria-label="Legenda zajętości">
                        <span class="flex items-center gap-1.5"><span class="occ-0 size-3 rounded-[3px] border"></span>pusty</span>
                        <span class="flex items-center gap-1.5"><span class="occ-1 size-3 rounded-[3px]"></span>do 33%</span>
                        <span class="flex items-center gap-1.5"><span class="occ-2 size-3 rounded-[3px]"></span>do 66%</span>
                        <span class="flex items-center gap-1.5"><span class="occ-3 size-3 rounded-[3px]"></span>powyżej</span>
                    </div>
                </div>

                @if ($editMode)
                    <p class="flex items-center gap-2 border-b border-line bg-surface-2/60 px-4 py-2 text-[0.8125rem] text-ink-2" role="status">
                        <x-lucide-move class="size-4 text-ink-3" aria-hidden="true" />
                        Przeciągnij regał w nowe miejsce (albo strzałki na klawiaturze). Klawisz <span class="kbd">R</span> obraca zaznaczony regał.
                    </p>
                @endif

                <div class="overflow-auto p-4" x-data="rackPlan({{ $cell }}, {{ $warehouse->plan_width }}, {{ $warehouse->plan_height }})">
                    <div class="plan-grid relative rounded-lg" style="width: {{ $warehouse->plan_width * $cell }}px; height: {{ $warehouse->plan_height * $cell }}px; --cell: {{ $cell }}px">
                        @foreach ($racks as $r)
                            @php
                                $f = $r->footprint();
                                $occ = $r->occupancy();
                                $matched = $matchedRacks->contains($r->id);
                            @endphp
                            <button type="button" wire:key="rack-{{ $r->id }}-{{ $editMode ? 1 : 0 }}"
                                    @class([
                                        'rack absolute flex items-center justify-center rounded-[5px] border font-mono text-[0.6875rem] font-medium leading-none',
                                        $occClass($occ),
                                        'is-selected' => $rack?->id === $r->id,
                                        'is-match' => $matched,
                                        'cursor-grab touch-none active:cursor-grabbing' => $editMode,
                                    ])
                                    style="left: {{ $r->pos_x * $cell + 1 }}px; top: {{ $r->pos_y * $cell + 1 }}px; width: {{ $f['w'] * $cell - 2 }}px; height: {{ $f['h'] * $cell - 2 }}px"
                                    data-rect="{{ $r->pos_x }},{{ $r->pos_y }},{{ $f['w'] }},{{ $f['h'] }}"
                                    aria-label="Regał {{ $r->code }}, {{ $r->levels }} półki × {{ $r->bays }} pola, zajętość {{ $occ }}%{{ $matched ? ', zawiera szukany towar' : '' }}"
                                    aria-pressed="{{ $rack?->id === $r->id ? 'true' : 'false' }}"
                                    title="{{ $r->code }} · zajętość {{ $occ }}%"
                                    @if ($editMode)
                                        x-on:pointerdown="down($event, {{ $r->id }})"
                                        x-on:pointermove="move($event)"
                                        x-on:pointerup="up()"
                                        x-on:pointercancel="cancel()"
                                        x-on:keydown.arrow-left.prevent="$wire.moveRack({{ $r->id }}, {{ max(0, $r->pos_x - 1) }}, {{ $r->pos_y }})"
                                        x-on:keydown.arrow-right.prevent="$wire.moveRack({{ $r->id }}, {{ $r->pos_x + 1 }}, {{ $r->pos_y }})"
                                        x-on:keydown.arrow-up.prevent="$wire.moveRack({{ $r->id }}, {{ $r->pos_x }}, {{ max(0, $r->pos_y - 1) }})"
                                        x-on:keydown.arrow-down.prevent="$wire.moveRack({{ $r->id }}, {{ $r->pos_x }}, {{ $r->pos_y + 1 }})"
                                        x-on:keydown.r.prevent="$wire.rotateRack({{ $r->id }})"
                                    @else
                                        wire:click="selectRack({{ $r->id }})"
                                    @endif
                            >
                                <span @class(['truncate px-0.5', '[writing-mode:vertical-rl]' => $r->rotated])>{{ $r->code }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- Front regału --}}
            <section class="card self-start" aria-labelledby="rack-title">
                @if ($rack)
                    @php $rackOcc = $rack->occupancy(); @endphp
                    <div class="flex items-start justify-between gap-3 border-b border-line px-4 py-3">
                        <div class="min-w-0">
                            <h2 id="rack-title" class="font-mono text-base font-semibold">{{ $rack->code }}</h2>
                            <p class="text-[0.8125rem] text-ink-3">
                                {{ Num::plural($rack->levels, 'półka', 'półki', 'półek') }} × {{ Num::plural($rack->bays, 'pole', 'pola', 'pól') }} · zajętość {{ $rackOcc }}%
                                @if ($rack->note)<br>{{ $rack->note }}@endif
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <x-ui.tooltip text="Drukuj etykiety QR półek tego regału" position="bottom">
                                <x-ui.button size="sm" icon-only icon="printer" :href="route('labels.locations', $rack)" :navigate="false" target="_blank" aria-label="Etykiety półek" />
                            </x-ui.tooltip>
                            @if ($canLayout)
                                <x-ui.dropdown>
                                    <x-slot:trigger><x-ui.button size="sm" icon-only icon="ellipsis" aria-label="Akcje regału" /></x-slot:trigger>
                                    <x-ui.dropdown-item icon="square-pen" wire:click="openRackForm">Edytuj regał</x-ui.dropdown-item>
                                    <x-ui.dropdown-item icon="rotate-cw" wire:click="rotateRack({{ $rack->id }})">Obróć na planie</x-ui.dropdown-item>
                                    <x-ui.dropdown-item icon="trash-2" danger
                                        x-on:click="$confirm({{ Js::from(['title' => 'Usunąć regał '.$rack->code.'?', 'message' => $rack->placements->isNotEmpty() ? 'Leży na nim '.Num::plural($rack->placements->count(), 'towar', 'towary', 'towarów').' – ich lokalizacje zostaną usunięte (stany magazynowe się nie zmienią).' : 'Regał jest pusty.', 'confirm' => 'Usuń regał']) }}).then(ok => ok && $wire.deleteRack())">Usuń regał</x-ui.dropdown-item>
                                </x-ui.dropdown>
                            @endif
                        </div>
                    </div>

                    <div class="overflow-x-auto p-4">
                        <div class="grid gap-x-1.5" style="grid-template-columns: 2rem repeat({{ $rack->bays }}, minmax(5.5rem, 1fr))">
                            <div></div>
                            @for ($bay = 1; $bay <= $rack->bays; $bay++)
                                <div class="pb-1 text-center text-xs text-ink-4">pole {{ $bay }}</div>
                            @endfor

                            @for ($level = $rack->levels; $level >= 1; $level--)
                                <div class="flex items-center text-xs font-medium text-ink-3">P{{ $level }}</div>
                                @for ($bay = 1; $bay <= $rack->bays; $bay++)
                                    @php
                                        $items = $rack->placements->where('level', $level)->where('bay', $bay)->values();
                                        $used = min(100, $items->sum('fill'));
                                        $hit = $matches->contains($rack->id.':'.$level.':'.$bay);
                                    @endphp
                                    <button type="button" wire:click="openSlot({{ $level }}, {{ $bay }})" wire:key="slot-{{ $rack->id }}-{{ $level }}-{{ $bay }}"
                                            @class(['shelf-slot group relative mb-1.5 flex h-16 flex-col justify-end rounded-t-md border border-b-[3px] border-line border-b-ink-3/60 bg-surface-2/40 p-1 text-left transition-colors hover:bg-surface-2', 'ring-2 ring-ring ring-offset-1 ring-offset-surface' => $hit])
                                            aria-label="Półka {{ $level }}, pole {{ $bay }}: zajęte {{ $used }}%{{ $items->isNotEmpty() ? ', '.$items->map(fn ($i) => $i->product->name)->implode(', ') : ', puste' }}">
                                        <span class="flex h-11 w-full items-end gap-px overflow-hidden">
                                            @foreach ($items as $item)
                                                <span class="flex h-full min-w-0 items-end rounded-[3px] border border-accent-line bg-accent-soft px-1 pb-0.5 text-[0.625rem] leading-tight text-ink"
                                                      style="width: {{ $item->fill }}%" title="{{ $item->product->name }} · {{ $item->fill }}%">
                                                    <span class="truncate">{{ $item->product->sku }}</span>
                                                </span>
                                            @endforeach
                                        </span>
                                        <span class="mt-0.5 text-[0.625rem] text-ink-4">{{ $used ? $used.'% zajęte' : 'wolne' }}</span>
                                    </button>
                                @endfor
                            @endfor
                        </div>
                        <p class="mt-1 text-xs text-ink-4">Półki numerowane od podłogi (P1). Kliknij pole, aby {{ $canPlace ? 'położyć lub zdjąć towar' : 'zobaczyć, co na nim leży' }}.</p>
                    </div>
                @else
                    <x-ui.empty icon="mouse-pointer-click" title="Wybierz regał" description="Kliknij regał na planie, aby zobaczyć jego półki i towary." />
                @endif
            </section>
        </div>
    @endif

    {{-- Konfigurator serii regałów --}}
    <x-ui.modal wire:model="showConfigurator" title="Dodaj regały" description="Utwórz całą serię naraz – kody nadadzą się same, a regały ułożą się w rzędy na planie." max-width="xl">
        <form wire:submit="createRacks" id="config-form" class="space-y-5">
            <fieldset>
                <legend class="label">Nazewnictwo</legend>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <x-ui.field label="Prefiks" for="cfg-prefix" error="config.prefix" class="col-span-2 sm:col-span-2">
                        <input id="cfg-prefix" type="text" wire:model.live.debounce.200ms="config.prefix" class="control font-mono" placeholder="REG-" autocomplete="off">
                    </x-ui.field>
                    <x-ui.field label="Od numeru" for="cfg-start" error="config.start">
                        <input id="cfg-start" type="number" min="0" wire:model.live.debounce.200ms="config.start" class="control text-right tabular-nums">
                    </x-ui.field>
                    <x-ui.field label="Cyfr" for="cfg-digits" error="config.digits">
                        <input id="cfg-digits" type="number" min="1" max="4" wire:model.live.debounce.200ms="config.digits" class="control text-right tabular-nums">
                    </x-ui.field>
                    <x-ui.field label="Sufiks" for="cfg-suffix" error="config.suffix">
                        <input id="cfg-suffix" type="text" wire:model.live.debounce.200ms="config.suffix" class="control font-mono" placeholder="np. -A" autocomplete="off">
                    </x-ui.field>
                </div>
            </fieldset>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <x-ui.field label="Ile regałów" for="cfg-count" error="config.count" required>
                    <input id="cfg-count" type="number" min="1" max="200" wire:model.live.debounce.200ms="config.count" class="control text-right tabular-nums">
                </x-ui.field>
                <x-ui.field label="Półek w regale" for="cfg-levels" error="config.levels" required>
                    <input id="cfg-levels" type="number" min="1" max="12" wire:model="config.levels" class="control text-right tabular-nums">
                </x-ui.field>
                <x-ui.field label="Pól na półce" for="cfg-bays" error="config.bays" required>
                    <input id="cfg-bays" type="number" min="1" max="12" wire:model="config.bays" class="control text-right tabular-nums">
                </x-ui.field>
                <x-ui.field label="Regałów w rzędzie" for="cfg-row" error="config.per_row" required>
                    <input id="cfg-row" type="number" min="1" max="30" wire:model="config.per_row" class="control text-right tabular-nums">
                </x-ui.field>
            </div>

            <div class="rounded-xl border border-line bg-surface-2/50 p-3">
                <div class="mb-2 text-xs font-medium tracking-wide text-ink-4 uppercase">Podgląd kodów</div>
                <div class="flex flex-wrap gap-1.5">
                    @forelse ($previewCodes as $code)
                        @if ($code === '…')<span class="px-1 text-ink-4">…</span>@else<span class="badge border border-line-strong bg-surface font-mono text-ink">{{ $code }}</span>@endif
                    @empty
                        <span class="text-[0.8125rem] text-ink-4">Uzupełnij pola powyżej.</span>
                    @endforelse
                </div>
                @if ($previewCodes)
                    <p class="mt-2 text-xs text-ink-3">Etykiety półek dostaną kody typu <span class="code">{{ $previewCodes[0] }}-2-3</span> (regał – półka – pole).</p>
                @endif
            </div>
        </form>
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="$wire.showConfigurator = false">Anuluj</x-ui.button>
            <x-ui.button type="submit" form="config-form" variant="primary" icon="plus">Utwórz regały</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    {{-- Edycja regału --}}
    <x-ui.modal wire:model="showRackForm" title="Edycja regału" max-width="md">
        <form wire:submit="saveRack" id="rack-form" class="grid grid-cols-2 gap-4">
            <x-ui.field label="Kod" for="rf-code" error="rackForm.code" required class="col-span-2">
                <input id="rf-code" type="text" wire:model="rackForm.code" class="control font-mono" autocomplete="off">
            </x-ui.field>
            <x-ui.field label="Półek" for="rf-levels" error="rackForm.levels" required>
                <input id="rf-levels" type="number" min="1" max="12" wire:model="rackForm.levels" class="control text-right tabular-nums">
            </x-ui.field>
            <x-ui.field label="Pól na półce" for="rf-bays" error="rackForm.bays" required>
                <input id="rf-bays" type="number" min="1" max="12" wire:model="rackForm.bays" class="control text-right tabular-nums">
            </x-ui.field>
            <x-ui.field label="Opis" for="rf-note" error="rackForm.note" class="col-span-2">
                <input id="rf-note" type="text" wire:model="rackForm.note" class="control" placeholder="np. towary ciężkie, strefa przyjęć" autocomplete="off">
            </x-ui.field>
            <label class="col-span-2 flex items-center gap-2 text-ink-2"><input type="checkbox" wire:model="rackForm.rotated"> Ustawiony pionowo na planie</label>
        </form>
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="$wire.showRackForm = false">Anuluj</x-ui.button>
            <x-ui.button type="submit" form="rack-form" variant="primary">Zapisz</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    {{-- Rozmiar hali --}}
    <x-ui.modal wire:model="showPlanSize" title="Rozmiar hali" description="Plan to siatka kratek – jedna kratka odpowiada szerokości jednego pola regału." max-width="sm">
        <form wire:submit="savePlanSize" id="size-form" class="grid grid-cols-2 gap-4">
            <x-ui.field label="Szerokość (kratki)" for="ps-w" error="planSize.width" required class="col-span-2 sm:col-span-1">
                <input id="ps-w" type="number" min="8" max="200" wire:model="planSize.width" class="control text-right tabular-nums">
            </x-ui.field>
            <x-ui.field label="Głębokość (kratki)" for="ps-h" error="planSize.height" required class="col-span-2 sm:col-span-1">
                <input id="ps-h" type="number" min="6" max="200" wire:model="planSize.height" class="control text-right tabular-nums">
            </x-ui.field>
        </form>
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="$wire.showPlanSize = false">Anuluj</x-ui.button>
            <x-ui.button type="submit" form="size-form" variant="primary">Zapisz</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    {{-- Pole półki --}}
    @if ($rack)
        <x-ui.modal wire:model="showSlot" :title="$slotLevel ? ProductLocation::label($rack, $slotLevel, $slotBay) : 'Pole półki'"
                    description="Zajętość podajesz orientacyjnie – ile szerokości pola zajmuje towar. Zmiany zapisują się od razu." max-width="lg">
            {{-- Wolne miejsce liczone na żywo z suwaków; nowy towar dostaje domyślnie całą resztę pola. --}}
            <div class="space-y-4" x-data="{
                    others(id) {
                        return Object.entries(this.$wire.slotEdits ?? {}).reduce((sum, [key, edit]) => sum + (Number(key) === id ? 0 : Number(edit.fill || 0)), 0);
                    },
                    get used() { return this.others(-1) },
                    get free() { return Math.max(0, 100 - this.used) },
                    clampFill(id, el) {
                        const others = this.others(id);
                        const fill = Math.max(5, Math.min(Number(el.value), 100 - others));
                        el.value = fill;
                        this.$wire.$set(`slotEdits.${id}.fill`, fill, false);
                        this.$wire.$set('newFill', Math.max(5, 100 - others - fill), false);
                    },
                }">
                <div class="flex items-center gap-3 text-xs text-ink-3">
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-surface-3" aria-hidden="true">
                        <div class="h-full rounded-full bg-accent" x-bind:style="`width: ${used}%`" style="width: {{ 100 - $slotFree }}%"></div>
                    </div>
                    <span>Wolne: <span class="font-medium text-ink tabular-nums" x-text="free + '%'">{{ $slotFree }}%</span></span>
                </div>
                @if ($slotItems->isEmpty())
                    <p class="rounded-lg border border-dashed border-line-strong p-4 text-center text-[0.8125rem] text-ink-3">Na tym polu nic nie leży.</p>
                @else
                    <ul class="divide-y divide-line rounded-xl border border-line">
                        @foreach ($slotItems as $placement)
                            <li class="space-y-2 p-3" wire:key="pl-{{ $placement->id }}">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <a href="{{ route('products.show', $placement->product) }}" wire:navigate class="font-medium text-ink hover:underline">{{ $placement->product->name }}</a>
                                        <div class="code text-xs text-ink-4">{{ $placement->product->sku }}</div>
                                    </div>
                                    @if ($canPlace)
                                        <x-ui.button size="sm" variant="ghost" icon="x" wire:click="removePlacement({{ $placement->id }})" aria-label="Zdejmij {{ $placement->product->name }} z półki">Zdejmij</x-ui.button>
                                    @endif
                                </div>
                                @if ($canPlace)
                                    <div class="flex flex-wrap items-end gap-2">
                                        <div class="min-w-0 flex-1">
                                            <div class="mb-1 text-xs text-ink-3">Zajmuje: <span class="font-medium text-ink tabular-nums" x-text="$wire.slotEdits[{{ $placement->id }}]?.fill + '%'">{{ $placement->fill }}%</span></div>
                                            <input type="range" min="5" max="100" step="5" wire:model="slotEdits.{{ $placement->id }}.fill" class="w-full accent-[var(--accent)]"
                                                   x-on:input="clampFill({{ $placement->id }}, $el)" x-on:change="$wire.savePlacement({{ $placement->id }})"
                                                   aria-label="Zajmowane miejsce: {{ $placement->product->name }}">
                                        </div>
                                        <div class="w-28">
                                            <label class="mb-1 block text-xs text-ink-3" for="pq-{{ $placement->id }}">Ilość ({{ $placement->product->unit->value }})</label>
                                            <input id="pq-{{ $placement->id }}" type="text" inputmode="decimal" wire:model="slotEdits.{{ $placement->id }}.quantity" class="control h-8 text-right tabular-nums" placeholder="—"
                                                   x-on:change="$wire.savePlacement({{ $placement->id }})" x-on:keydown.enter.prevent="$el.blur()">
                                        </div>
                                    </div>
                                @else
                                    <div class="text-[0.8125rem] text-ink-3">Zajmuje {{ $placement->fill }}% pola @if ($placement->quantity !== null) · {{ Num::qty($placement->quantity, $placement->product->unit) }} @endif</div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($canPlace && $slotFree > 0)
                    <div class="rounded-xl border border-line p-3" data-search>
                        <div class="mb-2 font-medium text-ink">Połóż towar na tym polu</div>
                        <x-ui.search wire:model.live.debounce.250ms="productSearch" placeholder="Nazwa, indeks lub EAN…"
                                     x-on:keydown.enter.prevent="$el.closest('[data-search]').querySelector('[data-result]')?.click()" />
                        <div class="mt-3 flex flex-wrap items-end gap-3">
                            <div class="min-w-40 flex-1">
                                <div class="mb-1 text-xs text-ink-3">Zajmie: <span class="font-medium text-ink tabular-nums" x-text="$wire.newFill + '%'">{{ $newFill }}%</span> (wolne <span class="tabular-nums" x-text="free + '%'">{{ $slotFree }}%</span>)</div>
                                <input type="range" min="5" max="{{ $slotFree }}" x-bind:max="Math.max(5, free)" step="5" wire:model="newFill" class="w-full accent-[var(--accent)]" aria-label="Ile miejsca zajmie towar">
                            </div>
                            <div class="w-28">
                                <label class="mb-1 block text-xs text-ink-3" for="new-qty">Ilość (opcjonalnie)</label>
                                <input id="new-qty" type="text" inputmode="decimal" wire:model="newQuantity" class="control h-8 text-right tabular-nums" placeholder="—">
                            </div>
                        </div>
                        @if ($searchResults->isNotEmpty())
                            <ul class="mt-3 divide-y divide-line rounded-lg border border-line">
                                @foreach ($searchResults as $product)
                                    <li class="flex items-center justify-between gap-3 px-3 py-2">
                                        <span class="min-w-0">
                                            <span class="block truncate text-[0.8125rem] font-medium text-ink">{{ $product->name }}</span>
                                            <span class="code block text-xs text-ink-4">{{ $product->sku }} · na stanie {{ Num::qty($product->stocks->where('warehouse_id', $warehouse->id)->sum('quantity'), $product->unit) }}</span>
                                        </span>
                                        <x-ui.button size="sm" variant="primary" icon="arrow-down-to-line" data-result wire:click="place({{ $product->id }})">Połóż</x-ui.button>
                                    </li>
                                @endforeach
                            </ul>
                        @elseif (mb_strlen(trim($productSearch)) >= 2)
                            <p class="mt-3 text-[0.8125rem] text-ink-3">Nie znaleziono towaru.</p>
                        @endif
                    </div>
                @elseif ($canPlace)
                    <p class="text-[0.8125rem] text-ink-3">Pole jest pełne – zmniejsz zajętość lub zdejmij towar, by położyć coś nowego.</p>
                @endif
            </div>
        </x-ui.modal>
    @endif
</div>
