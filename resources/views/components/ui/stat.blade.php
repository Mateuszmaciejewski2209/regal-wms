@props(['label', 'value', 'hint' => null, 'icon' => null, 'tone' => null, 'href' => null])

@php $tag = $href ? 'a' : 'div'; @endphp

<{{ $tag }} @if ($href) href="{{ $href }}" wire:navigate @endif {{ $attributes->class(['card relative flex flex-col gap-1 p-4', 'transition-colors hover:border-line-strong' => $href]) }}>
    <div class="flex items-center justify-between gap-2 text-[0.8125rem] font-medium text-ink-3">
        <span>{{ $label }}</span>
        @if ($icon)
            <x-dynamic-component :component="'lucide-'.$icon" @class(['size-4', 'text-ink-4' => ! $tone, 'text-warn' => $tone === 'warn', 'text-accent' => $tone === 'success', 'text-danger' => $tone === 'danger']) aria-hidden="true" />
        @endif
    </div>
    <div class="num text-left text-2xl font-semibold tracking-tight text-ink">{{ $value }}</div>
    @if ($hint)
        <div class="text-[0.8125rem] text-ink-4">{{ $hint }}</div>
    @endif
</{{ $tag }}>
