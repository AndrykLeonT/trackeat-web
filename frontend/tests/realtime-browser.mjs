import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import { chromium } from 'playwright';

// Use only a server started against the isolated demo test database.
const base = process.env.TRACKEAT_TEST_URL;
assert(base, 'Define TRACKEAT_TEST_URL para el servidor con la base aislada de pruebas.');
const browser = await chromium.launch({ channel: 'chrome', headless: true });
const results = [];
await mkdir('../output/playwright', { recursive: true });
async function scenario(blockSocket) {
    const contexts = [];
    const errors = [];
    const frames = { client: 0, staff: 0 };
    async function login(role, email, path) {
        const context = await browser.newContext();
        contexts.push(context);
        if (blockSocket) await context.routeWebSocket(/\/app\//, (socket) => socket.close());
        const page = await context.newPage();
        page.setDefaultTimeout(20000);
        page.on('pageerror', (error) => errors.push(error.message));
        page.on('websocket', (socket) =>
            socket.on('framereceived', ({ payload }) => {
                if (String(payload).includes('pedido.actualizado')) frames[role]++;
            }),
        );
        await page.goto(`${base}/login`);
        await page.locator('#email').fill(email);
        await page.locator('#password').fill('TrackEatDemo!2026');
        await page.getByRole('button', { name: 'Log in', exact: true }).click();
        await page.waitForURL(`${base}/`);
        await page.goto(`${base}${path}`);
        await page
            .getByRole('status', {
                name: path === '/menu/' ? 'Conectado al servidor' : blockSocket ? 'Actualización automática · cada 5 s' : 'Tiempo real conectado',
                exact: true,
            })
            .waitFor();
        return page;
    }
    try {
        const client = await login('client', 'cliente1@example.test', '/menu/');
        const staff = await login('staff', 'administrador@example.test', '/comandas');
        let navigations = 0;
        for (const page of [client, staff])
            page.on('framenavigated', (frame) => {
                if (frame === page.mainFrame()) navigations++;
            });
        await client.getByRole('button', { name: 'Agregar Hamburguesa', exact: true }).click();
        const sent = client.waitForResponse((response) => response.url().endsWith('/api/pedidos') && response.request().method() === 'POST');
        await client.getByRole('button', { name: 'Confirmar pedido', exact: true }).click();
        const response = await sent;
        assert(response.ok(), await response.text());
        const order = await response.json();
        await client.goto(`${base}/comandas/desglose`);
        await client
            .getByRole('status', { name: blockSocket ? 'Actualización automática · cada 5 s' : 'Tiempo real conectado', exact: true })
            .waitFor();
        navigations = 0;
        const card = staff.getByRole('article').filter({ has: staff.getByRole('heading', { name: order.numero_pedido, exact: true }) });
        await card.waitFor();
        const row = client.locator('li').filter({ hasText: order.numero_pedido });
        for (const [button, state] of [
            ['Empezar a preparar', 'En preparación'],
            ['Marcar listo', 'Listo'],
            ['Marcar entregado', 'Entregado'],
        ]) {
            await card.getByRole('button', { name: button, exact: true }).click();
            await row.getByText(new RegExp(`^${state} ·`)).waitFor();
        }
        await card.waitFor({ state: 'detached' });
        assert.equal(navigations, 0, 'No debe recargarse ninguna pantalla');
        assert.deepEqual(errors, []);
        if (!blockSocket) {
            assert(frames.staff > 0, 'Cocina debe recibir el evento real de la nueva comanda');
            assert(frames.client >= 3, 'El cliente debe recibir los cambios de estado por WebSocket');
        }
        const mode = blockSocket ? 'respaldo-sin-socket' : 'websocket';
        await client.screenshot({ path: `../output/playwright/cliente-${mode}.png` });
        results.push({
            mode,
            order: order.numero_pedido,
            states: ['En preparación', 'Listo', 'Entregado'],
            navigations,
            frames,
            javascriptErrors: errors,
        });
        console.log(`${mode}: nueva comanda y 3 estados visibles sin recargar; eventos cliente=${frames.client}, cocina=${frames.staff}`);
    } finally {
        await Promise.all(contexts.map((context) => context.close()));
    }
}
try {
    await scenario(false);
    await scenario(true);
    await writeFile('../output/playwright/realtime-results.json', JSON.stringify(results, null, 2));
} finally {
    await browser.close();
}
