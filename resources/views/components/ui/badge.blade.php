@props(['tone' => 'neutral', 'icon' => null])

@php
    $tones = [
        'neutral' => 'bg-surface-3 text-ink-2',
        'success' => 'bg-accent-soft text-accent',
        'danger' => 'bg-danger-soft text-danger',
        'warn' => 'bg-warn-soft text-warn',
        'in' => 'bg-in-soft text-in',
        'out' => 'bg-out-soft text-out',
        'transfer' => 'bg-transfer-soft text-transfer',
        'inventory' => 'bg-inventory-soft text-inventory',
        'outline' => 'border border-line-strong text-ink-3',
    ];
@endphp

<span {{ $attributes->class(['badge', $tones[$tone] ?? $tones['neutral']]) }}>
    @if ($icon)<x-dynamic-component :component="'lucide-'.$icon" class="size-3.5" aria-hidden="true" />@endif
    {{ $slot }}
</span>
