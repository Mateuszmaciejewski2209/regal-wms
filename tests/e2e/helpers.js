import { expect } from '@playwright/test';

export const USERS = {
    admin: 'admin@regal.test',
    manager: 'kierownik@regal.test',
    storekeeper: 'magazynier@regal.test',
    viewer: 'ksiegowosc@regal.test',
};

export const PASSWORD = 'haslo1234';

export async function login(page, role) {
    await page.goto('/logowanie');
    await page.getByLabel('E-mail').fill(USERS[role]);
    await page.getByLabel('Hasło').fill(PASSWORD);
    await page.getByRole('button', { name: 'Zaloguj' }).click();
    await expect(page.getByRole('heading', { level: 1 })).toContainText('Dzień dobry');
}

/** Własna lista wyboru (<x-ui.select>): przycisk z etykietą → opcja w liście. */
export async function choose(page, label, option) {
    await page.getByRole('button', { name: label, exact: true }).click();
    await page.getByRole('option', { name: option }).first().click();
}

/** Toast z komunikatem (region aria-live). */
export function toast(page, text) {
    return page.locator('[aria-live="polite"]').getByText(text).first();
}

export async function navigate(page, linkName) {
    await page.getByRole('navigation', { name: 'Nawigacja główna' }).getByRole('link', { name: linkName }).click();
}
