<?php

namespace App\Livewire\Roles;

use App\Enums\Ability;
use App\Models\Role;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Role i uprawnienia: hierarchia poziomów + macierz uprawnień.
 * Zasady: rolę można tworzyć/edytować tylko poniżej własnego poziomu
 * i przyznawać jej wyłącznie uprawnienia, które ma się samemu.
 */
#[Title('Role i uprawnienia')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public function create(): void
    {
        Gate::authorize('roles.manage');

        $this->editingId = null;
        $this->form = ['name' => '', 'description' => '', 'level' => min(10, $this->maxLevel()), 'abilities' => [Ability::DocumentsView->value, Ability::StockView->value]];
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $role = Role::with('permissions')->findOrFail($id);
        Gate::authorize('manage-role', $role);

        $this->editingId = $id;
        $this->form = [
            'name' => $role->name,
            'description' => (string) $role->description,
            'level' => $role->level,
            'abilities' => $role->abilityKeys()->values()->all(),
        ];
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function save(): void
    {
        $role = $this->editingId ? Role::findOrFail($this->editingId) : null;
        $role ? Gate::authorize('manage-role', $role) : Gate::authorize('roles.manage');

        $grantable = $this->grantableAbilities();

        $data = $this->validate([
            'form.name' => ['required', 'string', 'max:80', Rule::unique('roles', 'name')->ignore($this->editingId)],
            'form.description' => ['nullable', 'string', 'max:255'],
            'form.level' => ['required', 'integer', 'min:1', 'max:'.$this->maxLevel()],
            'form.abilities' => ['array'],
            'form.abilities.*' => [Rule::in($grantable)],
        ], [
            'form.level.max' => 'Poziom musi być niższy od Twojego (maks. :max).',
            'form.abilities.*.in' => 'Nie możesz nadać uprawnienia, którego sam nie masz.',
        ], ['form.name' => 'nazwa', 'form.level' => 'poziom'])['form'];

        $role ??= new Role(['slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4))]);
        $role->fill(['name' => $data['name'], 'description' => $data['description'] ?: null, 'level' => $data['level']])->save();
        $role->syncAbilities($data['abilities']);

        $this->showForm = false;
        $this->dispatch('toast', title: 'Zapisano rolę', message: $role->name, tone: 'success');
    }

    public function delete(int $id): void
    {
        $role = Role::withCount('users')->findOrFail($id);
        Gate::authorize('manage-role', $role);

        if ($role->users_count > 0) {
            $this->dispatch('toast', title: 'Nie można usunąć', message: "Rolę ma {$role->users_count} użytkowników – najpierw zmień im rolę.", tone: 'danger');

            return;
        }

        $role->delete();
        $this->dispatch('toast', title: 'Usunięto rolę', message: $role->name, tone: 'neutral');
    }

    public function render()
    {
        return view('livewire.roles.index', [
            'roles' => Role::query()->with('permissions')->withCount('users')->orderByDesc('level')->get(),
            'groups' => Ability::grouped(),
            'grantable' => $this->grantableAbilities(),
            'maxLevel' => $this->maxLevel(),
        ]);
    }

    private function maxLevel(): int
    {
        $me = auth()->user();

        return $me->isAdmin() ? Role::MAX_LEVEL - 1 : max(1, $me->level() - 1);
    }

    /** @return list<string> */
    private function grantableAbilities(): array
    {
        $me = auth()->user();

        return collect(Ability::cases())->filter(fn (Ability $a) => $me->hasAbility($a))->map->value->values()->all();
    }
}
