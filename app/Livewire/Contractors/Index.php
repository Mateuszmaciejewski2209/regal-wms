<?php

namespace App\Livewire\Contractors;

use App\Enums\ContractorType;
use App\Livewire\Concerns\WithPerPage;
use App\Models\Contractor;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Kontrahenci')]
class Index extends Component
{
    use WithPerPage;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $type = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'type'], true)) {
            $this->resetPage();
        }
    }

    public function create(): void
    {
        Gate::authorize('contractors.manage');
        $this->editingId = null;
        $this->form = ['name' => '', 'nip' => '', 'type' => ContractorType::Both->value, 'street' => '', 'postal_code' => '', 'city' => '', 'email' => '', 'phone' => '', 'is_active' => true];
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('contractors.manage');
        $contractor = Contractor::findOrFail($id);
        $this->editingId = $id;
        $this->form = $contractor->only(['name', 'nip', 'street', 'postal_code', 'city', 'email', 'phone', 'is_active']) + ['type' => $contractor->type->value];
        $this->form = array_map(fn ($v) => $v ?? '', $this->form);
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function save(): void
    {
        Gate::authorize('contractors.manage');

        $this->form['nip'] = preg_replace('/\D/', '', (string) ($this->form['nip'] ?? ''));

        $data = $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.nip' => ['nullable', 'digits:10', Rule::unique('contractors', 'nip')->ignore($this->editingId)],
            'form.type' => ['required', Rule::enum(ContractorType::class)],
            'form.street' => ['nullable', 'string', 'max:255'],
            'form.postal_code' => ['nullable', 'regex:/^\d{2}-\d{3}$/'],
            'form.city' => ['nullable', 'string', 'max:120'],
            'form.email' => ['nullable', 'email', 'max:255'],
            'form.phone' => ['nullable', 'string', 'max:30'],
            'form.is_active' => ['boolean'],
        ], ['form.postal_code.regex' => 'Kod pocztowy w formacie 00-000.'], [
            'form.name' => 'nazwa', 'form.nip' => 'NIP', 'form.email' => 'e-mail', 'form.postal_code' => 'kod pocztowy',
        ])['form'];

        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);

        Contractor::updateOrCreate(['id' => $this->editingId], $data);

        $this->showForm = false;
        $this->dispatch('toast', title: 'Zapisano kontrahenta', message: $data['name'], tone: 'success');
    }

    public function render()
    {
        $contractors = Contractor::query()
            ->withCount('documents')
            ->when(trim($this->search) !== '', function ($q) {
                $term = '%'.trim($this->search).'%';
                $q->where(fn ($s) => $s->whereLike('name', $term)->orWhereLike('nip', $term)->orWhereLike('city', $term));
            })
            ->when($this->type, fn ($q) => $q->where('type', $this->type))
            ->orderBy('name')
            ->paginate($this->perPage());

        return view('livewire.contractors.index', [
            'contractors' => $contractors,
            'typeOptions' => collect(ContractorType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all(),
        ]);
    }
}
