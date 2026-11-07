// M1 – czas odpowiedzi API skanu mierzony po stronie klienta (round trip w przeglądarce „telefonu”).
//
//   node tests/perf/m1-scan-latency.mjs                     # 200 × tryb „Dodaj”, 100 × „Sprawdź”, 50 × przez interfejs PWA
//   node tests/perf/m1-scan-latency.mjs --latency 50        # to samo z emulacją opóźnienia sieci (CDP), mniejsze próby
//   opcje: --add N --lookup N --ui N --interval ms --latency ms --tag nazwa
//
// Przebieg: dane PERF (20 towarów + szkic PZ) → parowanie telefonu (Pixel 7, formularz /skaner) → wybór szkicu w PWA
// → sekwencyjne POST /api/scanner/scans (ten sam fetch i nagłówki co api() w resources/js/scanner.js),
// tempo: start co --interval ms (domyślnie 300 ms ≈ 200/min, poniżej limitu 240/min).
// Czas = performance.now() przed fetch → po sparsowaniu JSON (round trip widziany przez kod PWA).
// Dodatkowo Resource Timing: TTFB (requestStart → responseStart) ≈ czas serwera + sieć lokalna.
// Wariant „ui”: ręczne wpisanie kodu w PWA; czas od zdarzenia submit formularza do pojawienia się wyniku w karcie wyniku.
import { BASE_URL, copyPhpScripts, dockerPhp, emulateNetwork, launch, log, pairPhone, runStamp, saveCsv, saveJson, stats, USERS, dockerLoadSnapshot } from './lib/common.mjs';

const arg = (name, def) => {
    const i = process.argv.indexOf(`--${name}`);
    return i > -1 ? process.argv[i + 1] : def;
};
const latency = Number(arg('latency', 0));
const ADD = Number(arg('add', latency ? 100 : 200));
const LOOKUP = Number(arg('lookup', latency ? 50 : 100));
const UI = Number(arg('ui', latency ? 30 : 50));
const INTERVAL = Number(arg('interval', 300));
const tag = arg('tag', latency ? `lat${latency}` : 'lan');
const stamp = runStamp();
const prefix = `M1${tag.toUpperCase().replace(/[^A-Z0-9]/g, '')}${stamp.slice(-6)}`;

copyPhpScripts();
const products = dockerPhp('setup.php', ['products', prefix, 20, '10.00']).products;
const draftId = dockerPhp('setup.php', ['drafts', 1, 'PZ', 'MG1', USERS.storekeeper, `PERF M1 ${tag} ${stamp}`]).ids[0];
const lookupSkus = dockerPhp('setup.php', ['lookup-skus', 20]).skus;
log(`szkic PZ #${draftId}, towary ${products[0].sku}…, sprawdzanie: ${lookupSkus.length} towarów z danych startowych`);

const browser = await launch();
const { page, token } = await pairPhone(browser, USERS.storekeeper, `PERF M1 ${tag}`);
log('telefon sparowany');

// Wybór szkicu w PWA (arkusz „Gdzie trafiają skany?”)
await page.getByRole('button', { name: 'Wybierz dokument docelowy' }).click();
await page.getByRole('button', { name: new RegExp(`PZ · szkic #${draftId}\\b`) }).click();
await page.waitForFunction((id) => {
    const el = document.querySelector('[x-data]');
    return window.Alpine?.$data(el)?.target?.id === id;
}, draftId);
log('dokument docelowy wybrany w PWA');

if (latency) {
    await emulateNetwork(page, { latency });
    log(`emulacja sieci CDP: latency ${latency} ms (tylko strona telefonu)`);
}

await page.evaluate(() => performance.setResourceTimingBufferSize(100000));

const loadBefore = dockerLoadSnapshot();

/** Seria żądań w stronie telefonu – pacing: start kolejnego żądania co `interval` ms. */
async function apiSeries(mode, codes, interval) {
    const rows = [];
    const chunk = 25;
    for (let off = 0; off < codes.length; off += chunk) {
        const part = await page.evaluate(
            async ({ mode, codes, interval, token, draftId, off }) => {
                const sleep = (ms) => new Promise((r) => setTimeout(r, Math.max(0, ms)));
                const out = [];
                let next = performance.now();
                for (let k = 0; k < codes.length; k++) {
                    await sleep(next - performance.now());
                    next = performance.now() + interval;
                    const payload = { code: codes[k], quantity: '1', mode };
                    if (mode === 'add') payload.document_id = draftId;
                    const t0 = performance.now();
                    let status = 0;
                    let data = {};
                    let error = null;
                    try {
                        const res = await fetch('/api/scanner/scans', {
                            method: 'POST',
                            headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
                            body: JSON.stringify(payload),
                        });
                        status = res.status;
                        data = await res.json().catch(() => ({}));
                    } catch (e) {
                        error = String(e);
                    }
                    const t1 = performance.now();
                    const rt = performance.getEntriesByType('resource').filter((e) => e.name.endsWith('/api/scanner/scans')).at(-1);
                    out.push({
                        i: off + k + 1,
                        mode,
                        code: codes[k],
                        status,
                        result: data.result ?? null,
                        rtt_ms: t1 - t0,
                        ttfb_ms: rt && rt.startTime >= t0 - 1 ? rt.responseStart - rt.requestStart : null,
                        new_connection: rt && rt.startTime >= t0 - 1 ? rt.connectEnd - rt.connectStart > 0 : null,
                        error,
                    });
                }
                return out;
            },
            { mode, codes: codes.slice(off, off + chunk), interval, token, draftId, off },
        );
        rows.push(...part);
        log(`${mode}: ${rows.length}/${codes.length}`);
    }
    return rows;
}

const addCodes = Array.from({ length: ADD }, (_, i) => `RGL:${products[i % products.length].sku}`);
const lookupCodes = Array.from({ length: LOOKUP }, (_, i) => `RGL:${lookupSkus[i % lookupSkus.length]}`);

const addRows = await apiSeries('add', addCodes, INTERVAL);
const lookupRows = await apiSeries('lookup', lookupCodes, INTERVAL);

// Wariant UI: ręczne wpisanie kodu w PWA → wynik w karcie (aria-live) – „wynik skanu na telefonie”.
await page.evaluate(() => {
    window.__perf = { submits: [], shown: [] };
    document.addEventListener('submit', (e) => window.__perf.submits.push({ t: e.timeStamp, code: document.getElementById('manual')?.value }), true);
});
const uiRows = [];
for (let i = 0; i < UI; i++) {
    const code = `RGL:${products[i % products.length].sku}`;
    const started = Date.now();
    await page.evaluate((code) => {
        const card = document.querySelector('[aria-live="polite"]');
        window.__perf.pending = new Promise((resolve) => {
            const check = () => {
                const shown = card.querySelector('.code')?.textContent?.trim();
                const label = card.querySelector('.badge')?.textContent?.trim();
                if (shown === code && label && label !== 'W kolejce') {
                    const t = performance.now();
                    obs.disconnect();
                    requestAnimationFrame(() => resolve({ t_dom: t, t_frame: performance.now(), label, text: card.textContent.replace(/\s+/g, ' ').trim().slice(0, 160) }));
                    return true;
                }
                return false;
            };
            const obs = new MutationObserver(check);
            obs.observe(card, { subtree: true, childList: true, characterData: true, attributes: true });
        });
    }, code);
    await page.getByRole('button', { name: 'Wpisz kod ręcznie' }).last().click();
    await page.locator('#manual').fill(code);
    await page.locator('#manual').press('Enter');
    const r = await page.evaluate(async () => {
        const shown = await window.__perf.pending;
        const submit = window.__perf.submits.at(-1);
        return { ...shown, t_submit: submit.t, submitted_code: submit.code };
    });
    uiRows.push({
        i: i + 1,
        mode: 'add-ui',
        code,
        result_label: r.label,
        submit_to_dom_ms: r.t_dom - r.t_submit,
        submit_to_frame_ms: r.t_frame - r.t_submit,
        card: r.text,
    });
    const wait = INTERVAL + 100 - (Date.now() - started);
    if (wait > 0) await new Promise((res) => setTimeout(res, wait));
}
log(`ui: ${uiRows.length}`);

const loadAfter = dockerLoadSnapshot();
const finalDoc = dockerPhp('setup.php', ['doc-items', draftId]).documents[draftId];
await browser.close();

const all = [...addRows, ...lookupRows];
const summary = {
    stamp,
    tag,
    base_url: BASE_URL,
    emulated_latency_ms: latency,
    interval_ms: INTERVAL,
    draft_id: draftId,
    add: { ...stats(addRows.filter((r) => r.status === 200).map((r) => r.rtt_ms)), ttfb: stats(addRows.map((r) => r.ttfb_ms)) },
    lookup: { ...stats(lookupRows.filter((r) => r.status === 200).map((r) => r.rtt_ms)), ttfb: stats(lookupRows.map((r) => r.ttfb_ms)) },
    ui_submit_to_result: { dom: stats(uiRows.map((r) => r.submit_to_dom_ms)), frame: stats(uiRows.map((r) => r.submit_to_frame_ms)) },
    statuses: all.reduce((m, r) => ((m[r.status] = (m[r.status] ?? 0) + 1), m), {}),
    results: all.reduce((m, r) => ((m[`${r.mode}:${r.result}`] = (m[`${r.mode}:${r.result}`] ?? 0) + 1), m), {}),
    ui_labels: uiRows.reduce((m, r) => ((m[r.result_label] = (m[r.result_label] ?? 0) + 1), m), {}),
    errors: all.filter((r) => r.error).length,
    new_connections: all.filter((r) => r.new_connection).length,
    over_1s: { api: all.filter((r) => r.rtt_ms >= 1000).length, ui: uiRows.filter((r) => r.submit_to_dom_ms >= 1000).length },
    final_document: { items: finalDoc.items.length, total_quantity: finalDoc.items.reduce((s, x) => s + Number(x.quantity), 0) },
    expected_total_quantity: ADD + UI,
    docker_load_before: loadBefore,
    docker_load_after: loadAfter,
};
console.log(JSON.stringify({ add: summary.add, lookup: summary.lookup, ui: summary.ui_submit_to_result, statuses: summary.statuses, results: summary.results, final: summary.final_document, expected: summary.expected_total_quantity }, null, 2));
saveCsv(`m1-${tag}-${stamp}-api.csv`, all, ['i', 'mode', 'code', 'status', 'result', 'rtt_ms', 'ttfb_ms', 'new_connection', 'error']);
saveCsv(`m1-${tag}-${stamp}-ui.csv`, uiRows, ['i', 'mode', 'code', 'result_label', 'submit_to_dom_ms', 'submit_to_frame_ms', 'card']);
const file = saveJson(`m1-${tag}-${stamp}-summary.json`, summary);
log(`zapisano ${file}`);
