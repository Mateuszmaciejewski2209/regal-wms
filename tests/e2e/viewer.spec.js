import { test, expect } from '@playwright/test';
import { login } from './helpers.js';

/* Księgowość (tylko podgląd) – nie widzi akcji, których nie może wykonać; eksport działa. */
test.describe('Księgowość (podgląd)', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, 'viewer');
    });

    test('nie widzi przycisków tworzenia ani skanera', async ({ page }) => {
        await expect(page.getByRole('button', { name: 'Nowy dokument' })).toHaveCount(0);
        await expect(page.getByRole('link', { name: /Przyjmij towar/ })).toHaveCount(0);
        await expect(page.getByRole('link', { name: /Sparuj telefon/ })).toHaveCount(0);

        await page.goto('/dokumenty');
        await expect(page.getByRole('button', { name: 'Nowy dokument' })).toHaveCount(0);
    });

    test('wejście na zabronioną stronę pokazuje polski komunikat', async ({ page }) => {
        await page.goto('/dokumenty/nowy/PZ');
        await expect(page.getByRole('heading', { name: 'Brak uprawnień' })).toBeVisible();
        await expect(page.getByText('Księgowość (podgląd)')).toBeVisible();
        await page.getByRole('link', { name: 'Pulpit' }).click();
        await expect(page.getByRole('heading', { level: 1 })).toContainText('Dzień dobry');
    });

    test('eksportuje stany do CSV i przegląda plan bez edycji', async ({ page }) => {
        await page.goto('/stany');
        const download = page.waitForEvent('download');
        await page.getByRole('link', { name: 'Eksport CSV' }).click();
        expect((await download).suggestedFilename()).toMatch(/^stany-.*\.csv$/);

        await page.goto('/plan');
        await expect(page.getByRole('button', { name: 'Dodaj regały' })).toHaveCount(0);
        await page.getByRole('button', { name: /^Regał REG-01,/ }).click();
        await page.getByRole('button', { name: /^Półka 1, pole 1:/ }).click();
        await expect(page.getByText('Połóż towar na tym polu')).toHaveCount(0);
    });
});
