import assert from 'node:assert/strict';
import process from 'node:process';
import { mkdir, writeFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const demo = process.env.TRACKEAT_DEMO_URL;
assert(demo, 'Define TRACKEAT_DEMO_URL con el enlace firmado del servidor de pruebas.');
const base = new URL(demo).origin;
const browser = await chromium.launch({ channel: process.env.TRACKEAT_BROWSER || 'chrome', headless: true });
const errors = [];
const checks = [];
const contexts = [];
await mkdir('../output/playwright', { recursive: true });
async function page(options = {}) {
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, ...options });
    contexts.push(context);
    const tab = await context.newPage();
    tab.on('pageerror', (error) => errors.push(error.message));
    tab.setDefaultTimeout(30000);
    return tab;
}
async function waitText(tab, text) {
    await tab.getByText(text, { exact: false }).first().waitFor();
}
async function personalRecord(tab, name) {
    await tab.getByLabel('Nombre completo', { exact: true }).fill(name);
    await tab.getByLabel('Correo electrónico', { exact: true }).fill(`${name.startsWith('Ang') ? 'angelica' : 'carlos'}@example.test`);
    await tab.getByLabel('Teléfono', { exact: true }).fill('6121234567');
    await tab.getByLabel('Área', { exact: true }).selectOption('Cocina');
    await tab.getByLabel('Puesto', { exact: true }).fill('Cocinero');
    await tab.getByLabel('Fotografía', { exact: true }).setInputFiles('../frontend/src/assets/images/angie.jpg');
    await tab.getByRole('button', { name: 'Agregar integrante', exact: true }).click();
    await tab.getByRole('heading', { name, exact: true }).waitFor();
}
try {
    const a = await page();
    const b = await page();
    await a.goto(demo);
    await b.goto(demo);
    await waitText(a, 'Tiempo real conectado');
    await waitText(b, 'Tiempo real conectado');
    const sessionA = await (await a.request.get(`${base}/api/sesion`)).json();
    const sessionB = await (await b.request.get(`${base}/api/sesion`)).json();
    assert.notEqual(sessionA.user.id, sessionB.user.id);
    const initialBoard = await (await b.request.get(`${base}/api/tablero`)).json();
    checks.push('Dos navegadores obtienen cuentas cliente distintas y suscriben canales privados.');
    await waitText(a, 'Menú disponible sin conexión.');
    await a.context().setOffline(true);
    await a.reload();
    await a.getByRole('button', { name: 'Agregar Hamburguesa', exact: true }).waitFor();
    await a.getByLabel('Nombre empieza con', { exact: true }).fill('hamb');
    await a.getByRole('button', { name: 'Agregar Hamburguesa', exact: true }).click();
    await a.getByLabel('Notas para Hamburguesa', { exact: true }).fill('Sin cebolla');
    await a.getByLabel('Entrega', { exact: true }).selectOption('comer_aqui');
    await a.getByLabel('Número de mesa', { exact: true }).fill('7');
    await a.getByLabel('Número de mesa', { exact: true }).blur();
    assert(await a.getByRole('button', { name: 'Confirmar pedido', exact: true }).isDisabled());
    await a.reload();
    assert.equal(await a.getByLabel('Cantidad de Hamburguesa', { exact: true }).inputValue(), '1');
    assert.equal(await a.getByLabel('Notas para Hamburguesa', { exact: true }).inputValue(), 'Sin cebolla');
    checks.push('Menú recarga offline, busca por índice y conserva carrito, notas y mesa; envío desactivado.');
    await a.context().setOffline(false);
    await waitText(a, 'Tiempo real conectado');
    const sent = a.waitForResponse((response) => response.url().endsWith('/api/pedidos') && response.request().method() === 'POST');
    await a.getByRole('button', { name: 'Confirmar pedido', exact: true }).click();
    const response = await sent;
    assert(response.ok(), await response.text());
    const order = await response.json();
    await waitText(a, `Pedido confirmado: ${order.numero_pedido}`);
    await waitText(b, order.numero_pedido);
    const board = await (await b.request.get(`${base}/api/tablero`)).json();
    assert(board.pedidos.some((row) => row.id === order.id));
    await b.waitForFunction(
        (total) => {
            const row = [...document.querySelectorAll('table tr')].find((row) => row.querySelector('th')?.textContent === 'Recibido');
            return Number(row?.querySelector('td')?.textContent) === total;
        },
        Number(initialBoard.estados.recibido || 0) + 1,
    );
    checks.push('Pedido confirmado en PostgreSQL y visible en el tablero de otra sesión sin recargar.');

    const staff = await page();
    await staff.goto(`${base}/login`);
    await staff.locator('#email').fill('administrador@example.test');
    await staff.locator('#password').fill('TrackEatDemo!2026');
    await staff.getByRole('button', { name: 'Log in', exact: true }).click();
    await staff.waitForURL(base + '/');
    await staff.goto(`${base}/comandas`);
    await waitText(staff, 'Tiempo real conectado');
    const card = staff.getByRole('article').filter({ has: staff.getByRole('heading', { name: order.numero_pedido, exact: true }) });
    await card.getByRole('button', { name: 'Empezar a preparar', exact: true }).click();
    await card.getByRole('button', { name: 'Marcar listo', exact: true }).waitFor();
    await waitText(a, 'En preparación');
    await card.getByRole('button', { name: 'Marcar listo', exact: true }).click();
    await card.getByRole('button', { name: 'Marcar entregado', exact: true }).waitFor();
    await waitText(a, 'Listo');
    await b.context().setOffline(true);
    await card.getByRole('button', { name: 'Marcar entregado', exact: true }).click();
    await card.waitFor({ state: 'detached' });
    await b.context().setOffline(false);
    await waitText(b, 'Tiempo real conectado');
    await b.waitForFunction(
        (id) => [...document.querySelectorAll('li')].some((li) => li.textContent.includes(id) && li.textContent.includes('Entregado')),
        order.numero_pedido,
    );
    checks.push('Personal avanza estados; el cliente recibe los cambios; una reconexión recupera la entrega perdida.');
    await staff.goto(`${base}/dashboard`);
    await staff.getByRole('heading', { name: '¿En qué estado están los pedidos?' }).waitFor();
    await waitText(staff, 'Tiempo real conectado');
    assert(await staff.getByRole('table').isVisible());
    assert(
        Number(
            await staff
                .getByRole('row', { name: /^Entregado / })
                .getByRole('cell')
                .innerText(),
        ) > 0,
    );
    await staff.screenshot({ path: '../output/playwright/dashboard-live.png' });
    await a.screenshot({ path: '../output/playwright/menu-live.png' });
    await a.setViewportSize({ width: 390, height: 844 });
    await waitText(a, 'Tiempo real conectado');
    assert(await a.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
    await a.screenshot({ path: '../output/playwright/menu-live-mobile.png' });
    checks.push('Gráfico con datos reales, tabla accesible y menú sin desbordamiento a 390 px.');

    const personal = await page();
    await personal.goto(`${base}/personal/`);
    await personal.waitForFunction(() => !!navigator.serviceWorker.controller);
    const initial = await personal.evaluate(
        () =>
            new Promise((resolve, reject) => {
                const request = indexedDB.open('trackeat-personal');
                request.onsuccess = () => {
                    const version = request.result.version;
                    request.result.close();
                    resolve(version);
                };
                request.onerror = () => reject(request.error);
            }),
    );
    assert.equal(initial, 2);
    await personal.context().setOffline(true);
    await personal.reload();
    await personalRecord(personal, 'Angélica Prueba');
    assert.equal(await personal.locator('.chart-panel tbody tr').filter({ hasText: 'Cocina' }).locator('td').innerText(), '1');
    await personal.getByLabel('Nombre (empieza con)', { exact: true }).fill('angel');
    await personal.getByRole('button', { name: 'Buscar', exact: true }).click();
    await personal.getByRole('heading', { name: 'Angélica Prueba', exact: true }).waitFor();
    const blob = await personal.evaluate(
        () =>
            new Promise((resolve) => {
                const request = indexedDB.open('trackeat-personal');
                request.onsuccess = () => {
                    const db = request.result;
                    const tx = db.transaction('personal');
                    const rows = tx.objectStore('personal').getAll();
                    rows.onsuccess = () => resolve(rows.result[0].foto instanceof Blob && rows.result[0].foto.size > 0);
                    tx.oncomplete = () => db.close();
                };
            }),
    );
    assert(blob);
    await personal.getByText('Acerca de la base local y su migración', { exact: true }).click();
    await personal.getByRole('button', { name: 'Demostrar migración v1 → v2', exact: true }).click();
    await waitText(personal, 'Migración comprobada:');
    await personal.getByRole('button', { name: 'Editar Angélica Prueba', exact: true }).click();
    await personal.getByRole('button', { name: 'Guardar cambios', exact: true }).waitFor();
    await personal.getByLabel('Área', { exact: true }).selectOption('Servicio');
    await personal.getByRole('button', { name: 'Guardar cambios', exact: true }).click();
    await waitText(personal, 'Los cambios del integrante se guardaron.');
    assert.equal(await personal.locator('.chart-panel tbody tr').filter({ hasText: 'Servicio' }).locator('td').innerText(), '1');
    await personal.getByRole('button', { name: 'Eliminar Angélica Prueba', exact: true }).click();
    assert(await personal.getByRole('button', { name: 'Cancelar', exact: true }).evaluate((element) => element === document.activeElement));
    await personal.getByRole('button', { name: 'Cancelar', exact: true }).click();
    await personal.getByRole('heading', { name: 'Angélica Prueba', exact: true }).waitFor();
    await personal.getByRole('button', { name: 'Eliminar Angélica Prueba', exact: true }).click();
    await personal.getByRole('button', { name: 'Sí, eliminar', exact: true }).click();
    await personal.getByRole('heading', { name: 'Angélica Prueba', exact: true }).waitFor({ state: 'detached' });
    assert.equal(await personal.locator('.chart-panel tbody tr').filter({ hasText: 'Servicio' }).locator('td').innerText(), '0');
    await personal.screenshot({ path: '../output/playwright/personal-reverified.png' });
    await personal.context().setOffline(false);
    checks.push('Personal v2: alta Blob offline, búsqueda por índice, migración con bytes idénticos, edición y baja confirmada.');
    assert.equal(errors.length, 0, errors.join('\n'));
    const result = { date: new Date().toISOString(), checks, javascriptErrors: errors };
    await writeFile('../output/playwright/entrega-results.json', JSON.stringify(result, null, 2));
    console.log(JSON.stringify(result, null, 2));
} catch (error) {
    for (let index = 0; index < contexts.length; index++) {
        const tab = contexts[index].pages()[0];
        if (tab) {
            await tab.screenshot({ path: `../output/playwright/failure-${index}.png` }).catch(() => {});
            console.error(`PAGE ${index}: ${(await tab.locator('body').innerText()).slice(-2000)}`);
        }
    }
    throw error;
} finally {
    await browser.close();
}
