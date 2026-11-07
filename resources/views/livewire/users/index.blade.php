<div>
    <x-ui.page-header title="Użytkownicy" description="Konta pracowników. Możesz zarządzać tylko kontami z rolą niższą niż Twoja.">
        <x-slot:actions>
            @can('roles.manage')
                <x-ui.button :href="route('roles.index')" icon="shield-check">Role i uprawnienia</x-ui.button>
            @endcan
            <x-ui.button variant="primary" icon="plus" wire:click="create">Nowy użytkownik</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Użytkownik</th><th>Rola</th><th class="num">Poziom</th><th class="num hidden sm:table-cell">Telefony</th><th>Status</th><th class="w-12"><span class="sr-only">Akcje</span></th></tr></thead>
                <tbody>
                    @foreach ($users as $u)
                        <tr wire:key="u-{{ $u->id }}">
                            <td>
                                <div class="flex items-center gap-3">
                                    <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-surface-3 text-xs font-semibold text-ink-2">{{ $u->initials() }}</span>
                                    <div class="min-w-0">
                                        <div class="font-medium text-ink">{{ $u->name }} @if ($u->id === auth()->id())<span class="text-xs font-normal text-ink-4">(Ty)</span>@endif</div>
                                        <div class="text-xs text-ink-4">{{ $u->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td><x-ui.badge :tone="$u->role?->tone() ?? 'outline'">{{ $u->role?->name ?? 'brak roli' }}</x-ui.badge></td>
                            <td class="num text-ink-3">{{ $u->role?->level ?? '—' }}</td>
                            <td class="num hidden text-ink-3 sm:table-cell">{{ $u->scanner_devices_count }}</td>
                            <td>@if ($u->is_active)<x-ui.badge tone="success">aktywny</x-ui.badge>@else<x-ui.badge tone="danger">zablokowany</x-ui.badge>@endif</td>
                            <td class="text-right">
                                @can('manage-user', $u)
                                    <button type="button" wire:click="edit({{ $u->id }})" class="btn btn-ghost btn-icon btn-sm" aria-label="Edytuj {{ $u->name }}"><x-lucide-square-pen aria-hidden="true" /></button>
                                @else
                                    <x-ui.tooltip focusable text="Rola wyższa lub równa Twojej – brak uprawnień do edycji." position="left">
                                        <x-lucide-lock class="size-4 text-ink-4" aria-label="Zablokowane" />
                                    </x-ui.tooltip>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <x-ui.modal wire:model="showForm" :title="$editingId ? 'Edycja użytkownika' : 'Nowy użytkownik'" max-width="md">
        <form wire:submit="save" id="user-form" class="grid gap-4">
            <x-ui.field label="Imię i nazwisko" for="u-name" error="form.name" required>
                <input id="u-name" type="text" wire:model="form.name" class="control" autocomplete="name">
            </x-ui.field>
            <x-ui.field label="E-mail (login)" for="u-email" error="form.email" required>
                <input id="u-email" type="email" wire:model="form.email" class="control" autocomplete="off" spellcheck="false">
            </x-ui.field>
            <x-ui.field label="Rola" for="u-role" error="form.role_id" required :hint="$editingSelf ? 'Własnej roli nie można zmienić.' : 'Lista zawiera role niższe od Twojej.'">
                @if ($editingSelf)
                    <div class="control flex items-center bg-surface-2 text-ink-3">{{ auth()->user()->role?->name }}</div>
                @else
                    <x-ui.select id="u-role" wire:model="form.role_id" :options="$roleOptions" label="Rola" />
                @endif
            </x-ui.field>
            <x-ui.field :label="$editingId ? 'Nowe hasło' : 'Hasło'" for="u-password" error="form.password" :required="! $editingId" :hint="$editingId ? 'Zostaw puste, by nie zmieniać.' : 'Min. 8 znaków.'">
                <input id="u-password" type="password" wire:model="form.password" class="control" autocomplete="new-password">
            </x-ui.field>
            <div>
                <label class="flex items-center gap-2 text-ink-2"><input type="checkbox" wire:model="form.is_active" @disabled($editingSelf)> Konto aktywne</label>
                @error('form.is_active')<p class="mt-1.5 text-[0.8125rem] text-danger">{{ $message }}</p>@enderror
            </div>
        </form>
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="$wire.showForm = false">Anuluj</x-ui.button>
            <x-ui.button type="submit" form="user-form" variant="primary">Zapisz</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
