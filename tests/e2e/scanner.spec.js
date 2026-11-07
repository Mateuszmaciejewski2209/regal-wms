import { test, expect } from '@playwright/test';
import { login } from './helpers.js';

/*
 * Telefon jako skaner: parowanie z panelu, skan przez API (jak robi to PWA)
 * i aktualizacja otwartego dokumentu na żywo przez WebSocket.
 */
test.describe('Skaner mobilny', () => {
    test('parowanie → brak dokumentu = błąd → skan do dokumentu na żywo → odłożenie na półkę', async ({ page }) => {
        await login(page, 'admin');
        await page.goto('/skanery');
        await page.getByRole('button', { name: 'Sparuj telefon' }).first().click();

        const dialog = page.getByRole('dialog', { name: 'Sparuj telefon' });
        const code = (await dialog.locator('.code').filter({ hasText: /^[A-Z0-9]{4}-[A-Z0-9]{4}$/ }).first().innerText()).trim();

        // Telefon wymienia kod na token (to samo żądanie wysyła aplikacja /skaner)
        const paired = await page.request.post('/api/scanner/pair', { data: { code, name: 'Telefon E2E' } });
        expect(paired.status()).toBe(201);
        const token = (await paired.json()).token;
        const api = (method, url, data) => page.request[method](url, { data, headers: { Authorization: `Bearer ${token}` } });

        await expect(dialog).toBeHidden({ timeout: 15_000 }); // okno zamyka się samo
        await expect(page.getByLabel('Moje urządzenia').getByRole('listitem').filter({ hasText: 'Telefon E2E' }).first()).toBeVisible();

        // Bez wybranego dokumentu skan NIE jest sukcesem
        await api('post', '/api/scanner/target', { document_id: null });
        const noTarget = await (await api('post', '/api/scanner/scans', { code: 'RGL:LED-E27' })).json();
        expect(noTarget.result).toBe('no_target');

        // Wybór szkicu PZ w telefonie i otwarcie go w panelu
        const drafts = (await (await api('get', '/api/scanner/documents')).json()).data;
        const pz = drafts.find((d) => d.type === 'PZ');
        await api('post', '/api/scanner/target', { document_id: pz.id });
        await page.goto(`/dokumenty/${pz.id}`);
        await expect(page.getByText('Skaner telefonu podłączony')).toBeVisible();
        await expect(page.getByRole('link', { name: 'Żarówka LED E27 9 W, 806 lm' })).toHaveCount(0);
        // Czekamy, aż strona zasubskrybuje kanał dokumentu – inaczej zdarzenie może przyjść przed subskrypcją.
        await page.waitForFunction((id) => window.Echo?.connector?.pusher?.channel(`private-documents.${id}`)?.subscribed === true, pz.id);

        const added = await (await api('post', '/api/scanner/scans', { code: 'RGL:LED-E27', quantity: '3', document_id: pz.id })).json();
        expect(added.result).toBe('added');
        // Pozycja pojawia się bez przeładowania strony (WebSocket)
        await expect(page.getByRole('link', { name: 'Żarówka LED E27 9 W, 806 lm' })).toBeVisible();
        await expect(page.getByLabel('Ilość: Żarówka LED E27 9 W, 806 lm')).toHaveValue('3');

        // Tryb „Rozmieść”: etykieta półki, potem towar
        const shelf = 'RGL:L:MG1:REG-02:1:1';
        const location = await (await api('post', '/api/scanner/scans', { code: shelf, mode: 'putaway' })).json();
        expect(location.result).toBe('location');
        expect(location.location.code).toBe('REG-02-1-1');

        const placed = await (await api('post', '/api/scanner/scans', { code: 'RGL:LED-E27', mode: 'putaway', location: shelf, fill: 25, quantity: '10' })).json();
        expect(placed.result).toBe('placed');
        expect(placed.message).toContain('REG-02-1-1');

        // Dziennik skanów po polsku (bez kodów typu „no_target”)
        await page.goto('/skanery');
        const log = page.getByLabel('Dziennik skanów');
        for (const label of ['odłożono na półkę', 'skan półki', 'dodano', 'brak dokumentu']) {
            await expect(log.getByText(label, { exact: true }).first()).toBeVisible();
        }
        await expect(log.getByText('REG-02 · półka 1 · pole 1')).toBeVisible();
        await expect(log.getByText(/^(placed|location|no_target)$/)).toHaveCount(0);
    });
});
