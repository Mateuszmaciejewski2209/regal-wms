@props(['title' => null, 'description' => null, 'maxWidth' => 'lg'])

@php
    $model = $attributes->wire('model')->value();
    $widths = ['sm' => 'sm:max-w-sm', 'md' => 'sm:max-w-md', 'lg' => 'sm:max-w-lg', 'xl' => 'sm:max-w-xl', '2xl' => 'sm:max-w-2xl'];
@endphp

{{-- Modal: wyśrodkowany (desktop) lub arkusz od dołu (telefon). Pułapka fokusu + Escape. --}}
<div
    x-data="{ open: $wire.entangle('{{ $model }}') }"
    x-show="open"
    x-cloak
    x-on:keydown.escape.window="open = false"
    class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-6"
    role="dialog"
    aria-modal="true"
    @if ($title) aria-label="{{ $title }}" @endif
>
    <div
        x-show="open"
        x-transition:enter="transition-opacity duration-200 ease-out"
        x-transition:enter-start="opacity-0"
        x-transition:leave="transition-opacity duration-150 ease-out"
        x-transition:leave-end="opacity-0"
        class="absolute inset-0 bg-black/45"
        x-on:click="open = false"
    ></div>

    <div
        x-show="open"
        x-trap.noscroll="open"
        x-transition:enter="transition duration-200 ease-[cubic-bezier(0.23,1,0.32,1)]"
        x-transition:enter-start="opacity-0 translate-y-3 sm:translate-y-0 sm:scale-[0.97]"
        x-transition:leave="transition duration-150 ease-out"
        x-transition:leave-end="opacity-0 sm:scale-[0.98]"
        {{ $attributes->whereDoesntStartWith('wire:model')->class(['relative max-h-[92dvh] w-full overflow-y-auto overscroll-contain rounded-t-2xl border border-line bg-surface shadow-pop sm:rounded-2xl', $widths[$maxWidth] ?? $widths['lg']]) }}
    >
        @if ($title)
            <div class="flex items-start justify-between gap-4 border-b border-line px-5 py-4">
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-ink">{{ $title }}</h2>
                    @if ($description)<p class="mt-0.5 text-pretty text-[0.8125rem] text-ink-3">{{ $description }}</p>@endif
                </div>
                <button type="button" class="btn btn-ghost btn-icon btn-sm -mt-0.5 -mr-1.5" x-on:click="open = false" aria-label="Zamknij">
                    <x-lucide-x aria-hidden="true" />
                </button>
            </div>
        @endif

        <div class="px-5 py-4">{{ $slot }}</div>

        @isset($footer)
            <div class="flex flex-col-reverse gap-2 border-t border-line bg-surface-2/50 px-5 py-3 sm:flex-row sm:justify-end">{{ $footer }}</div>
        @endisset
    </div>
</div>
