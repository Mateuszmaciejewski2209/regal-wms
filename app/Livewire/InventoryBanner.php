<?php

namespace App\Livewire;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Document;
use Livewire\Component;

/**
 * Pasek ostrzegawczy „Trwa inwentaryzacja” – znika na żywo po zatwierdzeniu lub usunięciu spisu.
 */
class InventoryBanner extends Component
{
    public function getListeners(): array
    {
        return [
            'echo-private:warehouse,.document.changed' => '$refresh',
            'scanner-target-changed' => '$refresh',
        ];
    }

    public function render()
    {
        return view('livewire.inventory-banner', [
            'inventories' => Document::query()
                ->with('warehouse:id,code,name,color')
                ->where('type', DocumentType::INW)
                ->where('status', DocumentStatus::Draft)
                ->get(),
        ]);
    }
}
