@props(['text', 'position' => 'top', 'focusable' => false])

{{-- Podpowiedź: opóźnienie 300 ms przy najechaniu, natychmiast przy fokusie z klawiatury. --}}
<span
    x-data="{ open: false, timer: null }"
    x-on:mouseenter="timer = setTimeout(() => open = true, 300)"
    x-on:mouseleave="clearTimeout(timer); open = false"
    x-on:focusin="open = true"
    x-on:focusout="open = false"
    x-on:keydown.escape="open = false"
    {{ $attributes->class('inline-flex') }}
>
    {{-- focusable: dla treści nieinteraktywnej (ikona, nagłówek); przycisk w środku sam przyjmuje fokus. --}}
    <span x-ref="trigger" @if ($focusable) tabindex="0" @endif @class(['inline-flex items-center gap-1 rounded', 'cursor-help' => $focusable]) :aria-describedby="$id('tip')">
        {{ $slot }}
    </span>
    <span
        x-show="open"
        x-cloak
        x-anchor.{{ $position }}.fixed.offset.6="$refs.trigger"
        :id="$id('tip')"
        role="tooltip"
        x-transition:enter="transition duration-[125ms] ease-out"
        x-transition:enter-start="opacity-0 scale-[0.97]"
        x-transition:leave="transition duration-75 ease-out"
        x-transition:leave-end="opacity-0"
        class="pointer-events-none z-[80] w-max max-w-64 origin-bottom rounded-lg bg-ink px-2.5 py-1.5 text-left text-xs leading-snug font-normal tracking-normal text-pretty whitespace-normal text-canvas normal-case shadow-pop"
    >{{ $text }}</span>
</span>
