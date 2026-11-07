@props([
    'variant' => 'secondary',
    'size' => 'md',
    'href' => null,
    'icon' => null,
    'iconRight' => null,
    'type' => 'button',
    'iconOnly' => false,
    'navigate' => true,
])

@php
    $classes = 'btn btn-'.$variant
        .($size === 'sm' ? ' btn-sm' : '')
        .($iconOnly ? ' btn-icon' : '');
@endphp

@if ($href)
    <a href="{{ $href }}" @if ($navigate) wire:navigate @endif {{ $attributes->class($classes) }}>
        @if ($icon)<x-dynamic-component :component="'lucide-'.$icon" aria-hidden="true" />@endif
        {{ $slot }}
        @if ($iconRight)<x-dynamic-component :component="'lucide-'.$iconRight" aria-hidden="true" />@endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-dynamic-component :component="'lucide-'.$icon" aria-hidden="true" />@endif
        {{ $slot }}
        @if ($iconRight)<x-dynamic-component :component="'lucide-'.$iconRight" aria-hidden="true" />@endif
    </button>
@endif
