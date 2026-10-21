import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import vm from 'node:vm';

const source = await readFile(new URL('../src/lib/trackeat.js', import.meta.url), 'utf8');
async function setup(socket = { key: 'test', url: 'http://localhost:8080' }, user = { id: 1, rol: 'administrador' }) {
    const timers = new Map();
    const listeners = new Map();
    const channels = new Map();
    const events = new Map();
    let id = 0;
    let refreshes = 0;
    let disconnected = false;
    const statuses = [];
    const navigator = { onLine: true };
    class Pusher {
        connection = { bind: (event, callback) => events.set(event, callback) };
    }
    class Echo {
        constructor(options) {
            this.connector = { pusher: options.client };
        }
        private(name) {
            const channel = {
                listen(event, callback) {
                    this.receive = callback;
                    return this;
                },
                subscribed(callback) {
                    this.subscribe = callback;
                    return this;
                },
                error(callback) {
                    this.fail = callback;
                    return this;
                },
            };
            channels.set(name, channel);
            return channel;
        }
        disconnect() {
            disconnected = true;
        }
    }
    const context = vm.createContext({
        URL,
        Date,
        Math,
        navigator,
        window: {
            addEventListener: (event, callback) => listeners.set(event, callback),
            removeEventListener: (event) => listeners.delete(event),
        },
        setTimeout: (callback, delay) => {
            timers.set(++id, { callback, delay });
            return id;
        },
        clearTimeout: (timer) => timers.delete(timer),
    });
    const module = new vm.SourceTextModule(source, { context });
    await module.link((name) => {
        const dependency = new vm.SyntheticModule(
            ['default'],
            function () {
                this.setExport('default', name === 'laravel-echo' ? Echo : Pusher);
            },
            { context },
        );
        return dependency;
    });
    await module.evaluate();
    const stop = module.namespace.connectLive(
        { user, socket, csrf: 'test' },
        () => {
            refreshes++;
        },
        (label, connected) => statuses.push({ label, connected }),
    );
    return {
        stop,
        timers,
        channels,
        events,
        listeners,
        statuses,
        navigator,
        get refreshes() {
            return refreshes;
        },
        get disconnected() {
            return disconnected;
        },
        async tick(delay) {
            for (const [timer, entry] of [...timers]) {
                if (entry.delay <= delay && timers.delete(timer)) await entry.callback();
            }
        },
    };
}

test('sin configuración de socket deja de conectar y recibe comandas automáticamente', async () => {
    const live = await setup({ key: null, url: 'http://localhost:8080' });
    assert(live.statuses.some(({ label }) => label.includes('Actualización automática')));
    await live.tick(5000);
    assert(live.refreshes > 0, 'Una nueva comanda debe consultarse sin recargar la página');
    live.stop();
    assert.equal(live.timers.size, 0);
});

test('un socket que nunca conecta tiene actualización alternativa en cinco segundos', async () => {
    const live = await setup();
    await live.tick(5000);
    assert(live.refreshes > 0);
    assert(live.statuses.some(({ label }) => label.includes('Actualización automática')));
    live.stop();
});

test('los eventos de comandas y la suscripción actualizan sin recargar', async () => {
    const live = await setup();
    for (const channel of live.channels.values()) channel.subscribe();
    assert.equal(live.statuses.at(-1).connected, true);
    await live.tick(250);
    assert.equal(live.refreshes, 1);
    live.channels.get('trackeat.personal').receive();
    await live.tick(250);
    assert.equal(live.refreshes, 2);
    live.stop();
});

test('el cliente recibe cada cambio de estado en su canal privado', async () => {
    const live = await setup(undefined, { id: 27, rol: 'cliente' });
    assert.deepEqual([...live.channels.keys()], ['trackeat.usuario.27']);
    const channel = live.channels.get('trackeat.usuario.27');
    channel.subscribe();
    await live.tick(250);
    for (const estado of ['preparacion', 'listo', 'entregado']) {
        channel.receive({ pedido: { id: 1, estado } });
        await live.tick(250);
    }
    assert.equal(live.refreshes, 4);
    assert.equal(live.statuses.at(-1).connected, true);
    live.stop();
});

test('si falla un canal del personal no informa que está completamente conectado', async () => {
    const live = await setup();
    live.channels.get('trackeat.personal').fail();
    live.channels.get('trackeat.usuario.1').subscribe();
    assert.equal(live.statuses.at(-1).connected, false);
    await live.tick(5000);
    assert(live.refreshes > 0);
    live.stop();
});

test('se recuperan cambios al volver a tener internet o enfocar la ventana', async () => {
    const live = await setup();
    live.navigator.onLine = false;
    live.listeners.get('offline')?.();
    await live.tick(5000);
    assert.equal(live.refreshes, 0);
    assert.equal(live.statuses.at(-1).label, 'Sin conexión');
    live.navigator.onLine = true;
    live.listeners.get('online')?.();
    await live.tick(250);
    assert.equal(live.refreshes, 1);
    live.listeners.get('focus')?.();
    await live.tick(250);
    assert.equal(live.refreshes, 2);
    live.stop();
    assert.equal(live.listeners.size, 0);
    assert.equal(live.timers.size, 0);
    assert(live.disconnected);
});

test('una desconexión pierde las suscripciones hasta que se recuperan todos los canales', async () => {
    const live = await setup();
    for (const channel of live.channels.values()) channel.subscribe();
    live.events.get('state_change')({ current: 'unavailable' });
    assert.equal(live.statuses.at(-1).connected, false);
    await live.tick(5000);
    assert(live.refreshes > 0);
    for (const channel of live.channels.values()) channel.subscribe();
    assert.equal(live.statuses.at(-1).connected, true);
    live.stop();
    await live.tick(60000);
    assert.equal(live.timers.size, 0);
});

test('una demo vencida no abre conexiones ni programa consultas', async () => {
    const live = await setup(undefined, { id: 1, rol: 'cliente', demo_expires_at: '2020-01-01T00:00:00Z' });
    assert.equal(live.statuses.at(-1).label, 'Demostración terminada');
    assert.equal(live.channels.size, 0);
    assert.equal(live.timers.size, 0);
});
