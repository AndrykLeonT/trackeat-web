export async function prepareOffline() {
    if (!window.isSecureContext || !('serviceWorker' in navigator)) {
        throw new Error('Para habilitar el uso sin conexión, abre el catálogo en localhost o mediante HTTPS.');
    }
    const registration = await navigator.serviceWorker.register(new URL('./sw.js', window.location.href), {
        scope: './',
        updateViaCache: 'none',
    });
    await new Promise((resolve, reject) => {
        const timeout = setTimeout(() => {
            navigator.serviceWorker.removeEventListener('controllerchange', controlled);
            reject(new Error('No se completó la preparación sin conexión. Conecta la red y vuelve a cargar.'));
        }, 15000);
        function controlled() {
            if (navigator.serviceWorker.controller?.scriptURL === new URL('./sw.js', window.location.href).href) {
                clearTimeout(timeout);
                navigator.serviceWorker.removeEventListener('controllerchange', controlled);
                resolve();
            }
        }
        navigator.serviceWorker.addEventListener('controllerchange', controlled);
        controlled();
    });
    const ready = await new Promise((resolve, reject) => {
        const channel = new MessageChannel();
        const timeout = setTimeout(() => {
            channel.port1.close();
            reject(new Error('No se pudo verificar la caché del catálogo.'));
        }, 5000);
        channel.port1.onmessage = ({ data }) => {
            clearTimeout(timeout);
            channel.port1.close();
            resolve(data.ready);
        };
        registration.active.postMessage({ type: 'CACHE_READY' }, [channel.port2]);
    });
    if (!ready) throw new Error('La caché está incompleta. Conecta la red y vuelve a cargar el catálogo.');
}
