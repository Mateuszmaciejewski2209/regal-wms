// Testy end-to-end (Playwright) – uruchamiane na działającym środowisku Docker (http://localhost:8000).
//   docker compose up -d
//   npm run test:e2e
// Przed testami baza jest odtwarzana z danych demonstracyjnych (tests/e2e/global-setup.js).
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    globalSetup: './tests/e2e/global-setup.js',
    fullyParallel: false,
    workers: 1, // wspólna baza – scenariusze wykonują się po kolei
    retries: 0,
    timeout: 60_000,
    expect: { timeout: 10_000 },
    reporter: [['list'], ['html', { outputFolder: 'tests/e2e/report', open: 'never' }]],
    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:8000',
        locale: 'pl-PL',
        timezoneId: 'Europe/Warsaw',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        viewport: { width: 1440, height: 900 },
    },
    projects: [
        { name: 'desktop', use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } } },
    ],
});
