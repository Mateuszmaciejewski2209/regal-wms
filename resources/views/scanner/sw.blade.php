/* Service worker skanera Regał – szybki start i działanie przy słabym zasięgu. */
const CACHE = 'regal-scanner-v1';
const SHELL = ['/skaner'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // API nigdy z cache – skany muszą trafić na serwer (kolejka offline jest w aplikacji).
    if (event.request.method !== 'GET' || url.pathname.startsWith('/api/')) return;

    // Zbudowane zasoby (z hashem w nazwie): cache-first.
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(
            caches.match(event.request).then((hit) => hit || fetch(event.request).then((res) => {
                const copy = res.clone();
                caches.open(CACHE).then((cache) => cache.put(event.request, copy));
                return res;
            })),
        );
        return;
    }

    // Strona skanera: network-first z kopią zapasową.
    if (event.request.mode === 'navigate' && url.pathname.startsWith('/skaner')) {
        event.respondWith(
            fetch(event.request)
                .then((res) => {
                    const copy = res.clone();
                    caches.open(CACHE).then((cache) => cache.put('/skaner', copy));
                    return res;
                })
                .catch(() => caches.match('/skaner')),
        );
    }
});
