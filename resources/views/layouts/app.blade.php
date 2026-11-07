<!DOCTYPE html>
<html lang="pl" data-theme="light">
<head>
    @include('partials.head')
</head>
<body class="min-h-dvh" x-data="liveScanner({{ auth()->id() }})">
    <a href="#main" class="sr-only z-50 rounded-lg bg-primary px-3 py-2 text-on-primary focus:not-sr-only focus:fixed focus:top-3 focus:left-3">Przejdź do treści</a>

    @php
        $user = auth()->user();
        // Menu budowane z uprawnień roli (RBAC) – pozycje bez dostępu nie są pokazywane.
        $nav = collect([
            ['label' => null, 'items' => [
                ['route' => 'dashboard', 'match' => 'dashboard', 'icon' => 'layout-dashboard', 'label' => 'Pulpit', 'can' => null],
            ]],
            ['label' => 'Operacje', 'items' => [
                ['route' => 'documents.index', 'match' => 'documents.*', 'icon' => 'file-text', 'label' => 'Dokumenty', 'can' => 'documents.view'],
                ['route' => 'stock.index', 'match' => 'stock.*', 'icon' => 'boxes', 'label' => 'Stany magazynowe', 'can' => 'stock.view'],
                ['route' => 'layout.plan', 'match' => 'layout.*', 'icon' => 'map', 'label' => 'Plan magazynu', 'can' => 'stock.view'],
                ['route' => 'scanner.devices', 'match' => 'scanner.devices', 'icon' => 'scan-barcode', 'label' => 'Skanery mobilne', 'can' => 'scanner.use'],
            ]],
            ['label' => 'Kartoteki', 'items' => [
                ['route' => 'products.index', 'match' => 'products.*', 'icon' => 'package', 'label' => 'Towary', 'can' => 'stock.view'],
                ['route' => 'contractors.index', 'match' => 'contractors.*', 'icon' => 'building-2', 'label' => 'Kontrahenci', 'can' => 'stock.view'],
                ['route' => 'warehouses.index', 'match' => 'warehouses.*', 'icon' => 'warehouse', 'label' => 'Magazyny i kategorie', 'can' => 'stock.view'],
            ]],
            ['label' => 'Administracja', 'items' => [
                ['route' => 'users.index', 'match' => 'users.*', 'icon' => 'users', 'label' => 'Użytkownicy', 'can' => 'users.manage'],
                ['route' => 'roles.index', 'match' => 'roles.*', 'icon' => 'shield-check', 'label' => 'Role i uprawnienia', 'can' => 'roles.manage'],
                ['route' => 'webhooks.index', 'match' => 'webhooks.*', 'icon' => 'webhook', 'label' => 'Webhooki', 'can' => 'webhooks.manage'],
            ]],
        ])->map(fn ($group) => [...$group, 'items' => array_filter($group['items'], fn ($item) => ! $item['can'] || $user->can($item['can']))])
          ->filter(fn ($group) => count($group['items']) > 0);
    @endphp

    <div x-data="{ nav: false }" x-on:keydown.escape.window="nav = false" class="lg:grid lg:min-h-dvh lg:grid-cols-[15rem_minmax(0,1fr)]">
        {{-- Tło szuflady (telefon/tablet) --}}
        <div x-show="nav" x-cloak x-on:click="nav = false"
             x-transition:enter="transition-opacity duration-200 ease-out" x-transition:enter-start="opacity-0"
             x-transition:leave="transition-opacity duration-150 ease-out" x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-40 bg-black/40 lg:hidden"></div>

        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r border-line bg-surface transition-transform duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] lg:sticky lg:top-0 lg:z-auto lg:h-dvh lg:w-auto lg:translate-x-0 lg:transition-none"
            :class="nav && 'translate-x-0'"
            aria-label="Menu boczne"
        >
            <div class="flex h-14 shrink-0 items-center gap-2.5 px-4">
                <x-logo class="size-7 text-ink [--logo-ink:var(--surface)]" />
                <div class="leading-tight">
                    <div class="text-[0.9375rem] font-semibold tracking-tight text-ink">Regał</div>
                    <div class="text-[0.6875rem] font-medium tracking-wide text-ink-4 uppercase">Gospodarka magazynowa</div>
                </div>
            </div>

            <nav class="flex-1 overflow-y-auto px-3 pt-2 pb-4" aria-label="Nawigacja główna">
                @foreach ($nav as $group)
                    <div class="mb-4">
                        @if ($group['label'])
                            <div class="mb-1 px-2.5 text-[0.6875rem] font-medium tracking-wide text-ink-4 uppercase">{{ $group['label'] }}</div>
                        @endif
                        <ul class="space-y-0.5">
                            @foreach ($group['items'] as $item)
                                <li>
                                    <a href="{{ route($item['route']) }}" wire:navigate class="nav-link" @if (request()->routeIs($item['match'])) aria-current="page" @endif>
                                        <x-dynamic-component :component="'lucide-'.$item['icon']" aria-hidden="true" />
                                        {{ $item['label'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </nav>

            <div class="border-t border-line p-3">
                <x-ui.dropdown align="top-start" width="w-56">
                    <x-slot:trigger>
                        <button type="button" class="flex w-full items-center gap-2.5 rounded-lg p-1.5 text-left transition-colors hover:bg-surface-2">
                            <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-surface-3 text-xs font-semibold text-ink-2">{{ $user->initials() }}</span>
                            <span class="min-w-0 flex-1 leading-tight">
                                <span class="block truncate text-[0.8125rem] font-medium text-ink">{{ $user->name }}</span>
                                <span class="block truncate text-xs text-ink-4">{{ $user->role?->name ?? "brak roli" }}</span>
                            </span>
                            <x-lucide-chevrons-up-down class="size-4 text-ink-4" aria-hidden="true" />
                        </button>
                    </x-slot:trigger>

                    <x-ui.dropdown-item icon="sun" x-on:click="$store.theme.set('light')">Jasny motyw</x-ui.dropdown-item>
                    <x-ui.dropdown-item icon="moon" x-on:click="$store.theme.set('dark')">Ciemny motyw</x-ui.dropdown-item>
                    <x-ui.dropdown-item icon="monitor" x-on:click="$store.theme.set('system')">Motyw systemowy</x-ui.dropdown-item>
                    <div class="my-1 border-t border-line"></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-ui.dropdown-item icon="log-out" type="submit">Wyloguj</x-ui.dropdown-item>
                    </form>
                </x-ui.dropdown>
            </div>
        </aside>

        <div class="flex min-w-0 flex-col">
            <header class="sticky top-0 z-30 flex h-14 items-center gap-3 border-b border-line bg-surface/85 px-4 backdrop-blur-md sm:px-6">
                <button type="button" class="btn btn-ghost btn-icon -ml-2 lg:hidden" x-on:click="nav = true" aria-label="Otwórz menu">
                    <x-lucide-menu aria-hidden="true" />
                </button>
                <x-logo class="size-6 text-ink [--logo-ink:var(--surface)] lg:hidden" />

                @can('stock.view')<livewire:quick-find />@endcan

                <div class="ml-auto flex items-center gap-2">
                    @can('scanner.use')<livewire:scanner-status />@endcan

                    <span class="hidden items-center gap-2 rounded-lg px-2 text-[0.8125rem] text-ink-3 sm:inline-flex" x-data
                          :title="$store.live.state === 'connected' ? 'Połączono z serwerem WebSocket' : 'Brak połączenia WebSocket – dane odświeżą się po przeładowaniu'">
                        <span class="live-dot" x-show="$store.live.state === 'connected'"></span>
                        <span class="size-2 rounded-full bg-ink-4" x-show="$store.live.state !== 'connected'" x-cloak></span>
                        <span x-text="$store.live.state === 'connected' ? 'Na żywo' : 'Offline'">Na żywo</span>
                    </span>

                    <button type="button" class="btn btn-ghost btn-icon" x-on:click="$store.theme.toggle()" aria-label="Przełącz motyw">
                        <x-lucide-sun-moon aria-hidden="true" />
                    </button>
                </div>
            </header>

            <livewire:inventory-banner />

            <main id="main" class="mx-auto w-full max-w-[90rem] flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                {{ $slot }}
            </main>
        </div>
    </div>

    <x-ui.toasts />
    <x-ui.confirm />
</body>
</html>
