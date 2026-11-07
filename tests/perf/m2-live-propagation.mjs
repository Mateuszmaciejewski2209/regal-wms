// M2 – propagacja zmian na żywo między użytkownikami (WebSocket Reverb + Livewire).
//
//   node tests/perf/m2-live-propagation.mjs                    # 40 skanów (20 nowych wierszy + 20 zmian ilości) + 15 zatwierdzeń
//   node tests/perf/m2-live-propagation.mjs --latency 50       # emulacja opóźnienia sieci telefonu (CDP), 30 skanów, bez zatwierdzeń
//   opcje: --reps N (parzysta) --approvals N --pause ms --latency ms --tag nazwa
//
// A) Skan → panel: „telefon” magazyniera (kontekst mobilny, sparowany przez /skaner) wysyła POST /api/scanner/scans;
//    kierownik ma otwarty ten szkic w panelu (/dokumenty/{id}). t0 = tuż przed fetch w stronie telefonu,
//    t1 = chwila, gdy w panelu kierownika pole „Ilość: <towar>” istnieje i ma oczekiwaną wartość (MutationObserver + kontrola co 2 ms).
//    Dodatkowo t_ws = odebranie zdarzenia items.changed przez Echo/Pusher w panelu (przed odświeżeniem Livewire).
// B) Zatwierdzenie → lista: kierownik zatwierdza szkic w panelu (kliknięcie „Zatwierdź” w oknie potwierdzenia, t0 = event.timeStamp),
//    magazynier ma otwartą listę /dokumenty; t1 = wiersz dokumentu na liście pokazuje status „Zatwierdzony”.
// Zegary stron są różne – każdą chwilę przeliczamy na zegar Node (przesunięcie mierzone jak w NTP, próbka o min. RTT).
import { BASE_URL, clockOffset, copyPhpScripts, desktopContext, dockerPhp, emulateNetwork, launch, log, login, pairPhone, runStamp, saveCsv, saveJson, sleep, stats, USERS } from './lib/common.mjs';

const arg = (name, def) => {
    const i = process.argv.indexOf(`--${name}`);
    return i > -1 ? process.argv[i + 1] : def;
};
const latency = Number(arg('latency', 0));
const REPS = Number(arg('reps', latency ? 30 : 40));
const APPROVALS = Number(arg('approvals', latency ? 0 : 15));
const PAUSE = Number(arg('pause', 1200));
const tag = arg('tag', latency ? `lat${latency}` : 'lan');
const stamp = runStamp();
const prefix = `M2${tag.toUpperCase().replace(/[^A-Z0-9]/g, '')}${stamp.slice(-6)}`;
const half = REPS / 2;

copyPhpScripts();
const products = dockerPhp('setup.php', ['products', prefix, half, '10.00']).products;
const liveDraft = dockerPhp('setup.php', ['drafts', 1, 'PZ', 'MG1', USERS.storekeeper, `PERF M2 ${tag} ${stamp}`]).ids[0];
const approvalDrafts = APPROVALS
    ? dockerPhp('setup.php', ['drafts', APPROVALS, 'PZ', 'MG1', USERS.storekeeper, `PERF M2 zatwierdzenie ${stamp}`, `${products[0].sku}=1`]).ids
    : [];
log(`szkic na żywo #${liveDraft}, szkice do zatwierdzenia: ${approvalDrafts.join(', ') || '—'}`);

const browser = await launch();
const { page: phone, token } = await pairPhone(browser, USERS.storekeeper, `PERF M2 ${tag}`);
await phone.getByRole('button', { name: 'Wybierz dokument docelowy' }).click();
await phone.getByRole('button', { name: new RegExp(`PZ · szkic #${liveDraft}\\b`) }).click();
await phone.waitForFunction((id) => window.Alpine?.$data(document.querySelector('[x-data]'))?.target?.id === id, liveDraft);
if (latency) await emulateNetwork(phone, { latency });

const managerCtx = await desktopContext(browser);
const panel = await managerCtx.newPage();
await login(panel, USERS.manager);
await panel.goto(`/dokumenty/${liveDraft}`);
await panel.waitForFunction((id) => window.Echo?.connector?.pusher?.channel(`private-documents.${id}`)?.subscribed === true, liveDraft, { timeout: 20_000 });
await panel.evaluate((id) => {
    window.__ws = [];
    window.Echo.connector.pusher.channel(`private-documents.${id}`).bind_global((event) => {
        if (!event.startsWith('pusher:')) window.__ws.push({ event, t: performance.timeOrigin + performance.now() });
    });
}, liveDraft);
log('panel kierownika otwarty, kanał private-documents zasubskrybowany');

const offsets = { start: { phone: await clockOffset(phone), panel: await clockOffset(panel) } };

// ───────────── A) skan → panel ─────────────
const scanRows = [];
for (let i = 0; i < REPS; i++) {
    const product = products[i % half];
    const expected = i < half ? 1 : 2;
    const kind = i < half ? 'new_row' : 'qty_change';

    await panel.evaluate(({ name, expected }) => {
        const label = `Ilość: ${name}`;
        window.__wsMark = window.__ws.length;
        window.__seen = new Promise((resolve) => {
            let done = false;
            const check = () => {
                if (done) return;
                const input = [...document.querySelectorAll('input[aria-label]')].find((el) => el.getAttribute('aria-label') === label);
                if (input && Number(String(input.value).replace(',', '.')) === expected) {
                    done = true;
                    const t = performance.timeOrigin + performance.now();
                    obs.disconnect();
                    clearInterval(timer);
                    resolve(t);
                }
            };
            const obs = new MutationObserver(check);
            obs.observe(document.body, { subtree: true, childList: true, attributes: true, characterData: true });
            const timer = setInterval(check, 2);
        });
    }, { name: product.name, expected });

    const sent = await phone.evaluate(
        async ({ code, token, docId }) => {
            const t0 = performance.timeOrigin + performance.now();
            const res = await fetch('/api/scanner/scans', {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
                body: JSON.stringify({ code, quantity: '1', mode: 'add', document_id: docId }),
            });
            const data = await res.json().catch(() => ({}));
            return { t0, t_resp: performance.timeOrigin + performance.now(), status: res.status, result: data.result, item_quantity: data.item_quantity };
        },
        { code: `RGL:${product.sku}`, token, docId: liveDraft },
    );

    const seen = await panel.evaluate(async () => {
        const t1 = await Promise.race([window.__seen, new Promise((r) => setTimeout(() => r(null), 15_000))]);
        const ws = window.__ws.slice(window.__wsMark).find((e) => e.event === 'items.changed');
        return { t1, t_ws: ws?.t ?? null };
    });

    const toNodePhone = (t) => t - offsets.start.phone.offset;
    const toNodePanel = (t) => (t === null ? null : t - offsets.start.panel.offset);
    const t0 = toNodePhone(sent.t0);
    scanRows.push({
        i: i + 1,
        kind,
        sku: product.sku,
        status: sent.status,
        result: sent.result,
        item_quantity: sent.item_quantity,
        phone_rtt_ms: sent.t_resp - sent.t0,
        t0_to_ws_ms: seen.t_ws === null ? null : toNodePanel(seen.t_ws) - t0,
        t0_to_panel_ms: seen.t1 === null ? null : toNodePanel(seen.t1) - t0,
        ws_to_panel_ms: seen.t1 === null || seen.t_ws === null ? null : seen.t1 - seen.t_ws,
    });
    log(`skan ${i + 1}/${REPS} ${kind}: panel po ${scanRows.at(-1).t0_to_panel_ms?.toFixed(1)} ms`);
    await sleep(PAUSE);
}
offsets.after_scans = { phone: await clockOffset(phone), panel: await clockOffset(panel) };

// ───────────── B) zatwierdzenie → lista u drugiego użytkownika ─────────────
const approvalRows = [];
if (approvalDrafts.length) {
    const skCtx = await desktopContext(browser);
    const list = await skCtx.newPage();
    await login(list, USERS.storekeeper);
    await list.goto('/dokumenty');
    await list.waitForFunction(() => window.Echo?.connector?.pusher?.channel('private-warehouse')?.subscribed === true, null, { timeout: 20_000 });
    offsets.list_start = await clockOffset(list);
    offsets.manager_start = await clockOffset(panel);

    for (let k = 0; k < approvalDrafts.length; k++) {
        const id = approvalDrafts[k];
        await panel.goto(`/dokumenty/${id}`);
        await panel.getByRole('heading', { level: 1 }).filter({ hasText: 'szkic' }).waitFor();
        await panel.waitForFunction(() => window.Echo?.connector?.pusher?.channel('private-warehouse')?.subscribed === true, null, { timeout: 20_000 });
        const present = await list.evaluate((id) => {
            const row = document.querySelector(`[wire\\:key="doc-${id}"]`);
            if (!row) return false;
            window.__approved = new Promise((resolve) => {
                let done = false;
                const check = () => {
                    const r = document.querySelector(`[wire\\:key="doc-${id}"]`);
                    if (!done && r && r.textContent.includes('Zatwierdzony')) {
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
            return true;
        }, id);
        if (!present) {
            approvalRows.push({ k: k + 1, document_id: id, error: 'szkic nie jest widoczny na pierwszej stronie listy' });
            continue;
        }
        await panel.evaluate(() => {
            window.__clicks = [];
            document.addEventListener('click', (e) => {
                const b = e.target.closest('button');
                if (b && b.textContent.trim() === 'Zatwierdź' && b.closest('[role="dialog"]')) window.__clicks.push(performance.timeOrigin + e.timeStamp);
            }, { capture: true });
            window.__self = new Promise((resolve) => {
                const check = () => {
                    const h = document.querySelector('h1');
                    if (h && /^PZ\/\d{4}\/\d{2}\/\d{4}$/.test(h.textContent.trim())) {
                        obs.disconnect();
                        clearInterval(timer);
                        resolve(performance.timeOrigin + performance.now());
                    }
                };
                const obs = new MutationObserver(check);
                obs.observe(document.body, { subtree: true, childList: true, characterData: true });
                const timer = setInterval(check, 2);
            });
        });
        await panel.getByRole('button', { name: 'Zatwierdź' }).first().click();
        const dialog = panel.getByRole('dialog', { name: 'Zatwierdzić dokument?' });
        await dialog.getByRole('button', { name: 'Zatwierdź' }).click();
        const tSelf = await panel.evaluate(() => Promise.race([window.__self, new Promise((r) => setTimeout(() => r(null), 15_000))]));
        const tClick = await panel.evaluate(() => window.__clicks.at(-1) ?? null);
        const tList = await list.evaluate(() => Promise.race([window.__approved, new Promise((r) => setTimeout(() => r(null), 15_000))]));
        const t0 = tClick - offsets.manager_start.offset;
        approvalRows.push({
            k: k + 1,
            document_id: id,
            click_to_own_page_ms: tSelf === null ? null : tSelf - offsets.manager_start.offset - t0,
            click_to_other_user_list_ms: tList === null ? null : tList - offsets.list_start.offset - t0,
        });
        log(`zatwierdzenie ${k + 1}/${approvalDrafts.length}: lista magazyniera po ${approvalRows.at(-1).click_to_other_user_list_ms?.toFixed(1)} ms`);
        await sleep(PAUSE);
    }
    offsets.list_end = await clockOffset(list);
    offsets.manager_end = await clockOffset(panel);
}

const finalDoc = dockerPhp('setup.php', ['doc-items', liveDraft]).documents[liveDraft];
await browser.close();

const byKind = (kind) => scanRows.filter((r) => kind === 'all' || r.kind === kind);
const summary = {
    stamp,
    tag,
    base_url: BASE_URL,
    emulated_latency_ms_on_phone: latency,
    live_draft_id: liveDraft,
    clock_offsets_ms: offsets,
    scan_to_panel: {
        all: stats(byKind('all').map((r) => r.t0_to_panel_ms)),
        new_row: stats(byKind('new_row').map((r) => r.t0_to_panel_ms)),
        qty_change: stats(byKind('qty_change').map((r) => r.t0_to_panel_ms)),
    },
    scan_to_ws_event: stats(scanRows.map((r) => r.t0_to_ws_ms)),
    ws_to_panel_dom: stats(scanRows.map((r) => r.ws_to_panel_ms)),
    phone_rtt: stats(scanRows.map((r) => r.phone_rtt_ms)),
    missed: scanRows.filter((r) => r.t0_to_panel_ms === null).length,
    approval_to_other_user_list: stats(approvalRows.map((r) => r.click_to_other_user_list_ms)),
    approval_to_own_page: stats(approvalRows.map((r) => r.click_to_own_page_ms)),
    approval_errors: approvalRows.filter((r) => r.error || r.click_to_other_user_list_ms === null).length,
    final_document: finalDoc,
};
console.log(JSON.stringify({ scan_to_panel: summary.scan_to_panel, ws: summary.scan_to_ws_event, ws_to_dom: summary.ws_to_panel_dom, approval: summary.approval_to_other_user_list, missed: summary.missed, offsets }, null, 2));
saveCsv(`m2-${tag}-${stamp}-scans.csv`, scanRows);
if (approvalRows.length) saveCsv(`m2-${tag}-${stamp}-approvals.csv`, approvalRows, ['k', 'document_id', 'click_to_own_page_ms', 'click_to_other_user_list_ms', 'error']);
const file = saveJson(`m2-${tag}-${stamp}-summary.json`, summary);
log(`zapisano ${file}`);
