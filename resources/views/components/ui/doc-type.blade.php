@props(['type'])

@php
    /** @var \App\Enums\DocumentType $type */
    $icons = ['in' => 'arrow-down-left', 'out' => 'arrow-up-right', 'transfer' => 'arrow-left-right', 'inventory' => 'clipboard-check'];
@endphp

<x-ui.badge :tone="$type->tone()" :icon="$icons[$type->direction()]" title="{{ $type->label() }}" {{ $attributes->class('font-mono tracking-tight') }}>{{ $type->value }}</x-ui.badge>
