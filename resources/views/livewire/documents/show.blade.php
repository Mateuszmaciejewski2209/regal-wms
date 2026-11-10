@php
    use App\Enums\DocumentType;
    use App\Support\Num;
    $isInventory = $type === DocumentType::INW;
    $hasPrices = $type->hasEnteredPrices();
    $draft = $doc->isDraft();
    $showValue = ! $isInventory;
@endphp

<div>
    <x-ui.page-header :title="$doc->displayNumber()" :back="route('documents.index', ['type' => $type->value])" back-label="Dokumenty">
        <x-slot:meta>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-[0.8125rem] text-ink-3">
                <x-ui.doc-type :type="$type" />
                <x-ui.status :status="$doc->status" />
                <span>{{ $type->label() }}</span>
            </div>
        </x-slot:meta>

        <x-slot:actions>
            @if ($draft)
                @can('scanner.use')
                    <x-ui.tooltip :text="$scannerAttached ? 'Skany z Twojego telefonu dopisują się do tego dokumentu. Kliknij, aby odłączyć.' : 'Kieruj skany z Twojego telefonu do tego dokumentu.'" position="bottom">
                        <x-ui.button wire:click="toggleScanner" :icon="$scannerAttached ? 'scan-line' : 'smartphone'" :aria-pressed="$scannerAttached ? 'true' : 'false'"
                                     :class="$scannerAttached ? 'border-accent-line! bg-accent-soft! text-accent!' : ''">
                            {{ $scannerAttached ? 'Skaner podłączony' : 'Podłącz skaner' }}
                        </x-ui.button>
                    </x-ui.tooltip>
                @endcan

                <x-ui.dropdown>
                    <x-slot:trigger>
                        <x-ui.button icon-only icon="ellipsis" aria-label="Więcej akcji" />
                    </x-slot:trigger>
                    @if ($canEdit)
                        <x-ui.dropdown-item icon="square-pen" wire:click="openHeader">Edytuj nagłówek</x-ui.dropdown-item>
                        @if ($isInventory)
                            <x-ui.dropdown-item icon="list-plus" wire:click="prefill">Wczytaj brakujące stany</x-ui.dropdown-item>
                        @endif
                    @endif
                    <x-ui.dropdown-item icon="printer" :href="route('documents.pdf', $doc)" target="_blank" :navigate="false">Podgląd wydruku</x-ui.dropdown-item>
                    @can('cancel-document', $doc)
                        <div class="my-1 border-t border-line"></div>
                        <x-ui.dropdown-item icon="trash-2" danger x-on:click="$wire.confirmDelete = true">Usuń szkic</x-ui.dropdown-item>
                    @endcan
                </x-ui.dropdown>

                @if ($lock)
                    <x-ui.tooltip text="Magazyn jest w trakcie inwentaryzacji – zatwierdzenie będzie możliwe po jej zakończeniu." position="bottom">
                        <x-ui.button variant="primary" icon="lock" disabled>Zatwierdź</x-ui.button>
                    </x-ui.tooltip>
                @elsecan('approve-document', $doc)
                    <x-ui.button variant="primary" icon="check" x-on:click="$wire.confirmApprove = true" :disabled="$doc->items->isEmpty() && ! $isInventory">Zatwierdź</x-ui.button>
                @else
                    <x-ui.tooltip :text="$isInventory ? 'Inwentaryzację zatwierdza kierownik magazynu – przekaż mu arkusz.' : 'Twoja rola nie ma uprawnienia do zatwierdzenia tego dokumentu.'" position="bottom">
                        <x-ui.button variant="primary" icon="lock" disabled>Zatwierdź</x-ui.button>
                    </x-ui.tooltip>
                @endif
            @else
                <x-ui.button :href="route('documents.pdf', $doc)" :navigate="false" target="_blank" icon="printer">PDF</x-ui.button>
                {{-- Storno: tylko dokumenty operacyjne (INW i dokumenty z niej wygenerowane rozliczają różnice spisu). --}}
                @if ($doc->isApproved() && ! $isInventory && ! ($doc->parent && $doc->parent->type === DocumentType::INW))
                    @can('cancel-document', $doc)
                        <x-ui.dropdown>
                            <x-slot:trigger>
                                <x-ui.button icon-only icon="ellipsis" aria-label="Więcej akcji" />
                            </x-slot:trigger>
                            <x-ui.dropdown-item icon="undo-2" danger x-on:click="$wire.confirmCancel = true">Storno dokumentu…</x-ui.dropdown-item>
                        </x-ui.dropdown>
                    @endcan
                @endif
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Nagłówek dokumentu --}}
    <section class="card mb-3 grid gap-x-6 gap-y-4 p-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Dane dokumentu">
        <div>
            <div class="text-xs font-medium text-ink-4">{{ $type->requiresTargetWarehouse() ? 'Magazyn źródłowy → docelowy' : 'Magazyn' }}</div>
            <div class="mt-0.5 font-medium text-ink">
                <x-ui.warehouse :warehouse="$doc->warehouse" name class="text-[0.8125rem]" />
                @if ($doc->targetWarehouse)
                    <span class="text-ink-4">→</span> <x-ui.warehouse :warehouse="$doc->targetWarehouse" name class="text-[0.8125rem]" />
                @endif
            </div>
        </div>
        @if ($type->requiresContractor())
            <div>
                <div class="text-xs font-medium text-ink-4">{{ $type->contractorLabel() }}</div>
                @if ($doc->contractor)
                    <div class="mt-0.5 font-medium text-ink">{{ $doc->contractor->name }}</div>
                    <div class="text-[0.8125rem] text-ink-3">@if ($doc->contractor->nip) NIP {{ $doc->contractor->formattedNip() }} @endif</div>
                @else
                    <div class="mt-0.5 text-warn">Nie wybrano</div>
                @endif
            </div>
        @endif
        <div>
            <div class="text-xs font-medium text-ink-4">Data dokumentu</div>
            <div class="mt-0.5 font-medium text-ink num text-left">{{ $doc->issued_at->format('d.m.Y') }}</div>
            @if ($doc->external_number)<div class="text-[0.8125rem] text-ink-3">Numer obcy: {{ $doc->external_number }}</div>@endif
        </div>
        <div>
            <div class="text-xs font-medium text-ink-4">Historia</div>
            <div class="mt-0.5 text-[0.8125rem] text-ink-2">Utworzył {{ $doc->author->name }}, {{ $doc->created_at->format('d.m.Y H:i') }}</div>
            @if ($doc->approver)<div class="text-[0.8125rem] text-ink-2">Zatwierdził {{ $doc->approver->name }}, {{ $doc->approved_at->format('d.m.Y H:i') }}</div>@endif
            @if ($doc->canceller)<div class="text-[0.8125rem] text-danger">Anulował {{ $doc->canceller->name }}, {{ $doc->cancelled_at->format('d.m.Y H:i') }}</div>@endif
        </div>
        @if ($doc->notes)
            <div class="sm:col-span-2 lg:col-span-4">
                <div class="text-xs font-medium text-ink-4">Uwagi</div>
                <p class="mt-0.5 text-pretty whitespace-pre-line text-ink-2">{{ $doc->notes }}</p>
            </div>
        @endif
        @if ($doc->parent || $doc->children->isNotEmpty())
            <div class="flex flex-wrap items-center gap-2 sm:col-span-2 lg:col-span-4">
                <span class="text-xs font-medium text-ink-4">Powiązane:</span>
                @if ($doc->parent)
                    <a href="{{ route('documents.show', $doc->parent) }}" wire:navigate class="badge border border-line-strong font-mono text-ink-2 hover:text-ink">{{ $doc->parent->displayNumber() }}</a>
                @endif
                @foreach ($doc->children as $child)
                    <a href="{{ route('documents.show', $child) }}" wire:navigate class="badge border border-line-strong font-mono text-ink-2 hover:text-ink">{{ $child->displayNumber() }}</a>
                @endforeach
            </div>
        @endif
    </section>

    @if ($lock)
        <div class="mb-3 flex items-start gap-3 rounded-xl border border-warn/30 bg-warn-soft p-3 text-[0.8125rem] text-ink-2" role="status">
            <x-lucide-lock class="mt-0.5 size-4 shrink-0 text-warn" aria-hidden="true" />
            <p>
                <span class="font-medium text-ink">Magazyn {{ $lock->warehouse->code }} jest w trakcie inwentaryzacji.</span>
                Szkic możesz uzupełniać, ale zatwierdzisz go dopiero po zakończeniu spisu
                <a href="{{ route('documents.show', $lock) }}" wire:navigate class="font-medium text-ink underline underline-offset-2">{{ $lock->displayNumber() }}</a>.
            </p>
        </div>
    @endif

    @if ($canEdit)
        {{-- Wprowadzanie pozycji: czytnik USB / wyszukiwarka / telefon --}}
        <section class="card mb-3 grid gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_17rem]" aria-label="Dodawanie pozycji">
            <form wire:submit="scan">
                <label for="scan-code" class="label">Czytnik kodów</label>
                <div class="relative">
                    <x-lucide-scan-barcode class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-4" aria-hidden="true" />
                    <input id="scan-code" type="text" wire:model="code" class="control pl-9 font-mono" autocomplete="off" spellcheck="false" autofocus
                           placeholder="Zeskanuj lub wpisz kod i Enter…" enterkeyhint="done">
                </div>
                <p class="mt-1.5 text-xs text-ink-4">Czytnik USB działa jak klawiatura: QR etykiety, indeks lub EAN.</p>
                @if ($unknownCode)
                    <p class="mt-1.5 text-xs text-ink-3" role="status">
                        Kodu <span class="code">{{ $unknownCode }}</span> nie ma w kartotece.
                        @can('products.manage')
                            <a href="{{ route('products.create', ['ean' => $unknownCode]) }}" wire:navigate class="font-medium text-ink underline underline-offset-2">Dodaj towar z tym kodem</a>
                        @else
                            Poproś kierownika o dodanie towaru.
                        @endcan
                    </p>
                @endif
            </form>

            <div data-search class="relative">
                <label for="product-search" class="label">Dodaj towar z kartoteki</label>
                <x-ui.search id="product-search" wire:model.live.debounce.250ms="productSearch" placeholder="Nazwa, indeks lub EAN…"
                             x-on:keydown.enter.prevent="$el.closest('[data-search]').querySelector('[data-result]')?.click()" />
                @if ($searchResults->isNotEmpty())
                    <ul class="absolute inset-x-0 top-full z-20 mt-1 max-h-72 overflow-y-auto rounded-xl border border-line bg-surface p-1 shadow-pop" role="listbox">
                        @foreach ($searchResults as $product)
                            <li>
                                <button type="button" data-result wire:click="addProduct({{ $product->id }})" role="option"
                                        class="flex w-full items-center justify-between gap-3 rounded-lg px-2.5 py-2 text-left transition-colors hover:bg-surface-2 focus-visible:bg-surface-2">
                                    <span class="min-w-0">
                                        <span class="block truncate text-[0.8125rem] font-medium text-ink">{{ $product->name }}</span>
                                        <span class="code block text-xs text-ink-4">{{ $product->sku }}@if ($product->ean) · {{ $product->ean }}@endif</span>
                                    </span>
                                    <x-lucide-plus class="size-4 shrink-0 text-ink-4" aria-hidden="true" />
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @elseif (mb_strlen(trim($productSearch)) >= 2)
                    <div class="absolute inset-x-0 top-full z-20 mt-1 rounded-xl border border-line bg-surface p-3 text-[0.8125rem] text-ink-3 shadow-pop">Nie znaleziono towaru.</div>
                @endif
            </div>

            <div @class(['rounded-xl border p-3', 'border-accent-line bg-accent-soft' => $scannerAttached, 'border-dashed border-line-strong' => ! $scannerAttached])>
                @if ($scannerAttached)
                    @if ($pairedDevices > 0)
                        <div class="flex items-center gap-2 font-medium text-ink"><span class="live-dot"></span> Skaner telefonu podłączony</div>
                        <p class="mt-1 text-[0.8125rem] text-ink-3">Zeskanowane kody dopisują się tutaj na żywo. Ilość na skan ustawisz w telefonie.</p>
                    @else
                        <div class="flex items-center gap-2 font-medium text-ink"><x-lucide-smartphone class="size-4 text-warn" aria-hidden="true" /> Brak sparowanego telefonu</div>
                        <p class="mt-1 text-[0.8125rem] text-ink-3">Skany trafią do tego dokumentu, gdy sparujesz telefon.
                            <a href="{{ route('scanner.devices') }}" wire:navigate class="font-medium text-ink underline underline-offset-2">Sparuj telefon</a></p>
                    @endif
                @else
                    <div class="flex items-center gap-2 font-medium text-ink"><x-lucide-smartphone class="size-4 text-ink-3" aria-hidden="true" /> Skaner telefonu</div>
                    <p class="mt-1 text-[0.8125rem] text-ink-3">
                        @can('scanner.use')
                            Użyj „Podłącz skaner”, by skany z telefonu trafiały do tego dokumentu.
                            <a href="{{ route('scanner.devices') }}" wire:navigate class="font-medium text-ink underline underline-offset-2">Parowanie telefonu</a>
                        @else
                            Twoja rola nie ma dostępu do skanera mobilnego.
                        @endcan
                    </p>
                @endif
            </div>
        </section>

        @if ($shortages->isNotEmpty())
            <div class="mb-3 flex items-start gap-3 rounded-xl border border-warn/30 bg-warn-soft p-3 text-[0.8125rem] text-ink-2" role="status">
                <x-lucide-triangle-alert class="mt-0.5 size-4 shrink-0 text-warn" aria-hidden="true" />
                <p><span class="font-medium text-ink">Brak stanu dla {{ $shortages->count() }} poz.</span> Zatwierdzenie zostanie odrzucone, dopóki ilości przekraczają stan w magazynie {{ $doc->warehouse->code }}.</p>
            </div>
        @endif
    @endif

    {{-- Pozycje --}}
    <section class="card overflow-hidden" aria-label="Pozycje dokumentu">
        @if ($isInventory && $canEdit && $doc->items->isNotEmpty())
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-2.5 text-[0.8125rem]">
                <p class="text-ink-3">
                    @if ($uncounted)
                        Niepoliczone: <span class="num font-semibold text-ink">{{ $uncounted }}</span> z {{ $doc->items->count() }}.
                        Wpisz ilość, potwierdź zgodność <x-lucide-check class="inline size-3.5" aria-label="przyciskiem z ptaszkiem" /> lub wpisz brak <span class="code">0</span>.
                    @else
                        <x-lucide-circle-check class="mr-1 inline size-4 text-accent" aria-hidden="true" />Wszystkie pozycje policzone – @can('approve-document', $doc) możesz zatwierdzić spis. @else przekaż arkusz kierownikowi do zatwierdzenia. @endcan
                    @endif
                </p>
                @if ($uncounted)
                    <x-ui.button size="sm" icon="list-checks"
                        x-on:click="$confirm({{ Js::from(['title' => 'Potwierdzić pozostałe pozycje?', 'message' => 'Wszystkie niepoliczone pozycje ('.$uncounted.') otrzymają ilość równą stanowi ewidencyjnemu – różnica 0.', 'confirm' => 'Potwierdź zgodność', 'danger' => false]) }}).then(ok => ok && $wire.confirmRemainingAsBook())">
                        Pozostałe zgodne ze stanem
                    </x-ui.button>
                @endif
            </div>
        @endif

        @if ($doc->items->isEmpty())
            <x-ui.empty icon="scan-line" :title="$canEdit ? 'Zeskanuj pierwszy towar' : 'Dokument bez pozycji'"
                        :description="$canEdit ? 'Użyj czytnika, wyszukiwarki albo telefonu. Ponowny skan tego samego kodu zwiększa ilość.' : null" />
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="num w-10">Lp.</th>
                            <th>Towar</th>
                            @if ($isInventory)
                                <th class="num w-32">Stan ewid.</th>
                                <th class="num {{ $canEdit ? 'w-56' : 'w-32' }}">Policzono</th>
                                <th class="num w-28">Różnica</th>
                            @else
                                @if ($checksStock)<th class="num w-28">Dostępne</th>@endif
                                <th class="num w-32">Ilość</th>
                                <th class="w-14">J.m.</th>
                                @if ($hasPrices || ! $draft)<th class="num w-36">Cena netto</th>@endif
                                @if ($hasPrices || ! $draft)<th class="num w-32">Wartość</th>@endif
                            @endif
                            @if ($canEdit)<th class="w-12"><span class="sr-only">Akcje</span></th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($doc->items as $item)
                            @php
                                $short = $shortages->has($item->product_id);
                                $diff = $item->difference();
                                $unit = $item->product->unit;
                            @endphp
                            <tr wire:key="item-{{ $item->id }}"
                                @if ($flashItemId === $item->id) class="flash-row" style="animation-name: flash-{{ $flashNonce % 2 ? 'a' : 'b' }}" @endif>
                                <td class="num text-ink-4">{{ $loop->iteration }}</td>
                                <td class="min-w-56">
                                    <a href="{{ route('products.show', $item->product) }}" wire:navigate class="font-medium text-ink hover:underline">{{ $item->product->name }}</a>
                                    <div class="code text-xs text-ink-4">{{ $item->product->sku }}@if ($item->product->ean) · {{ $item->product->ean }}@endif</div>
                                    @if ($locs = $locations[$item->product_id] ?? null)
                                        <div class="mt-1 flex flex-wrap items-center gap-1" title="{{ $type->isIncoming() ? 'Gdzie odłożyć' : 'Skąd pobrać' }}">
                                            @foreach (array_slice($locs, 0, 3) as $loc)
                                                <a href="{{ route('layout.plan', ['magazyn' => $doc->warehouse_id, 'znajdz' => $item->product->sku]) }}" wire:navigate
                                                   class="badge h-5 gap-0.5 bg-surface-3 font-mono text-[0.6875rem] text-ink-2 hover:bg-line"><x-lucide-map-pin class="size-3" aria-hidden="true" />{{ $loc }}</a>
                                            @endforeach
                                            @if (count($locs) > 3)<span class="text-xs text-ink-4">+{{ count($locs) - 3 }}</span>@endif
                                        </div>
                                    @endif
                                </td>

                                @if ($isInventory)
                                    <td class="num text-ink-2">{{ Num::qty($item->expected_quantity ?? 0, $unit) }}</td>
                                    <td class="num">
                                        @if ($canEdit)
                                            <div class="flex items-center justify-end gap-1">
                                                <input type="text" inputmode="decimal" wire:model.live.blur="quantities.{{ $item->id }}" x-on:keydown.enter.prevent="$el.blur()"
                                                       class="control h-8 w-28 text-right tabular-nums" placeholder="nie liczono" aria-label="Policzono: {{ $item->product->name }}"
                                                       @error('quantities.'.$item->id) aria-invalid="true" @enderror>
                                                <x-ui.tooltip text="Zgodne ze stanem ewidencyjnym">
                                                    <button type="button" wire:click="setCounted({{ $item->id }}, 'book')" class="btn btn-ghost btn-icon btn-sm" aria-label="Zgodne ze stanem: {{ $item->product->name }}">
                                                        <x-lucide-check aria-hidden="true" />
                                                    </button>
                                                </x-ui.tooltip>
                                                <x-ui.tooltip text="Brak towaru – policzono 0">
                                                    <button type="button" wire:click="setCounted({{ $item->id }}, 'zero')" class="btn btn-ghost btn-icon btn-sm font-mono text-[0.8125rem]" aria-label="Brak towaru: {{ $item->product->name }}">0</button>
                                                </x-ui.tooltip>
                                            </div>
                                        @elseif ($item->quantity === null)
                                            <x-ui.badge tone="outline">nie liczono</x-ui.badge>
                                        @else
                                            {{ Num::qty($item->quantity, $unit) }}
                                        @endif
                                    </td>
                                    <td class="num">
                                        @if ($diff === null)
                                            <span class="text-ink-4" aria-label="brak spisu">·</span>
                                        @elseif (bccomp($diff, '0', 3) === 0)
                                            <span class="text-ink-4">0</span>
                                        @else
                                            <span @class(['font-semibold', 'text-in' => bccomp($diff, '0', 3) > 0, 'text-out' => bccomp($diff, '0', 3) < 0])>{{ Num::signedQty($diff) }} {{ $unit->value }}</span>
                                        @endif
                                    </td>
                                @else
                                    @if ($checksStock)
                                        <td class="num">
                                            <span @class(['text-ink-3' => ! $short, 'font-medium text-warn' => $short])>{{ Num::qty($available[$item->product_id] ?? 0) }}</span>
                                        </td>
                                    @endif
                                    <td class="num">
                                        @if ($canEdit)
                                            <input type="text" inputmode="decimal" wire:model.live.blur="quantities.{{ $item->id }}" x-on:keydown.enter.prevent="$el.blur()"
                                                   class="control h-8 text-right tabular-nums" aria-label="Ilość: {{ $item->product->name }}"
                                                   @if ($short || $errors->has('quantities.'.$item->id)) aria-invalid="true" @endif>
                                        @else
                                            {{ Num::qty($item->quantity) }}
                                        @endif
                                    </td>
                                    <td class="text-ink-3">{{ $unit->value }}</td>
                                    @if ($hasPrices || ! $draft)
                                        <td class="num">
                                            @if ($canEdit)
                                                <div class="relative">
                                                    <input type="text" inputmode="decimal" wire:model.live.blur="prices.{{ $item->id }}" x-on:keydown.enter.prevent="$el.blur()"
                                                           class="control h-8 pr-7 text-right tabular-nums" placeholder="0,00" aria-label="Cena: {{ $item->product->name }}" @error('prices.'.$item->id) aria-invalid="true" title="{{ $message }}" @enderror>
                                                    <span class="pointer-events-none absolute inset-y-0 right-2.5 flex items-center text-xs text-ink-4" aria-hidden="true">zł</span>
                                                </div>
                                            @else
                                                {{ $item->unit_price !== null ? Num::money($item->unit_price) : '—' }}
                                            @endif
                                        </td>
                                        <td class="num font-medium">{{ Num::money($item->lineValue()) }}</td>
                                    @endif
                                @endif

                                @if ($canEdit)
                                    <td class="text-right">
                                        <button type="button" wire:click="removeItem({{ $item->id }})" class="btn btn-ghost btn-icon btn-sm text-ink-4 hover:text-danger" aria-label="Usuń pozycję {{ $item->product->name }}">
                                            <x-lucide-trash-2 aria-hidden="true" />
                                        </button>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="99" class="border-t border-line bg-surface-2/50 px-3 py-2.5">
                                <div class="flex flex-wrap items-center justify-between gap-2 text-[0.8125rem]">
                                    <span class="text-ink-3">Pozycji: <span class="num font-medium text-ink">{{ $doc->items->count() }}</span>
                                        @if ($isInventory) · policzono <span class="num font-medium text-ink">{{ $doc->items->whereNotNull('quantity')->count() }}</span>@endif
                                    </span>
                                    @if ($showValue && ($hasPrices || ! $draft))
                                        <span class="text-ink-3">Wartość netto: <span class="num text-base font-semibold text-ink">{{ Num::money($draft ? $draftValue : $doc->total_value) }}</span></span>
                                    @elseif ($isInventory && ! $draft)
                                        <span class="text-ink-3">Różnice inwentaryzacyjne (netto): <span class="num text-base font-semibold text-ink">{{ Num::money($doc->total_value) }}</span></span>
                                    @elseif (! $hasPrices && $draft && ! $isInventory)
                                        <span class="text-ink-4">Wycena po cenie średniej ważonej przy zatwierdzeniu</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </section>

    {{-- Kartoteka ruchów (zatwierdzony / storno) --}}
    @if ($movements->isNotEmpty())
        <details class="card mt-3 overflow-hidden">
            <summary class="cursor-pointer px-4 py-3 font-medium text-ink hover:bg-surface-2">Ruchy magazynowe ({{ $movements->count() }})</summary>
            <div class="overflow-x-auto border-t border-line">
                <table class="table">
                    <thead><tr><th>Towar</th><th>Mag.</th><th class="num">Ilość</th><th class="num">Cena</th><th class="num">Stan po</th><th>Rodzaj</th></tr></thead>
                    <tbody>
                        @foreach ($movements as $m)
                            <tr>
                                <td>{{ $m->product->name }} <span class="code text-xs text-ink-4">{{ $m->product->sku }}</span></td>
                                <td><x-ui.warehouse :warehouse="$m->warehouse" /></td>
                                <td @class(['num font-medium', 'text-in' => $m->quantity > 0, 'text-out' => $m->quantity < 0])>{{ Num::signedQty($m->quantity) }}</td>
                                <td class="num">{{ Num::money($m->unit_price) }}</td>
                                <td class="num text-ink-3">{{ Num::qty($m->balance_quantity, $m->product->unit) }}</td>
                                <td>@if ($m->is_reversal)<x-ui.badge tone="danger">storno</x-ui.badge>@else<x-ui.badge>ruch</x-ui.badge>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif

    {{-- Modale --}}
    <x-ui.modal wire:model="confirmApprove" title="Zatwierdzić dokument?" :description="$isInventory ? 'Różnice spisu zostaną rozliczone dokumentami PW (nadwyżki) i RW (niedobory).' : 'Stany magazynowe zostaną zaktualizowane, a dokument otrzyma numer.'" max-width="md">
        <dl class="grid grid-cols-2 gap-3 text-[0.8125rem]">
            <div><dt class="text-ink-4">Typ</dt><dd class="font-medium text-ink">{{ $type->value }} · {{ $type->label() }}</dd></div>
            <div><dt class="text-ink-4">Pozycji</dt><dd class="num text-left font-medium text-ink">{{ $doc->items->count() }}</dd></div>
            @if ($hasPrices)
                <div><dt class="text-ink-4">Wartość netto</dt><dd class="num text-left font-medium text-ink">{{ Num::money($draftValue) }}</dd></div>
                @php $withoutPrice = $doc->items->filter(fn ($i) => $i->unit_price === null || (float) $i->unit_price == 0)->count(); @endphp
                @if ($withoutPrice)
                    <div class="col-span-2 rounded-lg bg-warn-soft p-2.5 text-ink-2">
                        {{ Num::plural($withoutPrice, 'pozycja nie ma', 'pozycje nie mają', 'pozycji nie ma') }} ceny – zostanie przyjęta cena domyślna towaru (lub 0 zł).
                    </div>
                @endif
            @endif
        </dl>
        @if ($shortages->isNotEmpty())
            {{-- Brak towaru: zamiast zatwierdzenia skazanego na odrzucenie pokazujemy, czego brakuje, a przycisk jest nieaktywny. --}}
            <div class="mt-3 rounded-lg bg-danger-soft p-3 text-[0.8125rem] text-ink-2" role="alert">
                <p class="font-medium text-ink">Nie można zatwierdzić: w magazynie {{ $doc->warehouse->code }} brakuje towaru.</p>
                <ul class="mt-1.5 space-y-1">
                    @foreach ($shortages as $item)
                        <li><span class="font-medium text-ink">{{ $item->product->name }}</span>: dostępne {{ Num::qty($available[$item->product_id] ?? '0', $item->product->unit) }}, w dokumencie {{ Num::qty($item->quantity, $item->product->unit) }}</li>
                    @endforeach
                </ul>
                <p class="mt-1.5">Zmniejsz ilość w dokumencie albo najpierw przyjmij brakujący towar.</p>
            </div>
        @endif
        <p class="mt-3 text-[0.8125rem] text-ink-3">Zatwierdzonego dokumentu nie można edytować – korekta tylko przez {{ auth()->user()->can('documents.cancel') ? 'storno.' : 'storno (wykonuje je kierownik).' }}</p>
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="$wire.confirmApprove = false">Wróć</x-ui.button>
            <x-ui.button variant="primary" icon="check" wire:click="approve" :disabled="$shortages->isNotEmpty()">Zatwierdź</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.modal wire:model="confirmCancel" title="Wykonać storno?" description="Zostaną zapisane ruchy odwrotne po cenach pierwotnych. Operacja jest nieodwracalna." max-width="md">
        <p class="text-[0.8125rem] text-ink-2">Storno zostanie odrzucone, jeśli towar przyjęty tym dokumentem został już wydany.</p>
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="$wire.confirmCancel = false">Wróć</x-ui.button>
            <x-ui.button variant="danger" icon="undo-2" wire:click="cancelDocument">Wykonaj storno</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.modal wire:model="confirmDelete" title="Usunąć szkic?" description="Szkic i jego pozycje zostaną usunięte. Stany magazynowe się nie zmienią." max-width="md">
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="$wire.confirmDelete = false">Wróć</x-ui.button>
            <x-ui.button variant="danger" icon="trash-2" wire:click="deleteDraft">Usuń szkic</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    @if ($draft)
        <x-ui.modal wire:model="editHeader" title="Nagłówek dokumentu" max-width="lg">
            <form wire:submit="saveHeader" id="header-form" class="grid gap-4 sm:grid-cols-2">
                <x-ui.field :label="$type->requiresTargetWarehouse() ? 'Magazyn źródłowy' : 'Magazyn'" for="h-warehouse" error="header.warehouse_id" required
                            :hint="$isInventory ? 'Magazynu spisu nie można zmienić.' : null">
                    @if ($isInventory)
                        <div class="control flex items-center bg-surface-2 text-ink-3">{{ $doc->warehouse->code }} · {{ $doc->warehouse->name }}</div>
                    @else
                        <x-ui.select id="h-warehouse" wire:model="header.warehouse_id" :options="$warehouseOptions" nullable />
                    @endif
                </x-ui.field>
                @if ($type->requiresTargetWarehouse())
                    <x-ui.field label="Magazyn docelowy" for="h-target" error="header.target_warehouse_id" required>
                        <x-ui.select id="h-target" wire:model="header.target_warehouse_id" :options="$warehouseOptions" nullable />
                    </x-ui.field>
                @endif
                @if ($type->requiresContractor())
                    <x-ui.field :label="$type->contractorLabel()" for="h-contractor" error="header.contractor_id" required class="sm:col-span-2">
                        <x-ui.select id="h-contractor" wire:model="header.contractor_id" :options="$contractorOptions" nullable searchable />
                    </x-ui.field>
                @endif
                <x-ui.field label="Data dokumentu" for="h-date" error="header.issued_at" required>
                    <input id="h-date" type="date" wire:model="header.issued_at" class="control">
                </x-ui.field>
                @unless ($isInventory)
                    <x-ui.field label="Numer obcy (faktura, WZ dostawcy)" for="h-ext" error="header.external_number">
                        <input id="h-ext" type="text" wire:model="header.external_number" class="control" autocomplete="off">
                    </x-ui.field>
                @endunless
                <x-ui.field label="Uwagi" for="h-notes" error="header.notes" class="sm:col-span-2">
                    <textarea id="h-notes" wire:model="header.notes" rows="2" class="control"></textarea>
                </x-ui.field>
            </form>
            <x-slot:footer>
                <x-ui.button variant="ghost" x-on:click="$wire.editHeader = false">Anuluj</x-ui.button>
                <x-ui.button type="submit" form="header-form" variant="primary">Zapisz</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
