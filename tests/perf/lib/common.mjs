// Wspólne narzędzia pomiarów wydajności (tests/perf). Bez zależności poza Playwright z devDependencies.
import { execFileSync, spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { performance } from 'node:perf_hooks';
import { fileURLToPath } from 'node:url';
import { chromium, devices } from 'playwright';

export const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../..');
export const PERF_DIR = path.join(ROOT, 'tests', 'perf');
export const RESULTS_DIR = path.join(PERF_DIR, 'results');
export const BASE_URL = process.env.PERF_BASE_URL ?? 'http://127.0.0.1:8000';
export const PASSWORD = 'haslo1234';
export const USERS = {
    admin: 'admin@regal.test',
    manager: 'kierownik@regal.test',
    storekeeper: 'magazynier@regal.test',
    viewer: 'ksiegowosc@regal.test',
};

/** Czas ściany w ms z rozdzielczością ułamkową (ta sama podstawa co performance.timeOrigin w przeglądarce). */
export const nowMs = () => performance.timeOrigin + performance.now();
export const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

// ───────────────────────── Statystyka ─────────────────────────

/** Percentyl metodą interpolacji liniowej (R-7, jak numpy.percentile / Excel PERCENTYL.PRZEDZ.ZAMK). */
export function percentile(sorted, p) {
    if (!sorted.length) return null;
    const h = (sorted.length - 1) * p;
    const lo = Math.floor(h);
    const hi = Math.ceil(h);
    return sorted[lo] + (h - lo) * (sorted[hi] - sorted[lo]);
}

export function stats(values) {
    const v = values.filter((x) => Number.isFinite(x)).sort((a, b) => a - b);
    if (!v.length) return { n: 0 };
    const mean = v.reduce((s, x) => s + x, 0) / v.length;
    const r = (x) => Math.round(x * 100) / 100;
    return {
        n: v.length,
        min: r(v[0]),
        median: r(percentile(v, 0.5)),
        p95: r(percentile(v, 0.95)),
        max: r(v[v.length - 1]),
        mean: r(mean),
    };
}

// ───────────────────────── Zapis wyników ─────────────────────────

export function runStamp() {
    const d = new Date();
    const p = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}${p(d.getMonth() + 1)}${p(d.getDate())}-${p(d.getHours())}${p(d.getMinutes())}${p(d.getSeconds())}`;
}

export function saveJson(name, data) {
    fs.mkdirSync(RESULTS_DIR, { recursive: true });
    const file = path.join(RESULTS_DIR, name);
    fs.writeFileSync(file, JSON.stringify(data, null, 2));
    return file;
}

export function saveCsv(name, rows, columns = null) {
    fs.mkdirSync(RESULTS_DIR, { recursive: true });
    const cols = columns ?? (rows.length ? Object.keys(rows[0]) : []);
    const esc = (x) => {
        if (x === null || x === undefined) return '';
        const s = typeof x === 'number' ? String(Math.round(x * 1000) / 1000) : String(x);
        return /[",\n;]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
    };
    const lines = [cols.join(','), ...rows.map((r) => cols.map((c) => esc(r[c])).join(','))];
    const file = path.join(RESULTS_DIR, name);
    fs.writeFileSync(file, lines.join('\n') + '\n');
    return file;
}

export function log(...args) {
    console.log(`[${new Date().toLocaleTimeString('pl-PL')}]`, ...args);
}

// ───────────────────────── PHP w kontenerze ─────────────────────────

function appContainer() {
    return execFileSync('docker', ['compose', 'ps', '-q', 'app'], { cwd: ROOT, encoding: 'utf8' }).trim();
}

/** Kopiuje skrypty tests/perf/php do /tmp/perf w kontenerze app (kod aplikacji pozostaje nietknięty). */
export function copyPhpScripts() {
    const id = appContainer();
    execFileSync('docker', ['exec', id, 'mkdir', '-p', '/tmp/perf'], { cwd: ROOT });
    for (const f of fs.readdirSync(path.join(PERF_DIR, 'php'))) {
        execFileSync('docker', ['cp', path.join(PERF_DIR, 'php', f), `${id}:/tmp/perf/${f}`], { cwd: ROOT });
    }
}

/** Uruchamia skrypt PHP w kontenerze; ostatnia linia stdout to JSON z wynikiem. */
export function dockerPhp(script, args = [], { timeout = 600_000 } = {}) {
    const res = spawnSync('docker', ['compose', 'exec', '-T', 'app', 'php', `/tmp/perf/${script}`, ...args.map(String)], {
        cwd: ROOT,
        encoding: 'utf8',
        timeout,
        maxBuffer: 64 * 1024 * 1024,
    });
    if (res.status !== 0) {
        throw new Error(`php ${script} ${args.join(' ')} → kod ${res.status}\n${res.stderr}\n${res.stdout}`);
    }
    const lines = res.stdout.trim().split('\n');
    return JSON.parse(lines[lines.length - 1]);
}

// ───────────────────────── Przeglądarka ─────────────────────────

export async function launch() {
    // Bez uprawnienia do kamery: PWA pokazuje komunikat o kamerze, ręczne wpisywanie kodu działa.
    return chromium.launch({ headless: process.env.PERF_HEADED ? false : true });
}

export async function desktopContext(browser) {
    return browser.newContext({ baseURL: BASE_URL, locale: 'pl-PL', timezoneId: 'Europe/Warsaw', viewport: { width: 1440, height: 900 } });
}

export async function mobileContext(browser) {
    return browser.newContext({ ...devices['Pixel 7'], baseURL: BASE_URL, locale: 'pl-PL', timezoneId: 'Europe/Warsaw' });
}

export async function login(page, email) {
    await page.goto('/logowanie');
    await page.getByLabel('E-mail').fill(email);
    await page.getByLabel('Hasło').fill(PASSWORD);
    await page.getByRole('button', { name: 'Zaloguj' }).click();
    await page.getByRole('heading', { level: 1 }).filter({ hasText: 'Dzień dobry' }).waitFor({ timeout: 20_000 });
}

/** Kod parowania z panelu (/skanery → „Sparuj telefon”) – tak samo jak w tests/e2e/scanner.spec.js. */
export async function pairingCode(browser, email) {
    const ctx = await desktopContext(browser);
    const page = await ctx.newPage();
    await login(page, email);
    await page.goto('/skanery');
    await page.getByRole('button', { name: 'Sparuj telefon' }).first().click();
    const dialog = page.getByRole('dialog', { name: 'Sparuj telefon' });
    const codeEl = dialog.locator('.code').filter({ hasText: /^[A-Z0-9]{4}-[A-Z0-9]{4}$/ }).first();
    await codeEl.waitFor();
    const code = (await codeEl.innerText()).trim();
    return { code, ctx, page };
}

/**
 * Paruje „telefon” (kontekst mobilny Pixel 7) przez formularz PWA /skaner – prawdziwy przepływ aplikacji.
 * Zwraca stronę na ekranie skanowania i token urządzenia z localStorage.
 */
export async function pairPhone(browser, email, deviceName) {
    const { code, ctx: panelCtx } = await pairingCode(browser, email);
    const ctx = await mobileContext(browser);
    const page = await ctx.newPage();
    await page.goto('/skaner');
    await page.locator('#pair-code').fill(code);
    await page.locator('#device-name').fill(deviceName);
    // Limiter API skanera liczy żądania per adres IP (wspólnie z /pair: 10/min) – po serii skanów z tego samego
    // hosta parowanie dostaje 429 „Too Many Attempts.”. Kod parowania pozostaje ważny, więc ponawiamy po przerwie.
    for (let attempt = 1; ; attempt++) {
        await page.getByRole('button', { name: 'Połącz' }).click();
        try {
            await page.getByRole('button', { name: 'Wpisz kod ręcznie' }).last().waitFor({ timeout: 15_000 });
            break;
        } catch (e) {
            const state = await page.evaluate(() => {
                const app = window.Alpine?.$data(document.querySelector('[x-data]'));
                return { screen: app?.screen, camera: app?.camera, pairError: app?.pairError, token: !!app?.token };
            });
            if (attempt < 5 && /Too Many Attempts/i.test(state.pairError ?? '')) {
                log(`parowanie: 429 Too Many Attempts (próba ${attempt}) – ponawiam za 20 s`);
                await sleep(20_000);
                continue;
            }
            throw new Error(`Parowanie nie powiodło się (kod ${code}): ${JSON.stringify(state)}`);
        }
    }
    const token = await page.evaluate(() => JSON.parse(localStorage.getItem('regal.scanner.token')));
    await panelCtx.close();
    if (!token) throw new Error('Parowanie nie zwróciło tokenu');
    return { ctx, page, token };
}

/**
 * Przesunięcie zegara strony względem zegara Node (metoda jak w NTP: próbka o najkrótszym RTT).
 * t_node = t_strony - offset. Błąd ≤ rtt/2.
 */
export async function clockOffset(page, samples = 15) {
    let best = null;
    for (let i = 0; i < samples; i++) {
        const a = nowMs();
        const b = await page.evaluate(() => performance.timeOrigin + performance.now());
        const c = nowMs();
        const s = { offset: b - (a + c) / 2, rtt: c - a };
        if (!best || s.rtt < best.rtt) best = s;
    }
    return { offset: Math.round(best.offset * 1000) / 1000, rtt: Math.round(best.rtt * 1000) / 1000 };
}

/** Emulacja sieci przez CDP (Network.emulateNetworkConditions) – tylko dla danej strony. */
export async function emulateNetwork(page, { latency = 0, download = -1, upload = -1 } = {}) {
    const cdp = await page.context().newCDPSession(page);
    await cdp.send('Network.enable');
    await cdp.send('Network.emulateNetworkConditions', {
        offline: false,
        latency,
        downloadThroughput: download,
        uploadThroughput: upload,
    });
    return cdp;
}

export function dockerLoadSnapshot() {
    try {
        return execFileSync('docker', ['stats', '--no-stream', '--format', '{{.Name}};{{.CPUPerc}};{{.MemUsage}}'], { encoding: 'utf8', timeout: 60_000 })
            .trim()
            .split('\n')
            .map((l) => {
                const [name, cpu, mem] = l.split(';');
                return { name, cpu, mem };
            });
    } catch (e) {
        return [{ error: String(e.message) }];
    }
}
