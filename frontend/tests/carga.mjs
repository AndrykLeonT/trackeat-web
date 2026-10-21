import assert from 'node:assert/strict';
import process from 'node:process';
import { mkdir, writeFile } from 'node:fs/promises';
import { request } from 'playwright';
import WebSocket from 'ws';

const demoUrl = process.env.TRACKEAT_DEMO_URL;
assert(demoUrl, 'Define TRACKEAT_DEMO_URL con el enlace generado por presentacion.ps1.');
const base = new URL(demoUrl).origin;
const clients = Number(process.env.TRACKEAT_CLIENTS || 50);
const seconds = Number(process.env.TRACKEAT_LOAD_SECONDS || 600);
assert(clients > 0 && clients <= 100 && seconds >= 1);
const participants = [];
const created = new Set();
const errors = [];
let finishing = false;
const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
async function pool(items, concurrency, action) {
    let index = 0;
    await Promise.all(
        Array.from({ length: concurrency }, async () => {
            while (index < items.length) {
                const current = index++;
                await action(items[current], current);
            }
        }),
    );
}

async function participant() {
    const context = await request.newContext({ baseURL: base });
    const person = { context, seen: new Map(), latencies: [], socket: null, heartbeat: null };
    participants.push(person);
    assert((await context.get(demoUrl)).ok(), 'El enlace demo no abrió.');
    const response = await context.get('/api/sesion');
    person.session = await response.json();
    const { user, socket, csrf } = person.session;
    assert(user?.demo_room, 'La sesión no pertenece a la demostración.');
    const endpoint = new URL(socket.url);
    endpoint.protocol = endpoint.protocol === 'https:' ? 'wss:' : 'ws:';
    endpoint.pathname = `/app/${socket.key}`;
    endpoint.search = '?protocol=7&client=trackeat-load&version=1.0&flash=false';
    const ws = new WebSocket(endpoint, { origin: base });
    person.socket = ws;
    await new Promise((resolve, reject) => {
        const timeout = setTimeout(() => reject(new Error('Timeout al suscribir el socket.')), 20000);
        ws.on('error', reject);
        ws.on('close', () => {
            if (!finishing) errors.push('Se cerró una conexión durante la prueba.');
        });
        ws.on('message', async (bytes) => {
            try {
                const event = JSON.parse(bytes.toString());
                const data = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
                if (event.event === 'pusher:connection_established') {
                    const channel = `private-trackeat.demo.${user.demo_room}`;
                    const auth = await context.post('/broadcasting/auth', {
                        headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                        data: { socket_id: data.socket_id, channel_name: channel },
                    });
                    assert(auth.ok(), `Autorización falló: ${auth.status()}`);
                    ws.send(JSON.stringify({ event: 'pusher:subscribe', data: { ...(await auth.json()), channel } }));
                } else if (event.event === 'pusher_internal:subscription_succeeded') {
                    clearTimeout(timeout);
                    resolve();
                } else if (event.event === 'pusher:ping') ws.send(JSON.stringify({ event: 'pusher:pong', data: {} }));
                else if (event.event === 'pusher:error') throw new Error(JSON.stringify(data));
                else if (event.event === 'pedido.actualizado') {
                    assert.deepEqual(Object.keys(data.pedido).sort(), ['estado', 'id', 'numero_pedido', 'updated_at']);
                    person.seen.set(data.pedido.id, (person.seen.get(data.pedido.id) || 0) + 1);
                    person.latencies.push({ id: data.pedido.id, milliseconds: Math.max(0, Date.now() - Date.parse(data.pedido.updated_at)) });
                }
            } catch (error) {
                clearTimeout(timeout);
                errors.push(error.message);
                reject(error);
            }
        });
    });
    person.heartbeat = setInterval(() => {
        if (ws.readyState === WebSocket.OPEN) ws.send(JSON.stringify({ event: 'pusher:ping', data: {} }));
    }, 20000);
    return person;
}

try {
    console.log(`Preparando ${clients} sesiones distintas…`);
    await pool(Array.from({ length: clients }), 5, participant);
    assert.equal(new Set(participants.map((p) => p.session.user.id)).size, clients);
    const start = Date.now();
    const catalog = await (await participants[0].context.get('/api/catalogo')).json();
    const product = catalog.productos[0];
    assert(product, 'La base de demostración no tiene productos.');
    console.log(`${clients} sockets suscritos. Enviando ${clients} pedidos con concurrencia 10…`);
    await pool(participants, 10, async (person) => {
        const body = { client_request_id: crypto.randomUUID(), tipo_entrega: 'para_llevar', items: [{ id_producto: product.id, cantidad: 1 }] };
        const options = { headers: { 'X-CSRF-TOKEN': person.session.csrf, Accept: 'application/json' }, data: body };
        const response = await person.context.post('/api/pedidos', options);
        assert(response.ok(), `Crear pedido falló: ${response.status()} ${await response.text()}`);
        const order = await response.json();
        person.order = order;
        created.add(order.id);
        const retry = await person.context.post('/api/pedidos', options);
        assert.equal((await retry.json()).id, order.id, 'El reintento duplicó el pedido.');
    });
    assert.equal(created.size, clients);
    const deliveryDeadline = Date.now() + 15000;
    while (participants.some((person) => [...created].some((id) => !person.seen.has(id))) && Date.now() < deliveryDeadline) await delay(200);
    for (const person of participants) {
        assert(
            [...created].every((id) => person.seen.get(id) === 1),
            'Se perdieron o duplicaron eventos.',
        );
        const own = await (await person.context.get('/api/pedidos')).json();
        assert.equal(own.filter((order) => created.has(order.id)).length, 1, 'Persistencia o aislamiento incorrectos.');
    }
    while (Date.now() - start < seconds * 1000) {
        await delay(Math.min(30000, seconds * 1000 - (Date.now() - start)));
        assert(
            participants.every((person) => person.socket.readyState === WebSocket.OPEN),
            'No se mantuvieron todas las conexiones.',
        );
        assert.equal(errors.length, 0, errors.join('\n'));
        console.log(`${Math.floor((Date.now() - start) / 1000)} s: ${clients} conexiones activas.`);
    }
    const latencies = participants
        .flatMap((person) => person.latencies)
        .filter((event) => created.has(event.id))
        .map((event) => event.milliseconds)
        .sort((a, b) => a - b);
    const p95 = latencies[Math.ceil(latencies.length * 0.95) - 1];
    assert(p95 <= 3000, `Latencia p95 ${p95} ms supera 3000 ms.`);
    const result = {
        date: new Date().toISOString(),
        clients,
        durationSeconds: Math.floor((Date.now() - start) / 1000),
        orders: created.size,
        receivedEvents: latencies.length,
        expectedEvents: clients * clients,
        p95Milliseconds: p95,
        maxMilliseconds: latencies.at(-1),
        duplicateOrders: 0,
        errors,
        measurement: 'Desde updated_at del servidor (resolución de un segundo) hasta recepción WebSocket; incluye cola.',
    };
    await mkdir('../output/playwright', { recursive: true });
    await writeFile('../output/playwright/carga-results.json', JSON.stringify(result, null, 2));
    console.log(JSON.stringify(result, null, 2));
} finally {
    finishing = true;
    for (const person of participants) {
        clearInterval(person.heartbeat);
        person.socket?.close();
        await person.context.dispose();
    }
}
