import { test, expect } from '@playwright/test';
import { login } from './helpers.js';

/* Kierownik – zatwierdza inwentaryzację i wykonuje storno, zarządza tylko niższymi kontami. */
test.describe('Kierownik magazynu', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, 'manager');
    });

    test('przeprowadza i zatwierdza inwentaryzację (zera + „pozostałe zgodne”)', async ({ page }) => {
        await page.goto('/dokumenty/nowy/INW');
        await page.locator('#warehouse_id').click();
        await page.getByRole('option', { name: 'MG2 · Magazyn buforowy' }).click();
        await page.getByRole('button', { name: 'Utwórz szkic' }).click();
        await expect(page.getByRole('heading', { level: 1 })).toContainText('INW · szkic');

        // Pierwszy towar: brak na półce (0)
        await page.getByRole('button', { name: /^Brak towaru:/ }).first().click();
        await expect(page.getByText(/^-\d+/).first()).toBeVisible();

        await page.getByRole('button', { name: 'Pozostałe zgodne ze stanem' }).click();
        await page.getByRole('alertdialog').getByRole('button', { name: 'Potwierdź zgodność' }).click();
        await expect(page.getByText(/możesz zatwierdzić spis/)).toBeVisible();

        await page.getByRole('button', { name: 'Zatwierdź' }).first().click();
        await page.getByRole('dialog', { name: 'Zatwierdzić dokument?' }).getByRole('button', { name: 'Zatwierdź' }).click();

        await expect(page.getByRole('heading', { level: 1 })).toHaveText(/^INW\/\d{4}\/\d{2}\/\d{4}$/);
        await expect(page.getByText('Różnice inwentaryzacyjne (netto):')).toBeVisible();
        // Zatwierdzona INW nie ma storna (różnice rozliczają PW/RW)
        await expect(page.getByRole('button', { name: 'Więcej akcji' })).toHaveCount(0);
        // Wygenerowany RW widoczny w powiązanych
        await expect(page.getByRole('link', { name: /^RW\// })).toBeVisible();
    });

    test('wykonuje storno przyjęcia z menu „Więcej akcji”', async ({ page }) => {
        await page.goto('/dokumenty?type=PZ&status=approved&q=FV/OP/1240');
        await page.getByRole('link', { name: /^PZ\// }).first().click();

        await page.getByRole('button', { name: 'Więcej akcji' }).click();
        await page.getByRole('menuitem', { name: 'Storno dokumentu…' }).click();
        await page.getByRole('dialog', { name: 'Wykonać storno?' }).getByRole('button', { name: 'Wykonaj storno' }).click();

        await expect(page.getByText('Anulowany').first()).toBeVisible();
    });

    test('może edytować magazyniera, ale nie administratora', async ({ page }) => {
        await page.goto('/uzytkownicy');
        await expect(page.getByRole('button', { name: 'Edytuj Marek Zieliński' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Edytuj Anna Kowalska' })).toHaveCount(0);

        await page.getByRole('button', { name: 'Edytuj Marek Zieliński' }).click();
        await page.getByRole('button', { name: 'Rola' }).click();
        await expect(page.getByRole('option', { name: /Administrator/ })).toHaveCount(0);
        await expect(page.getByRole('option', { name: /Kierownik magazynu/ })).toHaveCount(0);
    });
});
