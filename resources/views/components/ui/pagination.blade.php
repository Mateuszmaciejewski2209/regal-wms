@props(['paginator', 'perPageOptions' => [10, 15, 25, 50, 100]])

{{-- Stopka tabeli: liczba rekordów na stronie, zakres i numery stron (zawsze widoczna). --}}
@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $paginator */
    $page = $paginator->currentPage();
    $last = $paginator->lastPage();
    $name = $paginator->getPageName();
    $window = collect([1, $last, $page - 1, $page, $page + 1])->filter(fn ($p) => $p >= 1 && $p <= $last)->unique()->sort()->values();
@endphp

<div {{ $attributes->class('flex flex-wrap items-center justify-between gap-3 border-t border-line px-4 py-2.5 text-[0.8125rem] text-ink-3') }}>
    <div class="flex items-center gap-2">
        <span id="per-page-label">Na stronie</span>
        <x-ui.select wire:model.live="perPage" size="sm" class="w-20" label="Liczba rekordów na stronie"
                     :options="collect($perPageOptions)->mapWithKeys(fn ($n) => [$n => (string) $n])->all()" />
        <span class="num text-left">
            @if ($paginator->total())
                {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} z {{ $paginator->total() }}
            @else
                0 z 0
            @endif
        </span>
    </div>

    @if ($last > 1)
        <nav class="flex items-center gap-1" aria-label="Strony">
            <button type="button" class="btn btn-ghost btn-icon btn-sm" wire:click="previousPage('{{ $name }}')" @disabled($page <= 1) aria-label="Poprzednia strona">
                <x-lucide-chevron-left aria-hidden="true" />
            </button>
            @foreach ($window as $i => $p)
                @if ($i > 0 && $p - $window[$i - 1] > 1)
                    <span class="px-1 text-ink-4" aria-hidden="true">…</span>
                @endif
                <button type="button" wire:click="gotoPage({{ $p }}, '{{ $name }}')"
                        @class(['btn btn-sm min-w-8 px-2 tabular-nums', 'btn-secondary text-ink' => $p === $page, 'btn-ghost' => $p !== $page])
                        @if ($p === $page) aria-current="page" @endif aria-label="Strona {{ $p }}">{{ $p }}</button>
            @endforeach
            <button type="button" class="btn btn-ghost btn-icon btn-sm" wire:click="nextPage('{{ $name }}')" @disabled($page >= $last) aria-label="Następna strona">
                <x-lucide-chevron-right aria-hidden="true" />
            </button>
        </nav>
    @endif
</div>
