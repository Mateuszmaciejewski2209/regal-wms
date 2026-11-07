<div>
    <x-ui.page-header title="Role i uprawnienia" description="Hierarchia ról: każdy zarządza tylko rolami i kontami poniżej własnego poziomu i nadaje wyłącznie uprawnienia, które sam posiada."
                      :back="route('users.index')" back-label="Użytkownicy">
        <x-slot:actions>
            <x-ui.button variant="primary" icon="plus" wire:click="create">Nowa rola</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-3 xl:grid-cols-[22rem_minmax(0,1fr)]">
        {{-- Hierarchia --}}
        <section class="card self-start" aria-labelledby="hier-title">
            <div class="border-b border-line px-4 py-3">
                <h2 id="hier-title" class="font-semibold">Hierarchia</h2>
                <p class="text-xs text-ink-4">Od najwyższego poziomu</p>
            </div>
            <ol class="relative px-4 py-3">
                @foreach ($roles as $role)
                    <li class="relative flex gap-3 pb-4 last:pb-0" wire:key="role-{{ $role->id }}">
                        @unless ($loop->last)<span class="absolute top-8 bottom-0 left-[0.9375rem] w-px bg-line" aria-hidden="true"></span>@endunless
                        <span class="num z-10 grid size-8 shrink-0 place-items-center rounded-full border border-line-strong bg-surface text-xs font-semibold text-ink-2">{{ $role->level }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-1.5 font-medium text-ink">
                                        {{ $role->name }}
                                        @if ($role->is_system)<x-ui.badge tone="inventory" icon="lock">wbudowana</x-ui.badge>@endif
                                    </div>
                                    <p class="text-pretty text-[0.8125rem] text-ink-3">{{ $role->description }}</p>
                                    <p class="mt-0.5 text-xs text-ink-4">{{ \App\Support\Num::plural($role->users_count, 'konto', 'konta', 'kont') }} · {{ \App\Support\Num::plural($role->abilityKeys()->count(), 'uprawnienie', 'uprawnienia', 'uprawnień') }}</p>
                                </div>
                                @can('manage-role', $role)
                                    <x-ui.dropdown>
                                        <x-slot:trigger><x-ui.button size="sm" icon-only icon="ellipsis" aria-label="Akcje roli {{ $role->name }}" /></x-slot:trigger>
                                        <x-ui.dropdown-item icon="square-pen" wire:click="edit({{ $role->id }})">Edytuj</x-ui.dropdown-item>
                                        <x-ui.dropdown-item icon="trash-2" danger
                                            x-on:click="$confirm({{ Js::from(['title' => 'Usunąć rolę „'.$role->name.'”?', 'message' => 'Rola zostanie trwale usunięta. Nie można usunąć roli przypisanej do kont.', 'confirm' => 'Usuń rolę']) }}).then(ok => ok && $wire.delete({{ $role->id }}))">Usuń</x-ui.dropdown-item>
                                    </x-ui.dropdown>
                                @endcan
                            </div>
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- Macierz uprawnień --}}
        <section class="card overflow-hidden" aria-labelledby="matrix-title">
            <div class="border-b border-line px-4 py-3">
                <h2 id="matrix-title" class="font-semibold">Macierz uprawnień</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Uprawnienie</th>
                            @foreach ($roles as $role)<th class="text-center! whitespace-nowrap">{{ $role->name }}</th>@endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($groups as $group => $abilities)
                            <tr><td colspan="99" class="bg-surface-2/60 py-1.5 text-xs font-medium tracking-wide text-ink-4 uppercase">{{ $group }}</td></tr>
                            @foreach ($abilities as $ability)
                                <tr>
                                    <td>
                                        <div class="text-ink">{{ $ability->label() }}</div>
                                        <div class="code text-xs text-ink-4">{{ $ability->value }}</div>
                                    </td>
                                    @foreach ($roles as $role)
                                        <td class="text-center">
                                            @if ($role->allows($ability))
                                                <x-lucide-check class="mx-auto size-4 text-accent" aria-label="tak" />
                                            @else
                                                <span class="text-ink-4" aria-label="nie">–</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <x-ui.modal wire:model="showForm" :title="$editingId ? 'Edycja roli' : 'Nowa rola'" max-width="2xl">
        <form wire:submit="save" id="role-form" class="grid gap-4 sm:grid-cols-6">
            <x-ui.field label="Nazwa" for="r-name" error="form.name" required class="sm:col-span-4">
                <input id="r-name" type="text" wire:model="form.name" class="control" autocomplete="off">
            </x-ui.field>
            <x-ui.field label="Poziom" for="r-level" error="form.level" required :hint="'1–'.$maxLevel" class="sm:col-span-2">
                <input id="r-level" type="number" min="1" max="{{ $maxLevel }}" wire:model="form.level" class="control text-right tabular-nums">
            </x-ui.field>
            <x-ui.field label="Opis" for="r-desc" error="form.description" class="sm:col-span-6">
                <input id="r-desc" type="text" wire:model="form.description" class="control" autocomplete="off">
            </x-ui.field>

            <fieldset class="sm:col-span-6">
                <legend class="label">Uprawnienia</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($groups as $group => $abilities)
                        <div>
                            <div class="mb-1.5 text-xs font-medium tracking-wide text-ink-4 uppercase">{{ $group }}</div>
                            <div class="space-y-1">
                                @foreach ($abilities as $ability)
                                    @php $allowed = in_array($ability->value, $grantable, true); @endphp
                                    <label @class(['flex items-start gap-2.5 rounded-lg px-2 py-1.5', 'hover:bg-surface-2' => $allowed, 'opacity-50' => ! $allowed])>
                                        <input type="checkbox" value="{{ $ability->value }}" wire:model="form.abilities" class="mt-0.5" @disabled(! $allowed)>
                                        <span>
                                            <span class="block text-[0.8125rem] text-ink">{{ $ability->label() }}</span>
                                            @unless ($allowed)<span class="block text-xs text-ink-4">Nie masz tego uprawnienia</span>@endunless
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                @error('form.abilities.*')<p class="mt-1.5 text-[0.8125rem] text-danger">{{ $message }}</p>@enderror
            </fieldset>
        </form>
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="$wire.showForm = false">Anuluj</x-ui.button>
            <x-ui.button type="submit" form="role-form" variant="primary">Zapisz</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
