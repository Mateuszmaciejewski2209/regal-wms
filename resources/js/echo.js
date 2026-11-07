import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

/*
 * Dwa tryby połączenia WebSocket:
 *  - lokalny (`composer dev`): Reverb na własnym porcie z .env (np. :8090),
 *  - Docker / tunel: puste VITE_REVERB_PORT → ten sam host i port co strona,
 *    nginx przekazuje ścieżkę /app do kontenera Reverb (działa też po HTTPS).
 */
const sameOrigin = !import.meta.env.VITE_REVERB_PORT;
const scheme = import.meta.env.VITE_REVERB_SCHEME || (window.location.protocol === 'https:' ? 'https' : 'http');
const configuredHost = import.meta.env.VITE_REVERB_HOST;

let host = !configuredHost || configuredHost === 'localhost' ? window.location.hostname : configuredHost;

// Reverb lokalnie nasłuchuje na IPv4 (0.0.0.0); „localhost” bywa rozwiązywany do ::1.
if (host === 'localhost' && !sameOrigin) host = '127.0.0.1';

const port = sameOrigin
    ? Number(window.location.port) || (scheme === 'https' ? 443 : 80)
    : Number(import.meta.env.VITE_REVERB_PORT);

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: host,
    wsPort: port,
    wssPort: port,
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
});
