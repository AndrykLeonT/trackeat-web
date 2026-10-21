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
    if (!data.user || !data.socket.key) return () => {};
    if (data.user.demo_expires_at && Date.parse(data.user.demo_expires_at) <= Date.now()) {
        status('Demostración terminada', false);
        return () => {};
    }
    const endpoint = new URL(data.socket.url);
    let timer;
    let expiry;
    let stopped = false;
    const echo = new Echo({
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
    const channels = [`trackeat.usuario.${data.user.id}`];
    if (staffRoles.includes(data.user.rol)) channels.push('trackeat.personal');
    if (data.user.demo_room) channels.push(`trackeat.demo.${data.user.demo_room}`);
    function sync() {
        clearTimeout(timer);
        timer = setTimeout(
            () => {
                if (!stopped) refresh();
            },
            100 + Math.random() * 150,
        );
    }
    for (const channel of channels) {
        echo.private(channel)
            .listen('.pedido.actualizado', sync)
            .subscribed(() => {
                status('Tiempo real conectado', true);
                sync();
            })
            .error(() => status('Sin autorización al socket', false));
    }
    connection.bind('state_change', ({ current }) => {
        if (current !== 'connected') status(current === 'connecting' ? 'Conectando tiempo real…' : 'Tiempo real desconectado', false);
    });
    if (data.user.demo_expires_at) {
        expiry = setTimeout(
            () => {
                echo.disconnect();
                status('Demostración terminada', false);
                refresh();
            },
            Math.max(0, Date.parse(data.user.demo_expires_at) - Date.now()),
        );
    }
    return () => {
        stopped = true;
        clearTimeout(timer);
        clearTimeout(expiry);
        echo.disconnect();
    };
}
