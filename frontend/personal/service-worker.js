/* global __PRECACHE_ASSETS__ */
const PREFIX = `trackeat-personal:${self.registration.scope}:`;
const CACHE_NAME = `${PREFIX}__BUILD_REVISION__`;
const ASSETS = __PRECACHE_ASSETS__.map((asset) => new URL(asset, self.registration.scope).href);
const SHELL = new URL('index.html', self.registration.scope).href;

self.addEventListener('install', (event) => {
    event.waitUntil(
        (async () => {
            const cache = await caches.open(CACHE_NAME);
            await cache.addAll(ASSETS);
            await self.skipWaiting();
        })(),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            const keys = await caches.keys();
            await Promise.all(keys.filter((key) => key.startsWith(PREFIX) && key !== CACHE_NAME).map((key) => caches.delete(key)));
            await self.clients.claim();
        })(),
    );
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);
    if (event.request.method !== 'GET' || url.origin !== self.location.origin || !url.href.startsWith(self.registration.scope)) return;
    url.search = '';
    if (event.request.mode === 'navigate' || ASSETS.includes(url.href)) {
        event.respondWith(
            (async () => {
                const cache = await caches.open(CACHE_NAME);
                const cached = await cache.match(event.request.mode === 'navigate' ? SHELL : url.href);
                return cached || fetch(event.request);
            })(),
        );
    }
});

self.addEventListener('message', (event) => {
    if (event.data?.type !== 'CACHE_READY') return;
    event.waitUntil(
        (async () => {
            const cache = await caches.open(CACHE_NAME);
            const present = await Promise.all(ASSETS.map((asset) => cache.match(asset)));
            event.ports[0]?.postMessage({ ready: present.every(Boolean) });
        })(),
    );
});
