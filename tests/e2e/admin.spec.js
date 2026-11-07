import { test, expect } from '@playwright/test';
import { login, toast } from './helpers.js';

/* Administrator – konfigurator regałów, role i uprawnienia, konta. */
test.describe('Administrator', () => {
    test.beforeEach(async ({ page }) => {
        await login(page, 'admin');
    });

    test('konfigurator tworzy 40 regałów R-01 … R-40 z podglądem kodów', async ({ page }) => {
        await page.goto('/plan');
        await page.getByRole('button', { name: 'Magazyn', exact: true }).click();
        await page.getByRole('option', { name: 'MG2 · Magazyn buforowy' }).click();

        await page.getByRole('button', { name: 'Dodaj regały' }).click();
        const dialog = page.getByRole('dialog', { name: 'Dodaj regały' });
        await dialog.getByLabel('Prefiks').fill('R-');
        await dialog.getByLabel('Od numeru').fill('1');
        await dialog.getByLabel('Ile regałów').fill('40');
        await dialog.getByLabel('Cyfr').fill('2');
        await dialog.getByLabel('Półek w regale').fill('5');
        await dialog.getByLabel('Regałów w rzędzie').fill('10');

        await expect(dialog.getByText('R-01', { exact: true })).toBeVisible();
        await expect(dialog.getByText('R-40', { exact: true })).toBeVisible();

        await dialog.getByRole('button', { name: 'Utwórz regały' }).click();
        await expect(toast(page, /40 regałów: R-01 … R-40/)).toBeVisible();
        await expect(page.getByRole('button', { name: /^Regał R-40, 5 półki × 3 pola/ })).toBeVisible();
        await expect(page.getByText(/43 regały/)).toBeVisible(); // 3 istniejące + 40 nowych
    });

    test('tworzy rolę z wybranymi uprawnieniami i nadaje ją nowemu kontu', async ({ page }) => {
        await page.goto('/role');
        await expect(page.getByLabel('Macierz uprawnień').getByText('Rozmieszczanie towarów na półkach')).toBeVisible();

        await page.getByRole('button', { name: 'Nowa rola' }).click();
        const dialog = page.getByRole('dialog', { name: 'Nowa rola' });
        await dialog.getByLabel('Nazwa').fill('Praktykant');
        await dialog.getByLabel('Poziom').fill('3');
        await dialog.getByLabel('Podgląd dokumentów').check();
        await dialog.getByLabel('Podgląd stanów i kartotek').check();
        await dialog.getByRole('button', { name: 'Zapisz' }).click();
        await expect(page.getByRole('heading', { name: 'Hierarchia' })).toBeVisible();
        await expect(page.locator('ol').getByText('Praktykant')).toBeVisible();

        await page.goto('/uzytkownicy');
        await page.getByRole('button', { name: 'Nowy użytkownik' }).click();
        const user = page.getByRole('dialog', { name: 'Nowy użytkownik' });
        await user.getByLabel('Imię i nazwisko').fill('Jan Praktykant');
        await user.getByLabel('E-mail (login)').fill('praktykant@regal.test');
        await user.getByRole('button', { name: 'Rola' }).click();
        await page.getByRole('option', { name: /Praktykant · poziom 3/ }).click();
        await user.getByLabel('Hasło').fill('haslo1234');
        await user.getByRole('button', { name: 'Zapisz' }).click();

        await expect(page.getByRole('cell', { name: /^JP Jan Praktykant/ })).toBeVisible();
    });
});
