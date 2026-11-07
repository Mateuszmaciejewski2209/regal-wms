// M4 – kolejka offline PWA skanera: utrata sieci, skany w kolejce (localStorage), zmiana dokumentu, odzyskanie sieci.
//
//   node tests/perf/m4-offline-queue.mjs              # 3 powtórzenia, K = 10 kodów (5 do dokumentu A, 5 do B)
//   node tests/perf/m4-offline-queue.mjs --reps 1     # krótka wersja
//   opcje: --reps N --k K (parzyste)
//
// Przebieg jednego powtórzenia (telefon = kontekst mobilny Pixel 7 sparowany przez /skaner, kamera niedostępna → ręczne wpisywanie):
//  1. online: wybór dokumentu A w PWA; panel kierownika ma otwarte szkice A i B (dwie karty).
//  2. context.setOffline(true); K/2 kodów wpisanych ręcznie → „W kolejce”.
//  3. Obserwacja: próba otwarcia listy dokumentów offline (PWA pobiera ją z API).
//  4. Zmiana dokumentu na B: PWA wymaga do tego sieci (POST /api/scanner/target), więc sieć wraca na chwilę,
//     ale żądania POST /api/scanner/scans są w tym czasie zrywane (page.route → abort), żeby kolejka nie została wysłana;
//     po wyborze B znowu setOffline(true).
//  5. K/2 kolejnych kodów → kolejka K pozycji (document_id z chwili skanu).
//  6. setOffline(false) → pomiar: zdarzenie „online” → pusta kolejka w localStorage → wszystkie pozycje widoczne w panelu A i B.
//  7. Weryfikacja w bazie: pozycje A i B, ilości (brak duplikatów), dziennik skanów urządzenia.
import { clockOffset, copyPhpScripts, desktopContext, dockerPhp, launch, log, login, pairPhone, runStamp, saveCsv, saveJson, sleep, stats, USERS } from './lib/common.mjs';

const arg = (name, def) => {
    const i = process.argv.indexOf(`--${name}`);
    return i > -1 ? process.argv[i + 1] : def;
};
const REPS = Number(arg('reps', 3));
const K = Number(arg('k', 10));
const stamp = runStamp();

copyPhpScripts();
const browser = await launch();
const { ctx: phoneCtx, page: phone } = await pairPhone(browser, USERS.storekeeper, `PERF M4 ${stamp}`);
const managerCtx = await desktopContext(browser);
const loginPage = await managerCtx.newPage();
await login(loginPage, USERS.manager);
await loginPage.close();

const alpine = (fn, arg) => phone.evaluate(({ fn, arg }) => new Function('app', 'arg', `return (${fn})(app, arg)`)(window.Alpine.$data(document.querySelector('[x-data]')), arg), { fn: fn.toString(), arg });

async function chooseTarget(id) {
    await phone.getByRole('button', { name: 'Wybierz dokument docelowy' }).click();
    await phone.getByRole('button', { name: new RegExp(`PZ · szkic #${id}\\b`) }).click();
    await phone.waitForFunction((id) => window.Alpine.$data(document.querySelector('[x-data]')).target?.id === id, id);
}

async function manualScan(code) {
    await phone.getByRole('button', { name: 'Wpisz kod ręcznie' }).last().click();
    await phone.locator('#manual').fill(code);
    await phone.locator('#manual').press('Enter');
    await phone.locator('[aria-live="polite"] .code').filter({ hasText: code }).waitFor();
    await sleep(150);
}

const queue = () => phone.evaluate(() => JSON.parse(localStorage.getItem('regal.scanner.queue') ?? '[]'));

async function openPanel(id) {
    const p = await managerCtx.newPage();
    await p.goto(`/dokumenty/${id}`);
    await p.waitForFunction((id) => window.Echo?.connector?.pusher?.channel(`private-documents.${id}`)?.subscribed === true, id, { timeout: 20_000 });
    return p;
}

/** W panelu: chwila, gdy wszystkie oczekiwane pozycje mają ilość 1. */
async function watchPanel(p, names) {
    await p.evaluate((names) => {
        window.__done = new Promise((resolve) => {
            let done = false;
            const check = () => {
                if (done) return;
                const ok = names.every((n) => {
                    const el = [...document.querySelectorAll('input[aria-label]')].find((e) => e.getAttribute('aria-label') === `Ilość: ${n}`);
                    return el && Number(String(el.value).replace(',', '.')) === 1;
                });
                if (ok) {
                    done = true;
                    obs.disconnect();
                    clearInterval(timer);
                    resolve(performance.timeOrigin + performance.now());
                }
            };
            const obs = new MutationObserver(check);
            obs.observe(document.body, { subtree: true, childList: true, attributes: true, characterData: true });
            const timer = setInterval(check, 2);
        });
    }, names);
}

const reps = [];
for (let r = 1; r <= REPS; r++) {
    const prefix = `M4${stamp.slice(-6)}R${r}`;
    const products = dockerPhp('setup.php', ['products', prefix, K, '10.00']).products;
    const [docA, docB] = dockerPhp('setup.php', ['drafts', 2, 'PZ', 'MG1', USERS.storekeeper, `PERF M4 ${stamp} R${r}`]).ids;
    const firstHalf = products.slice(0, K / 2);
    const secondHalf = products.slice(K / 2);
    log(`powtórzenie ${r}: A=#${docA}, B=#${docB}`);

    await chooseTarget(docA);
    const panelA = await openPanel(docA);
    const panelB = await openPanel(docB);

    // 2. offline + pierwsza połowa
    await phoneCtx.setOffline(true);
    await phone.waitForFunction(() => window.Alpine.$data(document.querySelector('[x-data]')).online === false);
    for (const p of firstHalf) await manualScan(`RGL:${p.sku}`);
    const queuedA = await queue();

    // 3. obserwacja: lista dokumentów offline
    await phone.getByRole('button', { name: 'Wybierz dokument docelowy' }).click();
    await sleep(800);
    const offlineTargets = await alpine((app) => ({ documents: app.documents.length, result: app.result?.result, message: app.result?.message, target: app.target?.id }));
    await phone.keyboard.press('Escape');
    await sleep(300);

    // 4. zmiana dokumentu na B (sieć na chwilę, skany zrywane)
    let aborted = 0;
    await phone.route('**/api/scanner/scans', (route) => {
        aborted++;
        return route.abort('internetdisconnected');
    });
    await phoneCtx.setOffline(false);
    await phone.waitForFunction(() => window.Alpine.$data(document.querySelector('[x-data]')).online === true);
    await sleep(500);
    await chooseTarget(docB);
    await phoneCtx.setOffline(true);
    await phone.waitForFunction(() => window.Alpine.$data(document.querySelector('[x-data]')).online === false);
    await phone.unroute('**/api/scanner/scans');
    const queueAfterSwitch = await queue();

    // 5. druga połowa
    for (const p of secondHalf) await manualScan(`RGL:${p.sku}`);
    const queued = await queue();

    // 6. odzyskanie sieci – pomiar
    await watchPanel(panelA, firstHalf.map((p) => p.name));
    await watchPanel(panelB, secondHalf.map((p) => p.name));
    await phone.evaluate(() => {
        window.__online = new Promise((resolve) => window.addEventListener('online', (e) => resolve(performance.timeOrigin + e.timeStamp), { once: true }));
        window.__empty = new Promise((resolve) => {
            const timer = setInterval(() => {
                if (JSON.parse(localStorage.getItem('regal.scanner.queue') ?? '[]').length === 0) {
                    clearInterval(timer);
                    resolve(performance.timeOrigin + performance.now());
                }
            }, 2);
        });
    });
    const off = { phone: await clockOffset(phone), panelA: await clockOffset(panelA), panelB: await clockOffset(panelB) };
    await phoneCtx.setOffline(false);
    const timeout = (ms) => new Promise((res) => setTimeout(() => res(null), ms));
    const tOnline = await phone.evaluate(() => window.__online);
    const tEmpty = await phone.evaluate(async () => Promise.race([window.__empty, new Promise((r) => setTimeout(() => r(null), 20_000))]));
    const tA = await Promise.race([panelA.evaluate(() => window.__done), timeout(20_000)]);
    const tB = await Promise.race([panelB.evaluate(() => window.__done), timeout(20_000)]);
    await sleep(2000); // czas na ewentualne duplikaty
    const n = (t, o) => (t === null ? null : t - o.offset);
    const online = n(tOnline, off.phone);

    // 7. weryfikacja w bazie
    const docs = dockerPhp('setup.php', ['doc-items', docA, docB]).documents;
    const events = dockerPhp('setup.php', ['scan-events', `PERF M4 ${stamp}`]).events.filter((e) => [docA, docB].includes(e.document_id));
    const expectA = firstHalf.map((p) => p.sku).sort();
    const expectB = secondHalf.map((p) => p.sku).sort();
    const skus = (d) => d.items.map((i) => i.sku).sort();
    const row = {
        rep: r,
        doc_a: docA,
        doc_b: docB,
        queued_total: queued.length,
        queued_doc_a: queued.filter((q) => q.document_id === docA).length,
        queued_doc_b: queued.filter((q) => q.document_id === docB).length,
        queue_after_switch: queueAfterSwitch.length,
        aborted_scan_requests_during_switch: aborted,
        offline_target_list_documents: offlineTargets.documents,
        offline_target_list_result: offlineTargets.result,
        offline_target_list_message: offlineTargets.message,
        online_to_queue_empty_ms: tEmpty === null ? null : n(tEmpty, off.phone) - online,
        online_to_panel_a_ms: tA === null ? null : n(tA, off.panelA) - online,
        online_to_panel_b_ms: tB === null ? null : n(tB, off.panelB) - online,
        online_to_all_visible_ms: tA === null || tB === null ? null : Math.max(n(tA, off.panelA), n(tB, off.panelB)) - online,
        a_items_ok: JSON.stringify(skus(docs[docA])) === JSON.stringify(expectA),
        b_items_ok: JSON.stringify(skus(docs[docB])) === JSON.stringify(expectB),
        all_quantities_1: [...docs[docA].items, ...docs[docB].items].every((i) => Number(i.quantity) === 1),
        scan_events_added_a: events.filter((e) => e.document_id === docA && e.result === 'added').length,
        scan_events_added_b: events.filter((e) => e.document_id === docB && e.result === 'added').length,
        clock_offsets: JSON.stringify(off),
    };
    reps.push(row);
    log(`powtórzenie ${r}: kolejka pusta po ${row.online_to_queue_empty_ms?.toFixed(1)} ms, wszystko w panelu po ${row.online_to_all_visible_ms?.toFixed(1)} ms, A ok=${row.a_items_ok}, B ok=${row.b_items_ok}, ilości=1: ${row.all_quantities_1}`);
    await panelA.close();
    await panelB.close();
    await sleep(1000);
}
await browser.close();

const summary = {
    stamp,
    reps: REPS,
    k: K,
    online_to_queue_empty: stats(reps.map((r) => r.online_to_queue_empty_ms)),
    online_to_all_visible: stats(reps.map((r) => r.online_to_all_visible_ms)),
    all_correct: reps.every((r) => r.a_items_ok && r.b_items_ok && r.all_quantities_1 && r.scan_events_added_a === K / 2 && r.scan_events_added_b === K / 2),
    rows: reps,
};
console.log(JSON.stringify({ ...summary, rows: undefined }, null, 2));
saveCsv(`m4-${stamp}-reps.csv`, reps);
const file = saveJson(`m4-${stamp}-summary.json`, summary);
log(`zapisano ${file}`);
