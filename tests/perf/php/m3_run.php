<?php

/*
 * M3 – równoczesne zatwierdzanie dokumentów (prawdziwa współbieżność: osobne procesy PHP startujące w tej samej chwili).
 *
 *   php m3_run.php a <powtórzenia> <N szkiców WZ> <S stan początkowy>   # wyścig o ograniczony stan
 *   php m3_run.php b <powtórzenia>                                       # ten sam szkic zatwierdzany przez 2 procesy
 *
 * Dane: towar PERF-M3…, przyjęcie PZ (S szt., 12,34 zł) zatwierdzone przez kierownika, potem szkice WZ po 1 szt.
 * Weryfikacja wyłącznie przez Eloquent / DB facade (bez zapytań specyficznych dla silnika bazy).
 * Wynik: jedna linia JSON na stdout.
 */

require __DIR__.'/bootstrap.php';

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\Unit;
use App\Models\Contractor;
use App\Models\Document;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Documents\DocumentService;
use Illuminate\Support\Facades\DB;

$scenario = $argv[1] ?? 'a';
$runs = (int) ($argv[2] ?? 1);
$n = $scenario === 'a' ? (int) ($argv[3] ?? 20) : 2;
$s = $scenario === 'a' ? (string) ($argv[4] ?? '10') : '10';
$price = '12.34';
$stamp = date('His');

$service = app(DocumentService::class);
$manager = User::query()->where('email', 'kierownik@regal.test')->firstOrFail();
$storekeeper = User::query()->where('email', 'magazynier@regal.test')->firstOrFail();
$warehouse = Warehouse::query()->where('code', 'MG1')->firstOrFail();
$contractor = Contractor::query()->where('is_active', true)->orderBy('id')->firstOrFail();
@mkdir('/tmp/perf/out', 0777, true);

function draft(DocumentService $service, DocumentType $type, Warehouse $w, Contractor $c, User $author, string $notes, Product $p, string $qty, ?string $price = null): Document
{
    $doc = $service->createDraft($type, ['warehouse_id' => $w->id, 'contractor_id' => $c->id, 'notes' => $notes], $author);
    $service->addProduct($doc, $p, $qty, $price);

    return $doc;
}

/** @return list<array> wyniki procesów */
function race(array $docIds, int $userId, float $go): array
{
    $procs = [];
    foreach ($docIds as $i => $id) {
        $file = "/tmp/perf/out/w-{$i}-{$id}.json";
        @unlink($file);
        $procs[] = [
            'p' => proc_open(['php', '/tmp/perf/m3_worker.php', (string) $id, (string) $userId, sprintf('%.6f', $go)], [0 => ['file', '/dev/null', 'r'], 1 => ['file', $file, 'w'], 2 => ['file', $file.'.err', 'w']], $pipes),
            'file' => $file,
        ];
    }
    foreach ($procs as $proc) {
        proc_close($proc['p']); // czeka na zakończenie procesu
    }
    $results = [];
    foreach ($procs as $proc) {
        $lines = array_filter(explode("\n", trim((string) @file_get_contents($proc['file']))));
        $json = $lines ? json_decode(end($lines), true) : null;
        $results[] = $json ?? ['status' => 'no_output', 'stderr' => mb_substr((string) @file_get_contents($proc['file'].'.err'), 0, 2000)];
    }

    return $results;
}

function seqLast(string $type): int
{
    $today = now();

    return (int) (DB::table('document_sequences')->where(['type' => $type, 'year' => $today->year, 'month' => $today->month])->value('last_number') ?? 0);
}

function msStats(array $values): array
{
    sort($values);
    $c = count($values);
    if (! $c) {
        return ['n' => 0];
    }
    $pct = function (float $p) use ($values, $c) {
        $h = ($c - 1) * $p;
        $lo = (int) floor($h);
        $hi = (int) ceil($h);

        return round($values[$lo] + ($h - $lo) * ($values[$hi] - $values[$lo]), 2);
    };

    return ['n' => $c, 'min' => round($values[0], 2), 'median' => $pct(0.5), 'p95' => $pct(0.95), 'max' => round($values[$c - 1], 2)];
}

$report = ['scenario' => $scenario, 'runs' => [], 'n' => $n, 's' => $s, 'price' => $price, 'db_driver' => DB::connection()->getDriverName()];

for ($run = 1; $run <= $runs; $run++) {
    $product = Product::query()->create([
        'sku' => sprintf('PERF-M3%s-%s-R%02d', strtoupper($scenario), $stamp, $run),
        'name' => sprintf('PERF M3%s %s przebieg %02d', strtoupper($scenario), $stamp, $run),
        'unit' => Unit::Piece,
        'default_price' => $price,
        'min_stock' => 0,
        'vat_rate' => 23,
        'is_active' => true,
    ]);

    $pz = draft($service, DocumentType::PZ, $warehouse, $contractor, $storekeeper, "PERF M3{$scenario} PZ {$stamp}/{$run}", $product, $s, $price);
    $pz = $service->approve($pz, $manager);

    $wzIds = [];
    $draftCount = $scenario === 'a' ? $n : 1;
    for ($i = 1; $i <= $draftCount; $i++) {
        $wzIds[] = draft($service, DocumentType::WZ, $warehouse, $contractor, $storekeeper, "PERF M3{$scenario} WZ {$stamp}/{$run}/{$i}", $product, '1')->id;
    }
    $targets = $scenario === 'a' ? $wzIds : [$wzIds[0], $wzIds[0]];

    $seqBefore = seqLast('WZ');
    $go = microtime(true) + 3.0;
    $results = race($targets, $manager->id, $go);
    $seqAfter = seqLast('WZ');

    // ── weryfikacja ──
    $stock = Stock::query()->where(['product_id' => $product->id, 'warehouse_id' => $warehouse->id])->first();
    $movements = StockMovement::query()->where(['product_id' => $product->id, 'warehouse_id' => $warehouse->id])->orderBy('id')->get();
    $movementSum = '0';
    foreach ($movements as $m) {
        $movementSum = bcadd($movementSum, (string) $m->quantity, 3);
    }
    $minBalance = $movements->min(fn ($m) => (float) $m->balance_quantity);
    $minBalanceValue = $movements->min(fn ($m) => (float) $m->balance_value);
    $docs = Document::query()->whereIn('id', $wzIds)->get();
    $approvedDocs = $docs->where('status', DocumentStatus::Approved);
    $numbers = $approvedDocs->pluck('number')->filter()->values()->all();
    $seqNums = array_map(fn ($num) => (int) substr($num, -4), $numbers);
    sort($seqNums);
    $expectedNums = $approvedDocs->count() ? range($seqBefore + 1, $seqBefore + $approvedDocs->count()) : [];
    $wzMovements = StockMovement::query()->whereIn('document_id', $wzIds)->count();

    $byStatus = array_count_values(array_map(fn ($r) => $r['status'], $results));
    $messages = array_count_values(array_map(fn ($r) => $r['message'] ?? '', array_filter($results, fn ($r) => $r['status'] !== 'approved')));
    $tEnds = array_filter(array_map(fn ($r) => $r['t1'] ?? null, $results));
    $t0s = array_filter(array_map(fn ($r) => $r['t0'] ?? null, $results));

    $report['runs'][] = [
        'run' => $run,
        'product_sku' => $product->sku,
        'pz_number' => $pz->number,
        'document_ids' => $wzIds,
        'workers' => count($targets),
        'by_status' => $byStatus,
        'messages_non_approved' => $messages,
        'stock_final' => ['quantity' => (string) $stock?->quantity, 'value' => (string) $stock?->value],
        'movements' => [
            'count' => $movements->count(),
            'count_for_wz' => $wzMovements,
            'sum_quantity' => $movementSum,
            'sum_equals_stock' => bccomp($movementSum, (string) $stock?->quantity, 3) === 0,
            'min_balance_quantity' => $minBalance,
            'min_balance_value' => $minBalanceValue,
            'last_balance_value' => (string) $movements->last()?->balance_value,
        ],
        'documents' => [
            'approved_in_db' => $approvedDocs->count(),
            'draft_in_db' => $docs->where('status', DocumentStatus::Draft)->count(),
            'numbers' => array_values($numbers),
            'numbers_unique' => count($numbers) === count(array_unique($numbers)),
            'numbers_consecutive_no_gaps' => $seqNums === $expectedNums,
            'sequence_before' => $seqBefore,
            'sequence_after' => $seqAfter,
        ],
        'timing_ms' => [
            'start_spread' => $t0s ? round((max($t0s) - min($t0s)) * 1000, 3) : null,
            'wall_go_to_last_end' => $tEnds ? round((max($tEnds) - $go) * 1000, 3) : null,
            'min_ready_before_go' => min(array_map(fn ($r) => $r['ready_before_go_ms'] ?? -1, $results)),
            'approve_approved' => msStats(array_map(fn ($r) => $r['approve_ms'], array_filter($results, fn ($r) => $r['status'] === 'approved'))),
            'approve_rejected' => msStats(array_map(fn ($r) => $r['approve_ms'], array_filter($results, fn ($r) => $r['status'] === 'rejected'))),
            'slowest_query' => msStats(array_map(fn ($r) => $r['slowest_query_ms'] ?? 0, $results)),
        ],
        'workers_raw' => $results,
    ];
}

out($report);
