import { test, expect } from '@playwright/test';
import { login, toast } from './helpers.js';

/*
 * Magazynier – codzienna praca: przyjęcie dostawy, wydanie, próba wydania ponad stan,
 * inwentaryzacja (bez prawa zatwierdzenia) i blokada magazynu na czas spisu.
 */
test.describe('Magazynier', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, 'storekeeper');
    });

    test('widzi szybkie akcje, a nie widzi administracji', async ({ page }) => {
        await expect(page.getByRole('link', { name: /Przyjmij towar/ })).toBeVisible();
        await expect(page.getByRole('link', { name: /Wydaj towar/ })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Do dokończenia' })).toBeVisible();

        const nav = page.getByRole('navigation', { name: 'Nawigacja główna' });
        await expect(nav.getByRole('link', { name: 'Plan magazynu' })).toBeVisible();
        await expect(nav.getByRole('link', { name: 'Użytkownicy' })).toHaveCount(0);
        await expect(nav.getByRole('link', { name: 'Role i uprawnienia' })).toHaveCount(0);
    });

    test('przyjmuje dostawę PZ od początku do zatwierdzenia', async ({ page }) => {
        await page.getByRole('link', { name: /Przyjmij towar/ }).click();
        await expect(page).toHaveURL(/\/dokumenty\/nowy\/PZ/);

        await page.getByLabel('Dostawca').click();
        await page.getByRole('option', { name: 'Opakowania Pomorze sp. z o.o.' }).click();
        await page.getByLabel('Numer obcy').fill('FV/E2E/001');
        await page.getByRole('button', { name: 'Utwórz szkic' }).click();

        await expect(page.getByRole('heading', { level: 1 })).toContainText('PZ · szkic');

        // Dodanie towaru z kartoteki (wyszukiwarka + Enter)
        await page.locator('#product-search').fill('Taśma pakowa');
        await page.getByRole('option', { name: /Taśma pakowa/ }).click();

        const quantity = page.getByLabel('Ilość: Taśma pakowa 48 mm × 66 m, brązowa');
        await quantity.fill('5');
        await quantity.press('Tab');
        const price = page.getByLabel('Cena: Taśma pakowa 48 mm × 66 m, brązowa');
        await price.fill('5,10');
        await price.press('Tab');

        await expect(page.getByText('Wartość netto:')).toContainText('25,50');
        // Towar ma przypisaną półkę – magazynier widzi, gdzie odłożyć
        await expect(page.getByTitle('Gdzie odłożyć').first()).toBeVisible();

        await page.getByRole('button', { name: 'Zatwierdź' }).first().click();
        const dialog = page.getByRole('dialog', { name: 'Zatwierdzić dokument?' });
        await dialog.getByRole('button', { name: 'Zatwierdź' }).click();

        await expect(page.getByRole('heading', { level: 1 })).toHaveText(/^PZ\/\d{4}\/\d{2}\/\d{4}$/);
        await expect(page.getByText('Zatwierdzony').first()).toBeVisible();
    });

    test('nie wyda towaru ponad stan – dostaje czytelny komunikat', async ({ page }) => {
        await page.goto('/dokumenty/nowy/WZ');
        await page.getByLabel('Odbiorca').click();
        await page.getByRole('option', { name: 'Budomax sp.j.' }).click();
        await page.getByRole('button', { name: 'Utwórz szkic' }).click();
        await expect(page.getByRole('heading', { level: 1 })).toContainText('WZ · szkic');

        await page.locator('#product-search').fill('Drabina');
        await page.getByRole('option', { name: /Drabina aluminiowa/ }).click();
        const quantity = page.getByLabel('Ilość: Drabina aluminiowa 3 × 8 stopni');
        await quantity.fill('50');
        await quantity.press('Tab');

        await expect(page.getByText(/Brak stanu dla/)).toBeVisible();

        await page.getByRole('button', { name: 'Zatwierdź' }).first().click();
        const dialog = page.getByRole('dialog', { name: 'Zatwierdzić dokument?' });
        await expect(dialog).toContainText('Nie można zatwierdzić');
        await expect(dialog).toContainText(/Drabina aluminiowa 3 × 8 stopni: dostępne .+, w dokumencie 50/);
        await expect(dialog.getByRole('button', { name: 'Zatwierdź' })).toBeDisabled();

        await dialog.getByRole('button', { name: 'Wróć' }).click();
        await expect(page.getByRole('heading', { level: 1 })).toContainText('szkic');
    });

    test('inwentaryzacja: liczy, ale zatwierdza kierownik; magazyn jest zablokowany', async ({ page }) => {
        await page.goto('/dokumenty/nowy/INW');
        await expect(page.getByText('zatwierdza go kierownik magazynu')).toBeVisible();

        await page.locator('#warehouse_id').click();
        await page.getByRole('option', { name: 'SKL · Punkt wydań' }).click();
        await page.getByRole('button', { name: 'Utwórz szkic' }).click();

        await expect(page.getByRole('heading', { level: 1 })).toContainText('INW · szkic');
        await expect(page.getByText('Trwa inwentaryzacja:')).toBeVisible();

        // Szybkie przyciski spisu: „zgodne” i „0”
        await page.getByRole('button', { name: /^Zgodne ze stanem:/ }).first().click();
        await page.getByRole('button', { name: /^Brak towaru:/ }).nth(1).click();
        await expect(page.getByText(/Niepoliczone: \d+ z \d+/)).toBeVisible();
        await expect(page.getByRole('button', { name: 'Zatwierdź' }).first()).toBeDisabled();

        // Blokada: nie da się utworzyć przyjęcia w zamrożonym magazynie
        await page.goto('/dokumenty/nowy/PZ');
        await page.locator('#warehouse_id').click();
        await page.getByRole('option', { name: 'SKL · Punkt wydań' }).click();
        await expect(page.getByText('Magazyn SKL jest w trakcie inwentaryzacji.')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Utwórz szkic' })).toBeDisabled();
    });

    test('kładzie towar na półce na planie magazynu, ale nie dodaje regałów', async ({ page }) => {
        await page.getByRole('navigation', { name: 'Nawigacja główna' }).getByRole('link', { name: 'Plan magazynu' }).click();
        await expect(page.getByRole('button', { name: 'Dodaj regały' })).toHaveCount(0);

        await page.getByRole('button', { name: /^Regał REG-05,/ }).click();
        await page.getByRole('button', { name: /^Półka 4, pole 2:/ }).click();

        const dialog = page.getByRole('dialog', { name: /REG-05 · półka 4 · pole 2/ });
        await dialog.getByLabel('Nazwa, indeks lub EAN').fill('Kask');
        await dialog.getByRole('button', { name: 'Połóż' }).click();
        await expect(dialog.getByRole('link', { name: 'Kask ochronny EN 397' })).toBeVisible();

        await page.keyboard.press('Escape');
        await expect(page.getByRole('button', { name: /^Półka 4, pole 2: zajęte \d+%.*Kask ochronny/ })).toBeVisible();
    });

    test('„Gdzie leży towar?” (Ctrl+K) pokazuje stan i półki', async ({ page }) => {
        await page.keyboard.press('Control+k');
        const dialog = page.getByRole('dialog', { name: 'Szukaj towaru' });
        await dialog.getByLabel('Nazwa, indeks lub EAN').fill('żarówka');
        await expect(dialog.getByText('Żarówka LED E27 9 W, 806 lm')).toBeVisible();
        await expect(dialog.locator('.wh-badge', { hasText: /REG-\d+-\d+-\d+/ }).first()).toBeVisible();

        await page.keyboard.press('Enter');
        await expect(page.getByRole('heading', { name: 'Gdzie leży' })).toBeVisible();
    });
});
