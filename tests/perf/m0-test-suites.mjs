// M0 – jednorazowe uruchomienie pełnych zestawów testów: PHPUnit (php artisan test) i Playwright E2E (npm run test:e2e).
// Zapisuje surowe logi i podsumowanie (liczba testów, wynik, czas ściany) do tests/perf/results/.
//   node tests/perf/m0-test-suites.mjs
// PHP: zmienna PHP_BIN (domyślnie PHP 8.4 z WinGet). Testy PHPUnit używają SQLite w pamięci (phpunit.xml),
// więc nie dotykają bazy w Dockerze. E2E odtwarza bazę z danych startowych (tests/e2e/global-setup.js).
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { ROOT, RESULTS_DIR, nowMs, runStamp, saveJson, log } from './lib/common.mjs';

const PHP_BIN = process.env.PHP_BIN
    ?? 'C:/Users/rozek/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.NTS.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe/php.exe';
const stamp = runStamp();
fs.mkdirSync(RESULTS_DIR, { recursive: true });

function run(label, cmd, args, opts = {}) {
    log(`start: ${label}`);
    const t0 = nowMs();
    const res = spawnSync(cmd, args, { cwd: ROOT, encoding: 'utf8', maxBuffer: 128 * 1024 * 1024, shell: opts.shell ?? false, env: { ...process.env, ...opts.env } });
    const wallS = (nowMs() - t0) / 1000;
    const out = (res.stdout ?? '') + (res.stderr ?? '');
    const file = path.join(RESULTS_DIR, `m0-${label}-${stamp}.log`);
    fs.writeFileSync(file, out);
    log(`koniec: ${label} kod=${res.status} czas=${wallS.toFixed(1)} s`);
    return { exitCode: res.status, wallSeconds: Math.round(wallS * 10) / 10, log: path.relative(ROOT, file), out };
}

const strip = (s) => s.replace(/\x1b\[[0-9;]*m/g, '');

// 1. PHPUnit
const php = run('phpunit', PHP_BIN, ['artisan', 'test']);
const phpOut = strip(php.out);
// Bez terminala `php artisan test` wypisuje jedną linię JSON: {"tool":"phpunit","result":…,"tests":…}
const phpJson = phpOut.split(/\r?\n/).map((l) => l.trim()).filter((l) => l.startsWith('{"tool"')).pop();
const phpSummary = phpJson
    ? JSON.parse(phpJson)
    : {
          tests: phpOut.match(/Tests:\s+(.+)/)?.[1]?.trim() ?? null,
          duration: phpOut.match(/Duration:\s+(.+)/)?.[1]?.trim() ?? null,
      };

// 2. Playwright E2E (z resetem bazy w global-setup)
const e2e = run('e2e', process.platform === 'win32' ? 'npm.cmd run test:e2e' : 'npm run test:e2e', [], { shell: true });
const e2eOut = strip(e2e.out);
const e2eSummary = {
    passed: e2eOut.match(/(\d+) passed/)?.[1] ?? null,
    failed: e2eOut.match(/(\d+) failed/)?.[1] ?? null,
    flaky: e2eOut.match(/(\d+) flaky/)?.[1] ?? null,
    skipped: e2eOut.match(/(\d+) skipped/)?.[1] ?? null,
    reportedDuration: e2eOut.match(/\d+ passed \(([^)]+)\)/)?.[1] ?? null,
};

const result = {
    stamp,
    phpunit: { exitCode: php.exitCode, wallSeconds: php.wallSeconds, ...phpSummary, log: php.log },
    e2e: { exitCode: e2e.exitCode, wallSeconds: e2e.wallSeconds, ...e2eSummary, log: e2e.log },
};
console.log(JSON.stringify(result, null, 2));
saveJson(`m0-summary-${stamp}.json`, result);
