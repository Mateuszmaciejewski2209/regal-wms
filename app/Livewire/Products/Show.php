<?php

namespace App\Livewire\Products;

use App\Livewire\Concerns\WithPerPage;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Layout\RackService;
use App\Support\Qr;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Karta towaru: dane, etykieta QR, stany per magazyn i kartoteka ruchów. */
class Show extends Component
{
    use WithPerPage;

    public Product $product;

    #[Url]
    public string $warehouse = '';

    public function getListeners(): array
    {
        return [
            'echo-private:warehouse,.document.changed' => '$refresh',
            'echo-private:warehouse,.layout.changed' => '$refresh',
        ];
    }

    public function updatedWarehouse(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $this->product->load(['category', 'stocks.warehouse']);

        $movements = $this->product->movements()
            ->with(['document:id,type,number,status,issued_at', 'warehouse:id,code,name,color'])
            ->when($this->warehouse, fn ($q) => $q->where('warehouse_id', $this->warehouse))
            ->latest('id')
            ->paginate($this->perPage());

        return view('livewire.products.show', [
            'qr' => Qr::svg($this->product->qrPayload(), 168),
            'locations' => app(RackService::class)->locationsOf($this->product),
            'movements' => $movements,
            'warehouses' => Warehouse::query()->orderBy('code')->get(['id', 'code']),
            'total' => (string) $this->product->stocks->sum('quantity'),
            'totalValue' => (string) $this->product->stocks->sum('value'),
        ])->title($this->product->name);
    }
}
