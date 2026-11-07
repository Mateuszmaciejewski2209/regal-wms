@props(['href' => null, 'icon' => null, 'danger' => false, 'navigate' => true, 'type' => 'button'])

@php
    $classes = 'flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-[0.8125rem] font-medium transition-colors '
        .($danger ? 'text-danger hover:bg-danger-soft' : 'text-ink-2 hover:bg-surface-2 hover:text-ink');
@endphp

@if ($href)
    <a href="{{ $href }}" role="menuitem" @if ($navigate) wire:navigate @endif {{ $attributes->class($classes) }}>
        @if ($icon)<x-dynamic-component :component="'lucide-'.$icon" class="size-4 shrink-0 text-ink-4" aria-hidden="true" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" role="menuitem" {{ $attributes->class($classes) }}>
        @if ($icon)<x-dynamic-component :component="'lucide-'.$icon" class="size-4 shrink-0 text-ink-4" aria-hidden="true" />@endif
        {{ $slot }}
    </button>
@endif
