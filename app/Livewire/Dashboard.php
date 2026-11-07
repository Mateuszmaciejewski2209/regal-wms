<?php

namespace App\Livewire;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\Product;
use App\Models\ScanEvent;
use App\Models\Stock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Pulpit')]
class Dashboard extends Component
{
    public const CHART_DAYS = 14;

    public function getListeners(): array
    {
        return [
            'echo-private:warehouse,.document.changed' => '$refresh',
            'echo-private:scanner.'.auth()->id().',.scan.received' => '$refresh',
        ];
    }

    public function render()
    {
        return view('livewire.dashboard', [
            'stockValue' => (string) Stock::query()->sum('value'),
            'productCount' => Product::query()->active()->count(),
            'lowStock' => $this->lowStockProducts(),
            'draftCount' => Document::query()->drafts()->count(),
            'myDrafts' => Document::query()->drafts()
                ->where('created_by', auth()->id())
                ->with(['warehouse:id,code,name,color', 'contractor:id,name'])
                ->withCount('items')
                ->latest('updated_at')
                ->limit(5)
                ->get(),
            'todayCount' => Document::query()->where('status', DocumentStatus::Approved)->whereDate('approved_at', today())->count(),
            'recentDocuments' => Document::query()
                ->with(['warehouse:id,code,name,color', 'contractor:id,name'])
                ->latest('id')
                ->limit(8)
                ->get(),
            'recentScans' => ScanEvent::query()
                ->with(['product:id,name,sku', 'user:id,name', 'document:id,type,number'])
                ->latest('id')
                ->limit(8)
                ->get(),
            'chart' => $this->flowChart(),
        ]);
    }

    /** Towary, których łączny stan spadł poniżej minimum. */
    private function lowStockProducts(): Collection
    {
        return Product::query()
            ->active()
            ->belowMinimum()
            ->withSum('stocks as total_quantity', 'quantity')
            ->get()
            ->sortBy(fn (Product $p) => (float) ($p->total_quantity ?? 0) / max((float) $p->min_stock, 0.001))
            ->values();
    }

    /**
     * Wartość przyjęć (PZ, PW) i wydań (WZ, RW) dzień po dniu. MM pomijamy – nie zmienia stanu firmy.
     *
     * @return array{days: list<array{date: CarbonImmutable, in: float, out: float}>, max: float, totalIn: float, totalOut: float}
     */
    private function flowChart(): array
    {
        $from = CarbonImmutable::today()->subDays(self::CHART_DAYS - 1);

        $rows = DB::table('stock_movements')
            ->join('documents', 'documents.id', '=', 'stock_movements.document_id')
            ->where('stock_movements.created_at', '>=', $from)
            ->where('stock_movements.is_reversal', false)
            ->whereIn('documents.type', [DocumentType::PZ->value, DocumentType::PW->value, DocumentType::WZ->value, DocumentType::RW->value])
            ->groupBy('day')
            ->selectRaw('DATE(stock_movements.created_at) as day')
            ->selectRaw('SUM(CASE WHEN stock_movements.quantity > 0 THEN stock_movements.quantity * stock_movements.unit_price ELSE 0 END) as value_in')
            ->selectRaw('SUM(CASE WHEN stock_movements.quantity < 0 THEN -stock_movements.quantity * stock_movements.unit_price ELSE 0 END) as value_out')
            ->get()
            ->keyBy('day');

        $days = [];
        for ($i = 0; $i < self::CHART_DAYS; $i++) {
            $date = $from->addDays($i);
            $row = $rows->get($date->toDateString());
            $days[] = ['date' => $date, 'in' => (float) ($row->value_in ?? 0), 'out' => (float) ($row->value_out ?? 0)];
        }

        $max = max(1, ...array_map(fn ($d) => max($d['in'], $d['out']), $days));

        return [
            'days' => $days,
            'max' => $max,
            'totalIn' => array_sum(array_column($days, 'in')),
            'totalOut' => array_sum(array_column($days, 'out')),
        ];
    }
}
