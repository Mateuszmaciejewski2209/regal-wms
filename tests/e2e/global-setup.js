import { execSync } from 'node:child_process';

/** Świeże dane demonstracyjne przed każdym przebiegiem testów E2E. */
export default async function globalSetup() {
    if (process.env.E2E_SKIP_RESET) return;

    execSync('docker compose exec -T app php artisan migrate:fresh --seed --force', { stdio: 'inherit' });
    // Kolejka trzyma połączenie z bazą sprzed resetu – restart, by webhooki działały od razu.
    execSync('docker compose restart queue', { stdio: 'ignore' });
}
