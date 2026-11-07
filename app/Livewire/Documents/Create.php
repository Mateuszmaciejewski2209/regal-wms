<?php

namespace App\Livewire\Documents;

use App\Enums\DocumentType;
use App\Exceptions\WarehouseException;
use App\Models\Contractor;
use App\Models\Warehouse;
use App\Services\Documents\DocumentService;
use App\Services\Scanner\ScannerService;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Create extends Component
{
    public string $type;

    public ?int $warehouse_id = null;

    public ?int $target_warehouse_id = null;

    public ?int $contractor_id = null;

    public string $issued_at = '';

    public string $external_number = '';

    public string $notes = '';

    public bool $prefill = true;

    public bool $attachScanner = true;

    public function mount(string $type): void
    {
        abort_unless(DocumentType::tryFrom($type), 404);

        $this->type = $type;
        $this->issued_at = now()->toDateString();
        $this->warehouse_id = Warehouse::query()->active()->orderBy('code')->value('id');
    }

    public function docType(): DocumentType
    {
        return DocumentType::from($this->type);
    }

    public function save(DocumentService $documents, ScannerService $scanner)
    {
        $type = $this->docType();

        $data = $this->validate([
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'target_warehouse_id' => [Rule::requiredIf($type->requiresTargetWarehouse()), 'nullable', 'different:warehouse_id', Rule::exists('warehouses', 'id')],
            'contractor_id' => [Rule::requiredIf($type->requiresContractor()), 'nullable', Rule::exists('contractors', 'id')],
            'issued_at' => ['required', 'date'],
            'external_number' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'target_warehouse_id.different' => 'Magazyn docelowy musi być inny niż źródłowy.',
        ]);

        try {
            $document = $documents->createDraft($type, $data + ['prefill' => $this->prefill], auth()->user());
        } catch (WarehouseException $e) {
            $this->addError('warehouse_id', $e->getMessage());

            return null;
        }

        if ($this->attachScanner && auth()->user()->can('scanner.use')) {
            $scanner->setTarget(auth()->user(), $document);
        }

        return $this->redirectRoute('documents.show', $document, navigate: true);
    }

    public function render(DocumentService $documents)
    {
        $type = $this->docType();

        $contractors = $type->requiresContractor()
            ? Contractor::query()
                ->where('is_active', true)
                ->when($type === DocumentType::PZ, fn ($q) => $q->suppliers(), fn ($q) => $q->customers())
                ->orderBy('name')
                ->get(['id', 'name', 'nip', 'city'])
            : collect();

        $lock = collect([$this->warehouse_id, $type->requiresTargetWarehouse() ? $this->target_warehouse_id : null])
            ->filter()
            ->map(fn ($id) => $documents->inventoryLock((int) $id))
            ->filter()
            ->first();

        return view('livewire.documents.create', [
            'docType' => $type,
            'warehouseOptions' => Warehouse::query()->active()->orderBy('code')->get()->mapWithKeys(fn ($w) => [$w->id => "{$w->code} · {$w->name}"])->all(),
            'contractorOptions' => $contractors->mapWithKeys(fn ($c) => [$c->id => $c->name.($c->nip ? " · NIP {$c->nip}" : '')])->all(),
            'lock' => $lock,
        ])->title('Nowy dokument '.$type->value);
    }
}
