const CACHE_NAME = 'accurate-check-static-v2';
const APP_SHELL = [
    '/offline',
    '/manifest.webmanifest',
    '/favicon.svg',
    '/pwa-192.png',
    '/pwa-512.png',
];
const BUSINESS_PATH_PREFIXES = [
    '/dashboard',
    '/warehouse',
    '/hod',
    '/head-warehouse',
    '/admin',
    '/files',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(APP_SHELL)),
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)))),
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (BUSINESS_PATH_PREFIXES.some((prefix) => url.pathname.startsWith(prefix))) {
        if (request.mode === 'navigate') {
            event.respondWith(
                fetch(request).catch(() => caches.match('/offline')),
            );
            return;
        }

        event.respondWith(fetch(request));
        return;
    }

    if (url.pathname.startsWith('/build/') || APP_SHELL.includes(url.pathname)) {
        event.respondWith(
            caches.match(request).then((cached) => cached ?? fetch(request)),
        );
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match('/offline')),
        );
    }
});
