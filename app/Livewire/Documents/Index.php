<?php

namespace App\Livewire\Documents;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Livewire\Concerns\WithPerPage;
use App\Models\Document;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Dokumenty')]
class Index extends Component
{
    use WithPerPage;

    #[Url]
    public string $type = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $warehouse = '';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function getListeners(): array
    {
        return ['echo-private:warehouse,.document.changed' => '$refresh'];
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['type', 'status', 'warehouse', 'search', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('status', 'warehouse', 'search', 'from', 'to');
        $this->resetPage();
    }

    public function render()
    {
        $base = $this->filtered(Document::query());

        $counts = (clone $base)
            ->reorder()
            ->selectRaw('type, COUNT(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type');

        $documents = (clone $base)
            ->ofType($this->type ?: null)
            ->with(['warehouse:id,code,name,color', 'targetWarehouse:id,code,name,color', 'contractor:id,name', 'author:id,name'])
            ->withCount('items')
            ->latest('issued_at')
            ->latest('id')
            ->paginate($this->perPage());

        return view('livewire.documents.index', [
            'documents' => $documents,
            'counts' => $counts,
            'types' => DocumentType::cases(),
            'statusOptions' => ['' => 'Każdy status'] + collect(DocumentStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all(),
            'warehouseOptions' => ['' => 'Wszystkie magazyny'] + Warehouse::query()->orderBy('code')->get()->mapWithKeys(fn ($w) => [$w->id => "{$w->code} · {$w->name}"])->all(),
            'hasFilters' => $this->status || $this->warehouse || $this->search || $this->from || $this->to,
        ]);
    }

    /** Filtry wspólne dla listy i liczników zakładek (bez filtra typu). */
    private function filtered(Builder $query): Builder
    {
        return $query
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->warehouse, fn ($q) => $q->where(fn ($w) => $w->where('warehouse_id', $this->warehouse)->orWhere('target_warehouse_id', $this->warehouse)))
            ->when($this->from, fn ($q) => $q->whereDate('issued_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('issued_at', '<=', $this->to))
            ->when(trim($this->search) !== '', function ($q) {
                $term = '%'.trim($this->search).'%';
                $q->where(fn ($s) => $s
                    ->whereLike('number', $term)
                    ->orWhereLike('external_number', $term)
                    ->orWhereLike('notes', $term)
                    ->orWhereHas('contractor', fn ($c) => $c->whereLike('name', $term)->orWhereLike('nip', $term)));
            });
    }
}
