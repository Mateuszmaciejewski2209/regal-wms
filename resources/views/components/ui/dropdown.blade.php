@props(['align' => 'bottom-end', 'width' => 'w-56'])

{{-- Menu rozwijane: pozycjonowane względem przycisku (x-anchor), skaluje się od strony wyzwalacza. --}}
<div x-data="{ open: false }" x-on:keydown.escape="open = false" {{ $attributes->class('relative') }}>
    <div x-ref="trigger" x-on:click="open = ! open">{{ $trigger }}</div>

    <div
        x-show="open"
        x-cloak
        x-anchor.{{ $align }}.fixed.offset.6="$refs.trigger"
        x-on:click.outside="open = false"
        x-on:click="if ($event.target.closest('a,button')) open = false"
        x-transition:enter="transition duration-150 ease-[cubic-bezier(0.23,1,0.32,1)]"
        x-transition:enter-start="opacity-0 scale-[0.97]"
        x-transition:leave="transition duration-100 ease-out"
        x-transition:leave-end="opacity-0"
        @class(['z-[70] rounded-xl border border-line bg-surface p-1 shadow-pop', $width, 'origin-top-right' => str_ends_with($align, 'end'), 'origin-top-left' => str_ends_with($align, 'start')])
        role="menu"
    >
        {{ $slot }}
    </div>
</div>
