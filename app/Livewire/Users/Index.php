<?php

namespace App\Livewire\Users;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Użytkownicy')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public function create(): void
    {
        Gate::authorize('users.manage');

        $this->editingId = null;
        $this->form = [
            'name' => '', 'email' => '', 'password' => '', 'is_active' => true,
            'role_id' => $this->assignableRoles()->sortBy('level')->first()?->id,
        ];
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $user = User::findOrFail($id);
        Gate::authorize('manage-user', $user);

        $this->editingId = $id;
        $this->form = ['name' => $user->name, 'email' => $user->email, 'role_id' => $user->role_id, 'is_active' => $user->is_active, 'password' => ''];
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function save(): void
    {
        $me = auth()->user();
        $target = $this->editingId ? User::findOrFail($this->editingId) : null;

        $target ? Gate::authorize('manage-user', $target) : Gate::authorize('users.manage');

        $isSelf = $target?->id === $me->id;
        $assignable = $this->assignableRoles()->pluck('id')->all();

        $data = $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'form.role_id' => ['required', 'integer', $isSelf ? Rule::in([$me->role_id]) : Rule::in($assignable)],
            'form.is_active' => ['boolean', $isSelf ? 'accepted' : 'nullable'],
            'form.password' => [$target ? 'nullable' : 'required', Password::min(8)],
        ], [
            'form.role_id.in' => $isSelf ? 'Nie możesz zmienić własnej roli.' : 'Możesz nadać tylko rolę niższą od własnej.',
            'form.is_active.accepted' => 'Nie możesz zablokować własnego konta.',
        ], ['form.name' => 'imię i nazwisko', 'form.email' => 'e-mail', 'form.password' => 'hasło', 'form.role_id' => 'rola'])['form'];

        if (blank($data['password'])) {
            unset($data['password']);
        }

        User::updateOrCreate(['id' => $this->editingId], $data);

        $this->showForm = false;
        $this->dispatch('toast', title: 'Zapisano użytkownika', message: $data['email'], tone: 'success');
    }

    public function render()
    {
        return view('livewire.users.index', [
            'users' => User::query()
                ->with('role')
                ->withCount(['scannerDevices' => fn ($q) => $q->active()])
                ->get()
                ->sortBy([fn ($a, $b) => $b->level() <=> $a->level(), fn ($a, $b) => strcmp($a->name, $b->name)]),
            'roleOptions' => $this->assignableRoles()->sortByDesc('level')->mapWithKeys(fn (Role $r) => [$r->id => "{$r->name} · poziom {$r->level}"])->all(),
            'editingSelf' => $this->editingId === auth()->id(),
        ]);
    }

    /** Role, które bieżący użytkownik może nadać (niższe od własnej; administrator – wszystkie). */
    private function assignableRoles()
    {
        $me = auth()->user();

        return Role::query()->get()->filter(fn (Role $role) => $me->canAssignRole($role))->values();
    }
}
