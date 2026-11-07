// M3 – równoczesne zatwierdzanie dokumentów.
//
//   node tests/perf/m3-concurrent-approve.mjs                          # pełna wersja: (a) 5 × [S=10, N=20], (b) 5 × [2 procesy, 1 szkic]
//   node tests/perf/m3-concurrent-approve.mjs --runs-a 1 --runs-b 1    # krótka wersja
//   opcje: --runs-a N --runs-b N --n N --s S
//
// Orkiestrator (tests/perf/php/m3_run.php) działa w kontenerze app i uruchamia osobne procesy PHP (proc_open),
// które wczytują dokument, czekają do wspólnej chwili „go” (go = teraz + 3 s) i wołają DocumentService::approve().
// Każdy proces ma własne połączenie z bazą, więc blokady (SELECT … FOR UPDATE) działają jak przy równoległych żądaniach HTTP.
import { copyPhpScripts, dockerPhp, log, runStamp, saveCsv, saveJson } from './lib/common.mjs';

const arg = (name, def) => {
    const i = process.argv.indexOf(`--${name}`);
    return i > -1 ? process.argv[i + 1] : def;
};
const runsA = Number(arg('runs-a', 5));
const runsB = Number(arg('runs-b', 5));
const N = Number(arg('n', 20));
const S = Number(arg('s', 10));
const stamp = runStamp();

copyPhpScripts();
const out = { stamp };

for (const [scenario, runs, args] of [['a', runsA, [N, S]], ['b', runsB, []]]) {
    if (!runs) continue;
    log(`scenariusz ${scenario}: ${runs} powtórzeń`);
    const report = dockerPhp('m3_run.php', [scenario, runs, ...args]);
    out[scenario] = report;
    const rows = report.runs.flatMap((r) => r.workers_raw.map((w) => ({ run: r.run, ...w })));
    saveCsv(`m3${scenario}-${stamp}-workers.csv`, rows, ['run', 'document_id', 'pid', 'status', 'number', 'message', 'error_class', 'error_code', 'ready_before_go_ms', 'start_lag_ms', 'approve_ms', 'tx_ms', 'tx_end', 'queries', 'slowest_query_ms', 'slowest_query_sql']);
    for (const r of report.runs) {
        log(`${scenario}#${r.run}: ${JSON.stringify(r.by_status)} stan=${r.stock_final.quantity}/${r.stock_final.value} numery_ok=${r.documents.numbers_consecutive_no_gaps} suma_ruchów=stan:${r.movements.sum_equals_stock} wall=${r.timing_ms.wall_go_to_last_end} ms`);
    }
}

for (const s of ['a', 'b']) for (const r of out[s]?.runs ?? []) delete r.workers_raw; // surowe dane są w CSV
const file = saveJson(`m3-${stamp}-summary.json`, out);
log(`zapisano ${file}`);
