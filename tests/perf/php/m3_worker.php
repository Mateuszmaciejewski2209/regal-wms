<?php

/*
 * M3 – pojedynczy proces zatwierdzający. Uruchamiany równolegle przez m3_run.php (osobne procesy PHP = osobne połączenia z bazą).
 *
 *   php m3_worker.php <document_id> <user_id> <go_unix_time_float>
 *
 * Proces ładuje aplikację, wczytuje dokument i użytkownika (otwiera połączenie z bazą), czeka do wspólnej chwili „go”
 * i woła DocumentService::approve(). Tylko Eloquent / DB facade – bez zapytań zależnych od silnika bazy.
 */

require __DIR__.'/bootstrap.php';

use App\Exceptions\WarehouseException;
use App\Models\Document;
use App\Models\User;
use App\Services\Documents\DocumentService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

[$documentId, $userId, $go] = [(int) $argv[1], (int) $argv[2], (float) $argv[3]];

$document = Document::query()->findOrFail($documentId);
$user = User::query()->findOrFail($userId);
$service = app(DocumentService::class);

$marks = ['begin' => null, 'end' => null, 'end_kind' => null];
$slowest = ['ms' => 0.0, 'sql' => null];
$queries = 0;
Event::listen(TransactionBeginning::class, function () use (&$marks) {
    $marks['begin'] ??= microtime(true);
});
Event::listen(TransactionCommitted::class, function () use (&$marks) {
    $marks['end'] = microtime(true);
    $marks['end_kind'] = 'commit';
});
Event::listen(TransactionRolledBack::class, function () use (&$marks) {
    $marks['end'] = microtime(true);
    $marks['end_kind'] = 'rollback';
});
DB::listen(function (QueryExecuted $q) use (&$slowest, &$queries) {
    $queries++;
    if ($q->time > $slowest['ms']) {
        $slowest = ['ms' => $q->time, 'sql' => mb_substr($q->sql, 0, 160)];
    }
});

$ready = microtime(true);
while (microtime(true) < $go) {
    usleep(100);
}

$t0 = microtime(true);
$status = 'approved';
$message = null;
$number = null;
$errorClass = null;
$errorCode = null;

try {
    $approved = $service->approve($document, $user);
    $number = $approved->number;
} catch (WarehouseException $e) {
    $status = 'rejected';
    $message = $e->getMessage();
} catch (Throwable $e) {
    $status = 'error';
    $message = $e->getMessage();
    $errorClass = $e::class;
    $errorCode = (string) $e->getCode();
}
$t1 = microtime(true);

out([
    'document_id' => $documentId,
    'pid' => getmypid(),
    'status' => $status,
    'number' => $number,
    'message' => $message,
    'error_class' => $errorClass,
    'error_code' => $errorCode,
    'ready_before_go_ms' => round(($go - $ready) * 1000, 3),
    'start_lag_ms' => round(($t0 - $go) * 1000, 3),
    'approve_ms' => round(($t1 - $t0) * 1000, 3),
    'tx_ms' => $marks['begin'] && $marks['end'] ? round(($marks['end'] - $marks['begin']) * 1000, 3) : null,
    'tx_end' => $marks['end_kind'],
    'tx_end_at' => $marks['end'],
    'queries' => $queries,
    'slowest_query_ms' => round($slowest['ms'], 3),
    'slowest_query_sql' => $slowest['sql'],
    't0' => $t0,
    't1' => $t1,
]);
