/* global __PRECACHE_ASSETS__ */
const PREFIX = `trackeat-menu:${self.registration.scope}:`;
const CACHE_NAME = `${PREFIX}__BUILD_REVISION__`;
const ASSETS = __PRECACHE_ASSETS__.map((asset) => new URL(asset, self.registration.scope).href);
const SHELL = new URL('index.html', self.registration.scope).href;
const IMAGE_CACHE = 'trackeat-menu-images:v1';

self.addEventListener('install', (event) =>
    event.waitUntil(
        (async () => {
            const cache = await caches.open(CACHE_NAME);
            await cache.addAll(ASSETS);
            await self.skipWaiting();
        })(),
    ),
);
self.addEventListener('activate', (event) =>
    event.waitUntil(
        (async () => {
            for (const name of await caches.keys()) if (name.startsWith(PREFIX) && name !== CACHE_NAME) await caches.delete(name);
            await self.clients.claim();
        })(),
    ),
);
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);
    if (event.request.method !== 'GET' || url.origin !== self.location.origin) return;
    // Public images downloaded explicitly from the public catalog; no private API caching.
    if (/^\/catalogo\/imagenes\/[a-f0-9-]{36}\.webp$/.test(url.pathname)) {
        event.respondWith((async () => (await (await caches.open(IMAGE_CACHE)).match(url.href)) || fetch(event.request))());
        return;
    }
    if (event.request.mode === 'navigate' && /^\/menu\/(?:\d+\/?|index\.html)?$/.test(url.pathname)) {
        event.respondWith((async () => (await (await caches.open(CACHE_NAME)).match(SHELL)) || fetch(event.request))());
        return;
    }
    url.search = '';
    if (ASSETS.includes(url.href)) event.respondWith((async () => (await (await caches.open(CACHE_NAME)).match(url.href)) || fetch(event.request))());
});
