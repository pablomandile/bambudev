// Service worker de bambudev. Subí CACHE cuando cambien íconos o manifest:
// activate borra las cachés con otro nombre.
const CACHE = 'bambudev-v1';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE)
            .then((cache) => cache.addAll(['/manifest.webmanifest']))
            .catch(() => {}),
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((k) => k !== CACHE)
                        .map((k) => caches.delete(k)),
                ),
            ),
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    // Las respuestas de Inertia (JSON) comparten URL con el HTML: no se cachean.
    if (request.headers.has('X-Inertia')) {
        return;
    }

    // Cache-first solo para los bundles de Vite, que llevan hash en el nombre.
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(
            caches.match(request).then(
                (cached) =>
                    cached ||
                    fetch(request).then((response) => {
                        if (response.ok) {
                            const copy = response.clone();
                            caches
                                .open(CACHE)
                                .then((cache) => cache.put(request, copy));
                        }

                        return response;
                    }),
            ),
        );

        return;
    }

    // Todo lo demás: network-first, con la caché como respaldo sin conexión.
    event.respondWith(
        fetch(request)
            .then((response) => {
                if (response.ok && response.type === 'basic') {
                    const copy = response.clone();
                    caches
                        .open(CACHE)
                        .then((cache) => cache.put(request, copy));
                }

                return response;
            })
            .catch(() =>
                caches
                    .match(request)
                    .then((cached) => cached || Response.error()),
            ),
    );
});
