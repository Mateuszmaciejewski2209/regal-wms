<?php

namespace App\Livewire\Products;

use App\Livewire\Concerns\WithPerPage;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Towary')]
class Index extends Component
{
    use WithPerPage;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $category = '';

    #[Url]
    public string $status = 'active';

    /** @var list<string> zaznaczone towary (np. do wydruku etykiet) */
    public array $selected = [];

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'category', 'status'], true)) {
            $this->resetPage();
        }
    }

    /** Nagłówek tabeli: zaznacz/odznacz wszystkie na bieżącej stronie. */
    public function togglePage(array $ids): void
    {
        $ids = array_map('strval', $ids);
        $allSelected = ! array_diff($ids, $this->selected);

        $this->selected = $allSelected
            ? array_values(array_diff($this->selected, $ids))
            : array_values(array_unique([...$this->selected, ...$ids]));
    }

    /** Zaznacza wszystkie towary pasujące do filtrów (na wszystkich stronach). */
    public function selectAllFiltered(): void
    {
        $this->selected = $this->filtered()->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function render()
    {
        $products = $this->filtered()
            ->with('category:id,name')
            ->withSum('stocks as total_quantity', 'quantity')
            ->orderBy('name')
            ->paginate($this->perPage());

        $pageIds = $products->pluck('id')->map(fn ($id) => (string) $id)->all();
        $selectedOnPage = count(array_intersect($pageIds, $this->selected));

        return view('livewire.products.index', [
            'products' => $products,
            'pageIds' => $pageIds,
            'pageState' => $selectedOnPage === 0 ? 'none' : ($selectedOnPage === count($pageIds) ? 'all' : 'some'),
            'filteredCount' => $products->total(),
            'categoryOptions' => ['' => 'Wszystkie kategorie'] + Category::query()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    private function filtered(): Builder
    {
        return Product::query()
            ->search($this->search)
            ->when($this->category !== '', fn ($q) => $q->where('category_id', $this->category))
            ->when($this->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->status === 'inactive', fn ($q) => $q->where('is_active', false));
    }
}
