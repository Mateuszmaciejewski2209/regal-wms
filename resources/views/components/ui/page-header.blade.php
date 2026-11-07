@props(['title', 'description' => null, 'back' => null, 'backLabel' => 'Wróć'])

<header {{ $attributes->class('mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between') }}>
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" wire:navigate class="mb-2 inline-flex items-center gap-1 text-[0.8125rem] font-medium text-ink-3 transition-colors hover:text-ink">
                <x-lucide-arrow-left class="size-3.5" aria-hidden="true" />
                {{ $backLabel }}
            </a>
        @endif
        <h1 class="text-xl font-semibold tracking-tight text-balance text-ink">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 text-pretty text-ink-3">{{ $description }}</p>
        @endif
        {{ $meta ?? '' }}
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</header>
