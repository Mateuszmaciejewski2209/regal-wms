@props(['icon' => 'package', 'title', 'description' => null])

<div {{ $attributes->class('flex flex-col items-center justify-center px-6 py-14 text-center') }}>
    <div class="mb-3 grid size-10 place-items-center rounded-xl border border-line bg-surface-2 text-ink-3">
        <x-dynamic-component :component="'lucide-'.$icon" class="size-5" aria-hidden="true" />
    </div>
    <p class="font-medium text-ink">{{ $title }}</p>
    @if ($description)
        <p class="mt-1 max-w-sm text-pretty text-ink-3">{{ $description }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-4 flex flex-wrap justify-center gap-2">{{ $slot }}</div>
    @endif
</div>
