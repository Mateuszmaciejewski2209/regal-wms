<?php

namespace App\Livewire\Warehouses;

use App\Models\Category;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Magazyny i kategorie')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public string $newCategory = '';

    public function create(): void
    {
        Gate::authorize('warehouses.manage');
        $this->editingId = null;
        $this->form = ['code' => '', 'color' => Warehouse::nextFreeColor(), 'name' => '', 'address' => '', 'is_active' => true];
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('warehouses.manage');
        $this->editingId = $id;
        $warehouse = Warehouse::findOrFail($id);
        $this->form = array_map(fn ($v) => $v ?? '', $warehouse->only(['code', 'name', 'address', 'is_active'])) + ['color' => $warehouse->colorKey()];
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function save(): void
    {
        Gate::authorize('warehouses.manage');
        $this->form['code'] = strtoupper(trim($this->form['code'] ?? ''));

        $data = $this->validate([
            'form.code' => ['required', 'alpha_num', 'max:10', Rule::unique('warehouses', 'code')->ignore($this->editingId)],
            'form.name' => ['required', 'string', 'max:255'],
            'form.address' => ['nullable', 'string', 'max:255'],
            'form.is_active' => ['boolean'],
            'form.color' => ['required', Rule::in(array_keys(Warehouse::COLORS))],
        ], [], ['form.code' => 'kod', 'form.name' => 'nazwa'])['form'];

        Warehouse::updateOrCreate(['id' => $this->editingId], $data);
        $this->showForm = false;
        $this->dispatch('toast', title: 'Zapisano magazyn', message: $data['code'], tone: 'success');
    }

    public function addCategory(): void
    {
        Gate::authorize('warehouses.manage');
        $this->validate(['newCategory' => ['required', 'string', 'max:100', 'unique:categories,name']], [], ['newCategory' => 'nazwa kategorii']);

        Category::create(['name' => trim($this->newCategory)]);
        $this->reset('newCategory');
    }

    public function deleteCategory(int $id): void
    {
        Gate::authorize('warehouses.manage');
        Category::whereKey($id)->delete(); // towary zostają – FK nullOnDelete
    }

    public function render()
    {
        return view('livewire.warehouses.index', [
            'warehouses' => Warehouse::query()
                ->withCount(['stocks as products_count' => fn ($q) => $q->where('quantity', '!=', 0)])
                ->withSum('stocks as stock_value', 'value')
                ->orderBy('code')
                ->get(),
            'categories' => Category::query()->withCount('products')->orderBy('name')->get(),
        ]);
    }
}
