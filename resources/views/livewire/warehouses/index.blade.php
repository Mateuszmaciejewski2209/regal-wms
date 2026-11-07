@php use App\Support\Num; @endphp

<div>
    <x-ui.page-header title="Magazyny i kategorie" description="Struktura magazynowa i słownik kategorii towarów.">
        <x-slot:actions>
            @can('warehouses.manage')
                <x-ui.button variant="primary" icon="plus" wire:click="create">Nowy magazyn</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section aria-label="Magazyny" class="grid content-start gap-3 sm:grid-cols-2">
            @foreach ($warehouses as $w)
                <article class="card wh-stripe wh-{{ $w->colorKey() }} p-4" wire:key="w-{{ $w->id }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <x-ui.warehouse :warehouse="$w" class="text-[0.8125rem]" />
                                @unless ($w->is_active)<x-ui.badge tone="outline">nieaktywny</x-ui.badge>@endunless
                            </div>
                            <h2 class="mt-2 font-semibold text-ink">{{ $w->name }}</h2>
                            <p class="text-[0.8125rem] text-ink-3">{{ $w->address ?: 'Brak adresu' }}</p>
                        </div>
                        @can('warehouses.manage')
                            <button type="button" wire:click="edit({{ $w->id }})" class="btn btn-ghost btn-icon btn-sm" aria-label="Edytuj {{ $w->code }}"><x-lucide-square-pen aria-hidden="true" /></button>
                        @endcan
                    </div>
                    <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-line pt-3 text-[0.8125rem]">
                        <div><dt class="text-ink-4">Towarów na stanie</dt><dd class="num text-left font-semibold">{{ Num::qty($w->products_count) }}</dd></div>
                        <div><dt class="text-ink-4">Wartość</dt><dd class="num text-left font-semibold">{{ Num::money($w->stock_value ?? 0) }}</dd></div>
                    </dl>
                    <div class="mt-3 flex gap-2">
                        <x-ui.button size="sm" :href="route('stock.index', ['warehouse' => $w->id])" icon="boxes">Stany</x-ui.button>
                        <x-ui.button size="sm" :href="route('documents.index', ['warehouse' => $w->id])" icon="file-text">Dokumenty</x-ui.button>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="card self-start" aria-labelledby="cat-title">
            <div class="border-b border-line px-4 py-3">
                <h2 id="cat-title" class="font-semibold">Kategorie towarów</h2>
            </div>
            @can('warehouses.manage')
                <form wire:submit="addCategory" class="flex gap-2 border-b border-line p-3">
                    <label for="new-cat" class="sr-only">Nazwa kategorii</label>
                    <input id="new-cat" type="text" wire:model="newCategory" class="control" placeholder="Nowa kategoria…" autocomplete="off">
                    <x-ui.button type="submit" icon="plus" icon-only aria-label="Dodaj kategorię" />
                </form>
                @error('newCategory')<p class="px-3 pt-2 text-[0.8125rem] text-danger">{{ $message }}</p>@enderror
            @endcan
            <ul class="divide-y divide-line">
                @forelse ($categories as $c)
                    <li class="flex items-center justify-between gap-3 px-4 py-2" wire:key="cat-{{ $c->id }}">
                        <span class="text-ink">{{ $c->name }}</span>
                        <span class="flex items-center gap-1">
                            <span class="num text-xs text-ink-4">{{ $c->products_count }}</span>
                            @can('warehouses.manage')
                                <button type="button" class="btn btn-ghost btn-icon btn-sm text-ink-4 hover:text-danger" aria-label="Usuń kategorię {{ $c->name }}"
                                        x-on:click="$confirm({{ Js::from(['title' => 'Usunąć kategorię „'.$c->name.'”?', 'message' => $c->products_count ? 'Towary z tej kategorii ('.$c->products_count.') pozostaną bez kategorii.' : 'Kategoria nie ma przypisanych towarów.', 'confirm' => 'Usuń kategorię']) }}).then(ok => ok && $wire.deleteCategory({{ $c->id }}))"><x-lucide-trash-2 aria-hidden="true" /></button>
                            @endcan
                        </span>
                    </li>
                @empty
                    <li class="px-4 py-6 text-center text-[0.8125rem] text-ink-3">Brak kategorii</li>
                @endforelse
            </ul>
        </section>
    </div>

    <x-ui.modal wire:model="showForm" :title="$editingId ? 'Edycja magazynu' : 'Nowy magazyn'" max-width="md">
        <form wire:submit="save" id="wh-form" class="grid gap-4">
            <x-ui.field label="Kod" for="w-code" error="form.code" required hint="Krótki symbol, np. MG1, HALA2.">
                <input id="w-code" type="text" wire:model="form.code" class="control font-mono uppercase" autocomplete="off" maxlength="10">
            </x-ui.field>
            <x-ui.field label="Nazwa" for="w-name" error="form.name" required>
                <input id="w-name" type="text" wire:model="form.name" class="control" autocomplete="off">
            </x-ui.field>
            <x-ui.field label="Adres" for="w-address" error="form.address">
                <input id="w-address" type="text" wire:model="form.address" class="control" autocomplete="street-address">
            </x-ui.field>
            <fieldset>
                <legend class="mb-2 text-[0.8125rem] font-medium text-ink-2">Kolor magazynu</legend>
                <div class="flex flex-wrap gap-2" role="radiogroup">
                    @foreach (\App\Models\Warehouse::COLORS as $key => $label)
                        <label class="wh-{{ $key }} cursor-pointer" title="{{ $label }}">
                            <input type="radio" wire:model="form.color" value="{{ $key }}" class="peer sr-only">
                            <span class="grid size-8 place-items-center rounded-full border-2 border-transparent bg-[var(--wh)] text-white ring-offset-2 ring-offset-surface transition-shadow peer-checked:ring-2 peer-checked:ring-[var(--wh)] peer-focus-visible:ring-2 peer-focus-visible:ring-ink">
                                <x-lucide-check class="hidden size-4 [.peer:checked+span>&]:block" aria-hidden="true" />
                            </span>
                            <span class="sr-only">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                <p class="mt-2 text-xs text-ink-4">Kolor pojawia się przy kodzie magazynu w stanach, dokumentach i na planie.</p>
            </fieldset>
            <label class="flex items-center gap-2 text-ink-2"><input type="checkbox" wire:model="form.is_active"> Aktywny</label>
        </form>
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="$wire.showForm = false">Anuluj</x-ui.button>
            <x-ui.button type="submit" form="wh-form" variant="primary">Zapisz</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
