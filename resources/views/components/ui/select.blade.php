@props([
    'options' => [],
    'placeholder' => 'Wybierz…',
    'searchable' => null,
    'nullable' => false,
    'size' => 'md',
    'id' => null,
    'invalid' => false,
    'label' => null,
])

{{--
    Własna lista wyboru (zamiast natywnego <select>): spójny wygląd w obu motywach,
    obsługa klawiatury (strzałki, Enter, Escape), wyszukiwanie przy dłuższych listach.
    Działa z wire:model dzięki x-modelable.
--}}
@php
    $items = collect($options)->map(fn ($text, $value) => ['value' => (string) $value, 'label' => (string) $text])->values();
    $searchable ??= $items->count() > 8;
@endphp

<div
    x-data="selectBox(@js($items), @js($nullable))"
    x-modelable="value"
    {{ $attributes->whereStartsWith('wire:model') }}
    {{ $attributes->whereDoesntStartWith('wire:model')->class('relative') }}
    x-on:keydown.escape.prevent.stop="close(true)"
>
    <button
        type="button"
        x-ref="button"
        @if ($id) id="{{ $id }}" @endif
        @class(['control flex items-center justify-between gap-2 text-left', 'h-8 px-2.5 text-[0.8125rem]' => $size === 'sm'])
        aria-haspopup="listbox"
        :aria-expanded="open"
        @if ($label) aria-label="{{ $label }}" @endif
        @if ($invalid) aria-invalid="true" @endif
        x-on:click="toggle()"
        x-on:keydown.arrow-down.prevent="openList()"
        x-on:keydown.arrow-up.prevent="openList()"
    >
        <span class="truncate" :class="selected ? 'text-ink' : 'text-ink-4'" x-text="selected ? selected.label : @js($placeholder)">{{ $placeholder }}</span>
        <x-lucide-chevron-down class="size-4 shrink-0 text-ink-4 transition-transform duration-150" ::class="open && 'rotate-180'" aria-hidden="true" />
    </button>

    <div
        x-show="open"
        x-cloak
        x-anchor.bottom-start.fixed.offset.4="$refs.button"
        x-on:click.outside="close()"
        x-transition:enter="transition duration-150 ease-[cubic-bezier(0.23,1,0.32,1)]"
        x-transition:enter-start="opacity-0 scale-[0.98] -translate-y-0.5"
        x-transition:leave="transition duration-100 ease-out"
        x-transition:leave-end="opacity-0"
        class="z-[70] origin-top rounded-xl border border-line bg-surface p-1 shadow-pop"
        :style="`min-width: ${$refs.button.offsetWidth}px`"
    >
        @if ($searchable)
            <div class="relative mb-1">
                <x-lucide-search class="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-ink-4" aria-hidden="true" />
                <input type="text" x-ref="search" x-model="query" class="control h-8 pl-8 text-[0.8125rem]" placeholder="Szukaj…" aria-label="Szukaj na liście"
                       autocomplete="off" spellcheck="false"
                       x-on:keydown.arrow-down.prevent="move(1)" x-on:keydown.arrow-up.prevent="move(-1)"
                       x-on:keydown.enter.prevent="chooseActive()" x-on:keydown.tab="close()">
            </div>
        @endif

        <ul x-ref="list" role="listbox" tabindex="-1" class="max-h-64 overflow-y-auto overscroll-contain outline-none"
            x-on:keydown.arrow-down.prevent="move(1)" x-on:keydown.arrow-up.prevent="move(-1)"
            x-on:keydown.enter.prevent="chooseActive()" x-on:keydown.space.prevent="chooseActive()" x-on:keydown.tab="close()">
            <template x-for="(item, index) in filtered" :key="item.value">
                <li role="option" :id="$id('opt') + '-' + index" :aria-selected="isSelected(item)"
                    x-on:click="choose(item)" x-on:mousemove="active = index"
                    class="flex items-center justify-between gap-3 rounded-lg px-2.5 py-1.5 text-[0.8125rem]"
                    :class="active === index ? 'bg-surface-2 text-ink' : 'text-ink-2'">
                    <span class="truncate" x-text="item.label"></span>
                    <x-lucide-check class="size-4 shrink-0 text-ink" x-show="isSelected(item)" aria-hidden="true" />
                </li>
            </template>
            <li class="px-2.5 py-2 text-[0.8125rem] text-ink-4" x-show="! filtered.length">Brak wyników</li>
        </ul>
    </div>
</div>
