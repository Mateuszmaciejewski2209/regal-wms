<?php

namespace App\Livewire\Stock;

use App\Livewire\Concerns\WithPerPage;
use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Stany magazynowe w ujęciu towarowym: jeden wiersz = jeden towar, z rozbiciem na magazyny.
 */
#[Title('Stany magazynowe')]
class Index extends Component
{
    use WithPerPage;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $warehouse = '';

    #[Url]
    public string $category = '';

    #[Url]
    public bool $low = false;

    #[Url]
    public bool $zero = false;

    public function getListeners(): array
    {
        return ['echo-private:warehouse,.document.changed' => '$refresh'];
    }

    public function updated(string $property): void
    {
        if ($property !== 'perPage') {
            $this->resetPage();
        }
    }

    public function render()
    {
        $warehouseId = $this->warehouse !== '' ? (int) $this->warehouse : null;
        $base = $this->filtered($warehouseId);

        $sum = fn (string $column, ?int $warehouse) => Stock::query()
            ->selectRaw("coalesce(sum({$column}), 0)")
            ->whereColumn('stocks.product_id', 'products.id')
            ->when($warehouse, fn ($q) => $q->where('stocks.warehouse_id', $warehouse));

        $products = (clone $base)
            ->select('products.*')
            ->selectSub($sum('quantity', $warehouseId), 'on_hand')
            ->selectSub($sum('value', $warehouseId), 'on_hand_value')
            ->selectSub($sum('quantity', null), 'total_on_hand')
            ->with(['category:id,name', 'stocks' => fn ($q) => $q->where('quantity', '!=', 0)->with('warehouse:id,code,name,color')])
            ->orderBy('products.name')
            ->paginate($this->perPage());

        $totalValue = Stock::query()
            ->whereIn('product_id', (clone $base)->select('products.id'))
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->sum('value');

        // Ile aktywnych towarów nie ma stanu (w wybranym magazynie) – widać, co doda „Pokaż zerowe”.
        $zeroCount = Product::query()
            ->search($this->search)
            ->when($this->category !== '', fn ($q) => $q->where('products.category_id', $this->category))
            ->where('products.is_active', true)
            ->whereNotExists($this->hasStock($warehouseId))
            ->count();

        return view('livewire.stock.index', [
            'products' => $products,
            'zeroCount' => $zeroCount,
            'totalValue' => (string) $totalValue,
            'warehouseId' => $warehouseId,
            'warehouseOptions' => ['' => 'Wszystkie magazyny'] + Warehouse::query()->orderBy('code')->get()->mapWithKeys(fn ($w) => [$w->id => "{$w->code} · {$w->name}"])->all(),
            'categoryOptions' => ['' => 'Wszystkie kategorie'] + Category::query()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    private function hasStock(?int $warehouseId): \Closure
    {
        return fn ($q) => $q->selectRaw('1')->from('stocks')
            ->whereColumn('stocks.product_id', 'products.id')
            ->where('stocks.quantity', '!=', 0)
            ->when($warehouseId, fn ($w) => $w->where('stocks.warehouse_id', $warehouseId));
    }

    private function filtered(?int $warehouseId): Builder
    {
        $hasStock = $this->hasStock($warehouseId);

        return Product::query()
            ->search($this->search)
            ->when($this->category !== '', fn ($q) => $q->where('products.category_id', $this->category))
            ->when($this->low, fn ($q) => $q->belowMinimum())
            // Domyślnie tylko towary ze stanem; „Pokaż zerowe” dodaje aktywne towary bez stanu.
            ->when(! $this->zero && ! $this->low, fn ($q) => $q->whereExists($hasStock))
            ->when($this->zero || $this->low, fn ($q) => $q->where(fn ($w) => $w->where('products.is_active', true)->orWhereExists($hasStock)));
    }
}
