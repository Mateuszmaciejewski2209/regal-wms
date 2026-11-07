<div>
    <x-ui.page-header title="Kontrahenci" description="Dostawcy (PZ) i odbiorcy (WZ).">
        <x-slot:actions>
            @can('contractors.manage')
                <x-ui.button variant="primary" icon="plus" wire:click="create">Nowy kontrahent</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4 grid gap-2 sm:grid-cols-[minmax(0,1fr)_14rem]">
        <x-ui.search wire:model.live.debounce.300ms="search" placeholder="Nazwa, NIP lub miejscowość…" />
        <x-ui.select wire:model.live="type" :options="['' => 'Wszyscy'] + $typeOptions" label="Rodzaj" />
    </div>

    <div class="card overflow-hidden">
        @if ($contractors->isEmpty())
            <x-ui.empty icon="building-2" title="Brak kontrahentów" :description="$search || $type ? 'Nikt nie pasuje do filtrów.' : 'Dodaj dostawców i odbiorców, by wystawiać PZ i WZ.'" />
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Nazwa</th><th>NIP</th><th class="hidden md:table-cell">Adres</th><th>Rodzaj</th><th class="num hidden sm:table-cell">Dokumentów</th><th class="w-12"><span class="sr-only">Akcje</span></th></tr></thead>
                    <tbody>
                        @foreach ($contractors as $c)
                            <tr wire:key="c-{{ $c->id }}">
                                <td class="min-w-56">
                                    <span class="font-medium text-ink">{{ $c->name }}</span>
                                    @unless ($c->is_active)<x-ui.badge tone="outline" class="ml-1">nieaktywny</x-ui.badge>@endunless
                                    @if ($c->email)<div class="text-xs text-ink-4">{{ $c->email }}</div>@endif
                                </td>
                                <td class="code whitespace-nowrap text-ink-2">{{ $c->formattedNip() ?? '—' }}</td>
                                <td class="hidden text-ink-3 md:table-cell">{{ $c->address() ?: '—' }}</td>
                                <td><x-ui.badge :tone="match($c->type->value) { 'supplier' => 'in', 'customer' => 'out', default => 'neutral' }">{{ $c->type->label() }}</x-ui.badge></td>
                                <td class="num hidden text-ink-3 sm:table-cell">{{ $c->documents_count }}</td>
                                <td class="text-right">
                                    @can('contractors.manage')
                                        <button type="button" wire:click="edit({{ $c->id }})" class="btn btn-ghost btn-icon btn-sm" aria-label="Edytuj {{ $c->name }}"><x-lucide-square-pen aria-hidden="true" /></button>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <x-ui.pagination :paginator="$contractors" />
    </div>

    <x-ui.modal wire:model="showForm" :title="$editingId ? 'Edycja kontrahenta' : 'Nowy kontrahent'" max-width="xl">
        <form wire:submit="save" id="contractor-form" class="grid gap-x-4 gap-y-4 sm:grid-cols-6">
            <x-ui.field label="Nazwa" for="c-name" error="form.name" required class="sm:col-span-6">
                <input id="c-name" type="text" wire:model="form.name" class="control" autocomplete="organization">
            </x-ui.field>
            <x-ui.field label="NIP" for="c-nip" error="form.nip" class="sm:col-span-3">
                <input id="c-nip" type="text" inputmode="numeric" wire:model="form.nip" class="control font-mono" placeholder="0000000000" autocomplete="off">
            </x-ui.field>
            <x-ui.field label="Rodzaj" for="c-type" error="form.type" required class="sm:col-span-3">
                <x-ui.select id="c-type" wire:model="form.type" :options="$typeOptions" />
            </x-ui.field>
            <x-ui.field label="Ulica i numer" for="c-street" error="form.street" class="sm:col-span-6">
                <input id="c-street" type="text" wire:model="form.street" class="control" autocomplete="street-address">
            </x-ui.field>
            <x-ui.field label="Kod pocztowy" for="c-postal" error="form.postal_code" class="sm:col-span-2">
                <input id="c-postal" type="text" wire:model="form.postal_code" class="control" placeholder="00-000" autocomplete="postal-code">
            </x-ui.field>
            <x-ui.field label="Miejscowość" for="c-city" error="form.city" class="sm:col-span-4">
                <input id="c-city" type="text" wire:model="form.city" class="control" autocomplete="address-level2">
            </x-ui.field>
            <x-ui.field label="E-mail" for="c-email" error="form.email" class="sm:col-span-3">
                <input id="c-email" type="email" wire:model="form.email" class="control" autocomplete="email" spellcheck="false">
            </x-ui.field>
            <x-ui.field label="Telefon" for="c-phone" error="form.phone" class="sm:col-span-3">
                <input id="c-phone" type="tel" wire:model="form.phone" class="control" autocomplete="tel">
            </x-ui.field>
            <label class="flex items-center gap-2 text-ink-2 sm:col-span-6">
                <input type="checkbox" wire:model="form.is_active"> Aktywny
            </label>
            <p class="text-xs text-ink-4 sm:col-span-6"><span class="text-danger">*</span> pola wymagane</p>
        </form>
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="$wire.showForm = false">Anuluj</x-ui.button>
            <x-ui.button type="submit" form="contractor-form" variant="primary">Zapisz</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
