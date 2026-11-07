@php use App\Support\Num; @endphp

{{-- Szybkie szukanie towaru: przycisk w pasku górnym + okno (Ctrl+K, /). Bez animacji – używane wiele razy dziennie.
     Okno jest przenoszone do <body>: nagłówek ma backdrop-blur, który przycinałby „fixed” tło do wysokości paska. --}}
<div x-data="{ open: false }"
     x-on:keydown.window.ctrl.k.prevent="open = true"
     x-on:keydown.window.meta.k.prevent="open = true"
     x-on:keydown.window.slash="if (! ['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName) && ! document.activeElement.isContentEditable) { $event.preventDefault(); open = true }"
     x-on:keydown.escape.window="open = false">
    <button type="button" x-on:click="open = true"
            class="flex h-9 items-center gap-2 rounded-lg border border-line-strong bg-surface px-2.5 text-[0.8125rem] text-ink-3 transition-colors hover:border-ink-4 sm:w-64"
            aria-label="Szukaj towaru (Ctrl+K)" aria-keyshortcuts="Control+K">
        <x-lucide-search class="size-4 shrink-0" aria-hidden="true" />
        <span class="hidden flex-1 text-left sm:inline">Gdzie leży towar?</span>
        <span class="kbd hidden sm:inline-flex">Ctrl+K</span>
    </button>

    <template x-teleport="body">
    <div x-show="open" x-cloak class="fixed inset-0 z-[80] flex items-start justify-center bg-black/40 px-4 pt-[12vh]" x-on:click.self="open = false" role="dialog" aria-modal="true" aria-label="Szukaj towaru">
        <div class="w-full max-w-xl overflow-hidden rounded-2xl border border-line bg-surface shadow-pop" x-trap.noscroll="open">
            <div class="relative border-b border-line">
                <x-lucide-search class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-ink-4" aria-hidden="true" />
                <input type="search" wire:model.live.debounce.200ms="query" autocomplete="off" spellcheck="false"
                       class="h-14 w-full bg-transparent pr-4 pl-12 text-base text-ink outline-none placeholder:text-ink-4"
                       placeholder="Nazwa, indeks lub EAN…" aria-label="Nazwa, indeks lub EAN"
                       x-init="$watch('open', (value) => value && $nextTick(() => { $el.focus(); $el.select() }))"
                       x-on:keydown.enter.prevent="$el.closest('[role=dialog]').querySelector('[data-hit]')?.click()">
            </div>

            <div class="max-h-[60vh] overflow-y-auto">
                @if (mb_strlen(trim($query)) < 2)
                    <p class="px-4 py-6 text-center text-[0.8125rem] text-ink-3">Wpisz min. 2 znaki – pokażę stan i półki towaru.</p>
                @elseif ($products->isEmpty())
                    <p class="px-4 py-6 text-center text-[0.8125rem] text-ink-3">Nie znaleziono towaru „{{ $query }}”.</p>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($products as $p)
                            <li>
                                <a href="{{ route('products.show', $p) }}" wire:navigate data-hit x-on:click="open = false"
                                   class="block px-4 py-3 transition-colors hover:bg-surface-2 focus-visible:bg-surface-2 focus-visible:outline-none">
                                    <div class="flex items-baseline justify-between gap-3">
                                        <span class="truncate font-medium text-ink">{{ $p->name }}</span>
                                        <span class="num shrink-0 font-semibold text-ink">{{ Num::qty($p->stocks->sum('quantity'), $p->unit) }}</span>
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-ink-3">
                                        <span class="code">{{ $p->sku }}</span>
                                        @foreach ($p->stocks->where('quantity', '!=', 0) as $s)
                                            <x-ui.warehouse :warehouse="$s->warehouse" :qty="Num::qty($s->quantity)" />
                                        @endforeach
                                        @foreach ($p->locations->take(4) as $loc)
                                            <span class="wh-badge wh-{{ $loc->rack->warehouse->colorKey() }} font-mono" title="{{ $loc->rack->warehouse->code }} · {{ $loc->rack->warehouse->name }}"><x-lucide-map-pin class="size-3 text-[var(--wh)]" aria-hidden="true" />{{ $codeOf($loc) }}</span>
                                        @endforeach
                                        @if ($p->locations->isEmpty())<span class="text-ink-4">brak przypisanej półki</span>@endif
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div class="flex items-center justify-between border-t border-line bg-surface-2/50 px-4 py-2 text-xs text-ink-4">
                <span><span class="kbd">Enter</span> otwórz kartę towaru</span>
                <span><span class="kbd">Esc</span> zamknij</span>
            </div>
        </div>
    </div>
    </template>
</div>
