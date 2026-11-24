export const IMAGE_CACHE = 'trackeat-menu-images:v1';

function imageUrls(data) {
    return [...new Set(data.productos.flatMap((product) => (product.imagenes || []).map((image) => image.url)))].filter((url) =>
        /^\/catalogo\/imagenes\/[a-f0-9-]{36}\.webp$/.test(url),
    );
}

export async function galleryReady(data) {
    const cache = await caches.open(IMAGE_CACHE);
    const matches = await Promise.all(imageUrls(data).map((url) => cache.match(url)));
    return matches.every(Boolean);
}

export async function downloadGalleries(data) {
    const cache = await caches.open(IMAGE_CACHE);
    const urls = imageUrls(data);
    // Sequential batches keep memory bounded for complete, five-image galleries.
    for (let offset = 0; offset < urls.length; offset += 3) {
        await Promise.all(
            urls.slice(offset, offset + 3).map(async (url) => {
                if (await cache.match(url)) return;
                const response = await fetch(url, { credentials: 'omit', cache: 'no-store' });
                if (!response.ok || !response.headers.get('Content-Type')?.startsWith('image/webp'))
                    throw new Error('No se descargaron todas las imágenes.');
                await cache.put(url, response);
            }),
        );
    }
    const keep = new Set(urls.map((url) => new URL(url, location.origin).href));
    for (const request of await cache.keys()) if (!keep.has(request.url)) await cache.delete(request);
}

export async function prepareOffline() {
    if (!window.isSecureContext || !('serviceWorker' in navigator)) throw new Error('El menú sin conexión requiere localhost o HTTPS.');
    const registration = await navigator.serviceWorker.register('/menu/sw.js', { scope: '/menu/', updateViaCache: 'none' });
    await new Promise((resolve, reject) => {
        const controlled = () => {
            if (navigator.serviceWorker.controller?.scriptURL === new URL('/menu/sw.js', location.origin).href) {
                clearTimeout(timer);
                navigator.serviceWorker.removeEventListener('controllerchange', controlled);
                resolve();
            }
        };
        const timer = setTimeout(() => {
            navigator.serviceWorker.removeEventListener('controllerchange', controlled);
            reject(new Error('No se completó la preparación sin conexión. Recarga con internet.'));
        }, 15000);
        navigator.serviceWorker.addEventListener('controllerchange', controlled);
        controlled();
    });
    return registration;
}
