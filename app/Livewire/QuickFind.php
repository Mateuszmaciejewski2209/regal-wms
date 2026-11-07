<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\ProductLocation;
use Livewire\Component;

/**
 * „Gdzie to leży?” – szybkie wyszukiwanie towaru z każdego ekranu (Ctrl+K lub /):
 * stan w magazynach i półki, bez przechodzenia przez kartotekę.
 */
class QuickFind extends Component
{
    public string $query = '';

    public function render()
    {
        $products = mb_strlen(trim($this->query)) >= 2
            ? Product::query()
                ->search($this->query)
                ->with(['stocks.warehouse:id,code,name,color', 'locations.rack:id,code,warehouse_id', 'locations.rack.warehouse:id,code,name,color'])
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->limit(6)
                ->get()
            : collect();

        return view('livewire.quick-find', [
            'products' => $products,
            'codeOf' => fn (ProductLocation $l) => ProductLocation::code($l->rack, $l->level, $l->bay),
        ]);
    }
}
