@props(['placeholder' => 'Szukaj…', 'label' => null])

<div class="relative">
    <x-lucide-search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-4" aria-hidden="true" />
    <input type="search" autocomplete="off" spellcheck="false" placeholder="{{ $placeholder }}" aria-label="{{ $label ?? rtrim($placeholder, '…') }}" {{ $attributes->class('control pl-9') }}>
</div>
