// Rysunek 14 pracy: aplikacja skanera w trzech trybach, z symulowanym obrazem kamery.
// Chromium dostaje plik MJPEG jako „kamerę” (--use-file-for-fake-video-capture), więc kody są naprawdę
// odczytywane przez html5-qrcode z ramki celownika, a skan przechodzi przez API jak z telefonu.
//
// Uruchomienie (kontenery działają, baza zostaje zmieniona: parowanie, skany, szkic PZ):
//   node tests/screenshots/scanner-figure.mjs
// Wynik: tests/screenshots/out (albo katalog z FIG_OUT).
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';
import { BASE_URL, ROOT, USERS, desktopContext, login, pairPhone, sleep } from '../perf/lib/common.mjs';

const OUT = process.env.FIG_OUT ?? path.join(ROOT, 'tests', 'screenshots', 'out');
fs.mkdirSync(OUT, { recursive: true });

const PRODUCT_IDS = [1, 2, 3, 4, 5, 6, 7, 8, 9];
const CENTER_SKU = 'SRB-M8'; // etykieta w ramce; pozostałe osiem leży wokół niej
const RACK_ID = 2; // REG-02 w MG1
const LOCATION = 'REG-02-2-1';

// ───────────────────────── 1. Etykiety z aplikacji ─────────────────────────

async function captureLabels() {
    const browser = await chromium.launch();
    const ctx = await browser.newContext({ baseURL: BASE_URL, viewport: { width: 1200, height: 900 }, deviceScaleFactor: 3 });
    const page = await ctx.newPage();
    await login(page, USERS.admin);

    await page.goto('/etykiety?' + PRODUCT_IDS.map((id, i) => `ids[${i}]=${id}`).join('&'));
    const shot = async (locator) => (await locator.screenshot({ type: 'png', scale: 'device' })).toString('base64');
    const center = await shot(page.locator('.label', { hasText: CENTER_SKU }).first());
    const others = [];
    for (const label of await page.locator('.label').filter({ hasNotText: CENTER_SKU }).all()) {
        others.push(await shot(label));
    }

    await page.goto(`/etykiety/regal/${RACK_ID}`);
    const location = await shot(page.locator('.label', { hasText: LOCATION }).first());

    await browser.close();
    return { center, others: others.slice(0, 8), location };
}

// ───────────────────────── 2. Sceny „widziane przez kamerę” ─────────────────────────

const SCENE_CSS = `
  html, body { margin: 0; width: 720px; height: 1280px; overflow: hidden; }
  body { background: radial-gradient(ellipse at 50% 30%, #fbf7ef 0%, #e9e1d2 55%, #b9ae9c 100%); position: relative; }
  .sheet, .one { position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%) rotate(-2deg); }
  .sheet { display: grid; grid-template-columns: repeat(3, 440px); gap: 14px; }
  img { display: block; width: 440px; background: #fff; box-shadow: 0 10px 28px rgba(60, 45, 20, .28); border-radius: 4px; }
`;

async function renderScene(browser, html, file) {
    const page = await browser.newPage({ viewport: { width: 720, height: 1280 } });
    await page.setContent(`<style>${SCENE_CSS}</style>${html}`);
    await page.waitForLoadState('networkidle');
    const jpeg = await page.screenshot({ type: 'jpeg', quality: 92 });
    await page.close();
    fs.writeFileSync(path.join(OUT, file.replace('.mjpeg', '.jpg')), jpeg);
    fs.writeFileSync(path.join(OUT, file), Buffer.concat(Array(30).fill(jpeg))); // MJPEG = kolejne klatki JPEG
    return path.join(OUT, file);
}

async function makeScenes(labels) {
    const browser = await chromium.launch();
    const img = (b64) => `<img src="data:image/png;base64,${b64}">`;
    const product = await renderScene(browser, `<div class="one">${img(labels.center)}</div>`, 'scene-product.mjpeg');
    const grid = [...labels.others.slice(0, 4), labels.center, ...labels.others.slice(4, 8)];
    const sheet = await renderScene(browser, `<div class="sheet">${grid.map(img).join('')}</div>`, 'scene-sheet.mjpeg');
    const location = await renderScene(browser, `<div class="one">${img(labels.location)}</div>`, 'scene-location.mjpeg');
    await browser.close();
    return { product, sheet, location };
}

// ───────────────────────── 3. Telefon z symulowaną kamerą ─────────────────────────

async function withPhone(videoFile, fn) {
    const browser = await chromium.launch({
        args: ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream', `--use-file-for-fake-video-capture=${videoFile}`],
    });
    try {
        const { page, token } = await pairPhone(browser, USERS.admin, 'Pixel 7 (rysunek)');
        await page.getByRole('button', { name: 'Skanuj' }).waitFor();
        await page.waitForFunction(() => window.Alpine?.$data(document.querySelector('[x-data]')).camera === 'running', null, { timeout: 20_000 });
        return await fn(page, token, browser);
    } finally {
        await browser.close();
    }
}

async function setMode(page, name) {
    await page.getByRole('radio', { name }).click();
}

/** Naciśnięcie „Skanuj” i czekanie na kartę wyniku; zwraca odczytany kod. */
async function scan(page) {
    await page.getByRole('button', { name: 'Skanuj' }).click();
    await page.waitForFunction(() => window.Alpine?.$data(document.querySelector('[x-data]')).result, null, { timeout: 10_000 });
    await sleep(350); // koniec animacji karty i błysku
    return page.evaluate(() => window.Alpine.$data(document.querySelector('[x-data]')).result.code);
}

async function createDraftPz(browser) {
    const ctx = await desktopContext(browser);
    const page = await ctx.newPage();
    await login(page, USERS.admin);
    await page.goto('/dokumenty/nowy/PZ');
    await page.getByLabel('Dostawca').click();
    await page.getByRole('option').first().click();
    await page.getByRole('button', { name: 'Utwórz szkic' }).click();
    await page.waitForURL(/\/dokumenty\/\d+$/);
    const id = Number(page.url().match(/(\d+)$/)[1]);
    await ctx.close();
    return id;
}

async function setTarget(page, token, documentId) {
    await page.evaluate(async ({ token, documentId }) => {
        await fetch('/api/scanner/target', {
            method: 'POST',
            headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ document_id: documentId }),
        });
        await window.Alpine.$data(document.querySelector('[x-data]')).refreshMe();
    }, { token, documentId });
}

// ───────────────────────── 4. Złożenie rysunku ─────────────────────────

async function compose(shots) {
    const browser = await chromium.launch();
    const page = await browser.newPage({ viewport: { width: 2485, height: 1795 } });
    const cell = (file, caption) => `<figure><img src="data:image/png;base64,${fs.readFileSync(file).toString('base64')}"><figcaption>${caption}</figcaption></figure>`;
    await page.setContent(`<style>
        body { margin: 0; width: 2485px; height: 1795px; background: #fff; display: flex; justify-content: space-around; align-items: center; font-family: 'Segoe UI', sans-serif; }
        figure { margin: 0; display: flex; flex-direction: column; align-items: center; gap: 34px; }
        img { width: 740px; border: 12px solid #1f2937; border-radius: 64px; display: block; }
        figcaption { font-size: 46px; color: #111; }
    </style>
    ${cell(shots.lookup, 'a) tryb „Sprawdź”')}${cell(shots.add, 'b) tryb „Dodaj”')}${cell(shots.putaway, 'c) tryb „Rozmieść”')}`);
    await page.screenshot({ path: path.join(OUT, 'rysunek-14.png') });
    await browser.close();
}

// ───────────────────────── Przebieg ─────────────────────────

const labels = await captureLabels();
const scenes = await makeScenes(labels);
const shots = {};
const checks = {};

// Arkusz dziewięciu etykiet: odczytana ma być tylko środkowa (w ramce), mimo że sąsiednie kody są w kadrze.
checks.sheet = await withPhone(scenes.sheet, async (page) => {
    await setMode(page, 'Sprawdź');
    const codes = [];
    for (let i = 0; i < 5; i++) {
        codes.push(await scan(page));
        await page.evaluate(() => window.Alpine.$data(document.querySelector('[x-data]')).dismissResult());
    }
    return codes;
});

await withPhone(scenes.product, async (page, token, browser) => {
    await setMode(page, 'Sprawdź');
    checks.lookup = await scan(page);
    shots.lookup = path.join(OUT, 'skaner-sprawdz.png');
    await page.screenshot({ path: shots.lookup });

    const documentId = await createDraftPz(browser);
    checks.documentId = documentId; // szkic utworzony na potrzeby rysunku
    await setTarget(page, token, documentId);
    await setMode(page, 'Dodaj');
    await page.evaluate(() => window.Alpine.$data(document.querySelector('[x-data]')).dismissResult());
    checks.add = await scan(page);
    shots.add = path.join(OUT, 'skaner-dodaj.png');
    await page.screenshot({ path: shots.add });

    // Karta wyniku znika sama po kilku sekundach.
    await sleep(4600);
    checks.autoHide = await page.evaluate(() => window.Alpine.$data(document.querySelector('[x-data]')).result === null);
});

await withPhone(scenes.location, async (page) => {
    await setMode(page, 'Rozmieść');
    checks.putaway = await scan(page);
    shots.putaway = path.join(OUT, 'skaner-rozmiesc.png');
    await page.screenshot({ path: shots.putaway });
});

await compose(shots);
console.log(JSON.stringify({ checks, out: OUT }, null, 1));
