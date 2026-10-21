import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let csrf = '';
export const states = { recibido: 'Recibido', preparacion: 'En preparación', listo: 'Listo', entregado: 'Entregado', cancelado: 'Cancelado' };
export const staffRoles = ['administrador', 'cocinero', 'mesero', 'cajero'];

export async function api(path, { method = 'GET', body } = {}, retry = true) {
    const response = await fetch(`/api/${path}`, {
        method,
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        ...(body ? { body: JSON.stringify(body) } : {}),
    });
    if (response.status === 419 && retry) {
        await session();
        return api(path, { method, body }, false);
    }
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
        const error = new Error(
            Object.values(data.errors || {})
                .flat()
                .join(' ') ||
                data.message ||
                `Error ${response.status}`,
        );
        error.status = response.status;
        throw error;
    }
    return data;
}

export async function session() {
    const data = await api('sesion');
    csrf = data.csrf;
    return data;
}

export function connectLive(data, refresh, status) {
    if (!data.user) {
        status('Inicia sesión para recibir actualizaciones', false);
        return () => {};
    }
    if (data.user.demo_expires_at && Date.parse(data.user.demo_expires_at) <= Date.now()) {
        status('Demostración terminada', false);
        return () => {};
    }
    let timer;
    let expiry;
    let poll;
    let stopped = false;
    let refreshing = false;
    let echo;
    const subscribed = new Set();
    const channels = [`trackeat.usuario.${data.user.id}`];
    if (staffRoles.includes(data.user.rol)) channels.push('trackeat.personal');
    if (data.user.demo_room) channels.push(`trackeat.demo.${data.user.demo_room}`);
    const online = () => navigator.onLine !== false;
    const ready = () => subscribed.size === channels.length;
    function fallback() {
        if (!stopped) status(online() ? 'Actualización automática · cada 5 s' : 'Sin conexión', false);
    }
    async function update() {
        if (stopped || refreshing || !online()) return;
        refreshing = true;
        try {
            await refresh();
        } catch {
            if (!stopped) status('Servidor no disponible', false);
        } finally {
            refreshing = false;
        }
    }
    function sync() {
        if (stopped) return;
        clearTimeout(timer);
        timer = setTimeout(update, 100 + Math.random() * 150);
    }
    function schedulePoll() {
        // Also recover events lost while the socket or broadcast worker was unavailable.
        poll = setTimeout(async () => {
            if (stopped) return;
            if (!ready()) fallback();
            await update();
            if (!stopped) schedulePoll();
        }, 5000);
    }
    function offline() {
        subscribed.clear();
        fallback();
    }
    function resume() {
        if (!ready()) fallback();
        sync();
    }
    function stop() {
        stopped = true;
        clearTimeout(timer);
        clearTimeout(expiry);
        clearTimeout(poll);
        window.removeEventListener('online', resume);
        window.removeEventListener('offline', offline);
        window.removeEventListener('focus', resume);
        echo?.disconnect();
    }
    window.addEventListener('online', resume);
    window.addEventListener('offline', offline);
    window.addEventListener('focus', resume);
    schedulePoll();
    if (data.user.demo_expires_at) {
        expiry = setTimeout(
            () => {
                stop();
                status('Demostración terminada', false);
                void Promise.resolve()
                    .then(refresh)
                    .catch(() => {});
            },
            Math.max(0, Date.parse(data.user.demo_expires_at) - Date.now()),
        );
    }
    if (!data.socket?.key || !data.socket?.url) {
        fallback();
        return stop;
    }
    status(online() ? 'Conectando tiempo real…' : 'Sin conexión', false);
    try {
        const endpoint = new URL(data.socket.url);
        echo = new Echo({
            broadcaster: 'reverb',
            client: new Pusher(data.socket.key, {
                cluster: 'mt1',
                wsHost: endpoint.hostname,
                wsPort: Number(endpoint.port || (endpoint.protocol === 'https:' ? 443 : 80)),
                wssPort: Number(endpoint.port || 443),
                forceTLS: endpoint.protocol === 'https:',
                enabledTransports: ['ws', 'wss'],
                disableStats: true,
                channelAuthorization: { endpoint: '/broadcasting/auth', transport: 'ajax', headers: { 'X-CSRF-TOKEN': data.csrf } },
            }),
        });
        const connection = echo.connector.pusher.connection;
        for (const channel of channels) {
            echo.private(channel)
                .listen('.pedido.actualizado', sync)
                .subscribed(() => {
                    if (stopped) return;
                    subscribed.add(channel);
                    if (ready()) status('Tiempo real conectado', true);
                    sync();
                })
                .error(() => {
                    subscribed.delete(channel);
                    fallback();
                });
        }
        connection.bind('state_change', ({ current }) => {
            if (stopped || current === 'connected') return;
            subscribed.clear();
            fallback();
        });
    } catch {
        echo?.disconnect();
        fallback();
    }
    return stop;
}
