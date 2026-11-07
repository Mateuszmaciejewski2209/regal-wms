// Odtworzenie bazy z danych startowych – dokładnie ta sama procedura co przed testami E2E (tests/e2e/global-setup.js):
// migrate:fresh --seed w kontenerze app + restart kolejki. Następnie sprawdzenie, że panel odpowiada.
//   node tests/perf/reset-db.mjs
import globalSetup from '../e2e/global-setup.js';
import { BASE_URL, log } from './lib/common.mjs';

delete process.env.E2E_SKIP_RESET;
process.chdir(new URL('../..', import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1'));
await globalSetup();

for (const path of ['/logowanie', '/skaner']) {
    const res = await fetch(BASE_URL + path);
    log(`${BASE_URL}${path} → HTTP ${res.status}`);
    if (res.status !== 200) process.exitCode = 1;
}
