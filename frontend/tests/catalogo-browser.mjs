import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';
import { formatoFolio } from '../src/lib/folio.js';

const base = process.env.TRACKEAT_TEST_URL;
assert(base && new URL(base).port === '8005', 'Usa el servidor de trackeat_demo_test en puerto 8005.');
const output = fileURLToPath(new URL('../../output/playwright/', import.meta.url));
await mkdir(output, { recursive: true });
const browser = await chromium.launch({ channel: 'chrome', headless: true });
const results = [];
const errors = [];
const contexts = [];
const suffix = Date.now();
const categoryName = `Café pruebas ${suffix}`;
const productName = `Café especial ${suffix}`;
const description = '<img src=x onerror="window.catalogXss=true"> Texto de prueba';
async function login(email) {
    const context = await browser.newContext(); contexts.push(context);
    const page = await context.newPage();
    page.setDefaultTimeout(20000);
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto(`${base}/login`);
    await page.locator('#email').fill(email);
    await page.locator('#password').fill('TrackEatDemo!2026');
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await page.waitForURL(`${base}/dashboard`);
    return { context, page };
}
async function post(context, path, data) {
    const session = await (await context.request.get(`${base}/api/sesion`)).json();
    return context.request.post(`${base}${path}`, { headers: { 'X-CSRF-TOKEN': session.csrf, Accept: 'application/json' }, data });
}
try {
    const admin = await login('administrador@example.test');
    await admin.page.goto(`${base}/admin/categorias/crear`);
    await admin.page.getByLabel('Nombre', { exact: true }).fill(categoryName);
    await admin.page.getByLabel('Estación', { exact: true }).selectOption('barra');
    await admin.page.getByRole('button', { name: 'Guardar categoría' }).click();
    await admin.page.waitForURL(`${base}/admin/categorias`);
    await admin.page.getByRole('cell', { name: categoryName, exact: true }).waitFor();
    results.push('Alta de categoría desde formulario');
    await admin.page.goto(`${base}/admin/productos/crear`);
    await admin.page.getByLabel('Nombre', { exact: true }).fill(productName);
    await admin.page.getByLabel('Descripción', { exact: true }).fill(description);
    await admin.page.getByLabel('Categoría', { exact: true }).selectOption({ label: categoryName });
    await admin.page.getByLabel('Precio final MXN', { exact: true }).fill('40.50');
    const photo = fileURLToPath(new URL('../src/assets/images/logo.png', import.meta.url));
    await admin.page.getByLabel('Agregar imágenes', { exact: true }).setInputFiles([photo, photo]);
    await admin.page.getByRole('button', { name: 'Guardar producto', exact: true }).click();
    await admin.page.waitForURL(`${base}/admin/productos`);
    let catalog = await (await admin.context.request.get(`${base}/api/catalogo`)).json();
    const product = catalog.productos.find((row) => row.nombre === productName);
    assert(product, 'El producto creado debe aparecer en el catálogo público');
    assert.equal(product.imagenes.length, 2);
    results.push('Alta de producto con dos imágenes reales recodificadas');

    await admin.page.getByRole('link', { name: `Editar ${productName}`, exact: true }).click();
    await admin.page.getByRole('button', { name: 'Subir imagen 2', exact: true }).click();
    await admin.page.getByRole('button', { name: 'Guardar producto', exact: true }).click();
    await admin.page.waitForURL(`${base}/admin/productos`);
    catalog = await (await admin.context.request.get(`${base}/api/catalogo`)).json();
    assert.equal(catalog.productos.find((row) => row.id === product.id).imagenes[0].id, product.imagenes[1].id);
    results.push('Reordenamiento de galería desde interfaz');

    const client = await login('cliente1@example.test');
    await client.page.goto(`${base}/menu/`);
    await client.page.getByTestId('offline-status').filter({ hasText: 'Menú y galerías disponibles sin conexión.' }).waitFor();
    await client.page.getByLabel('Buscar producto', { exact: true }).fill(`especial ${suffix}`);
    await client.page.getByLabel('Categoría', { exact: true }).selectOption({ label: categoryName });
    await client.page.getByRole('link', { name: productName, exact: true }).click();
    const detail = client.page.getByRole('region', { name: 'Detalle de producto', exact: true });
    await detail.getByText(description, { exact: true }).waitFor();
    assert.equal(await client.page.evaluate(() => window.catalogXss), undefined);
    await detail.getByRole('button', { name: 'Ver imagen 2', exact: true }).click();
    await detail.getByRole('button', { name: 'Agregar al carrito', exact: true }).click();
    await client.context.setOffline(true);
    await client.page.reload();
    await client.page.getByRole('region', { name: 'Detalle de producto', exact: true }).getByText(description, { exact: true }).waitFor();
    await client.page.waitForFunction(() => { const image = document.querySelector('section[aria-label="Detalle de producto"] img'); return image?.complete && image.naturalWidth > 0; });
    await client.page.getByRole('heading', { name: productName, exact: true }).first().waitFor();
    assert(await client.page.getByRole('button', { name: 'Confirmar pedido', exact: true }).isDisabled());
    await client.page.screenshot({ path: `${output}/catalogo-offline.png`, fullPage: true });
    results.push('Búsqueda parcial y categoría, salida codificada, detalle y galerías offline, carrito persistente y envío bloqueado sin red');
    await client.context.setOffline(false);
    await client.page.getByRole('status', { name: 'Conectado al servidor', exact: true }).waitFor();

    const update = { nombre: productName, descripcion: description, id_categoria: product.id_categoria, precio: '49.50', disponible: true, imagenes_existentes: product.imagenes.map((i) => i.id), orden_imagenes: product.imagenes.map((i) => `existente:${i.id}`) };
    assert.equal((await post(admin.context, `/admin/productos/${product.id}`, update)).status(), 200);
    const rejected = client.page.waitForResponse((response) => response.url().endsWith('/api/pedidos') && response.request().method() === 'POST');
    await client.page.getByRole('button', { name: 'Confirmar pedido', exact: true }).click();
    assert.equal((await rejected).status(), 409);
    await client.page.getByRole('button', { name: 'He revisado los cambios', exact: true }).waitFor();
    await client.page.reload();
    await client.page.getByRole('button', { name: 'He revisado los cambios', exact: true }).waitFor();
    assert(await client.page.getByRole('button', { name: 'Confirmar pedido', exact: true }).isDisabled());
    await client.page.getByRole('button', { name: 'He revisado los cambios', exact: true }).click();
    const accepted = client.page.waitForResponse((response) => response.url().endsWith('/api/pedidos') && response.request().method() === 'POST');
    await client.page.getByRole('button', { name: 'Confirmar pedido', exact: true }).click();
    const orderResponse = await accepted;
    assert(orderResponse.ok(), await orderResponse.text());
    const order = await orderResponse.json();
    assert.equal(order.total, '49.50');
    results.push('Precio cambiado rechazado con 409, revisión pendiente persistente tras recarga y confirmación con total del servidor');
    await client.page.getByRole('link', { name: 'Ver seguimiento del pedido', exact: true }).click();
    await client.page.getByRole('heading', { name: `Pedido ${formatoFolio(order.numero_pedido)}`, exact: true }).waitFor();
    const other = await login('cliente2@example.test');
    assert.equal((await other.context.request.get(`${base}/api/pedidos/${order.id}`)).status(), 404);
    assert.equal((await other.page.goto(`${base}/pedidos/${order.id}`)).status(), 404);
    assert.equal((await other.context.request.get(`${base}/api/pedidos/99999999`)).status(), 404);
    assert.equal((await other.context.request.get(`${base}/admin/productos`)).status(), 403);
    assert.equal((await admin.context.request.get(`${base}/api/pedidos/${order.id}`)).status(), 200);
    results.push('Seguimiento propio, cliente ajeno 404, inexistente 404, administración 403 y personal permitido');

    assert.equal((await post(admin.context, `/admin/productos/${product.id}`, { ...update, disponible: false })).status(), 200);
    await client.page.goto(`${base}/menu/${product.id}`);
    await client.page.getByRole('region', { name: 'Detalle de producto', exact: true }).getByRole('button', { name: 'Agotado', exact: true }).waitFor();
    assert(await client.page.getByRole('region', { name: 'Detalle de producto', exact: true }).getByRole('button', { name: 'Agotado', exact: true }).isDisabled());
    results.push('Producto agotado visible sin permitir pedirlo');

    await admin.page.goto(`${base}/admin/productos`);
    await admin.page.getByRole('button', { name: `Eliminar ${productName}`, exact: true }).click();
    await admin.page.getByRole('button', { name: 'Confirmar eliminación', exact: true }).click();
    await admin.page.getByRole('status').filter({ hasText: 'Producto eliminado' }).waitFor();
    await admin.page.getByLabel('Mostrar', { exact: true }).selectOption('eliminados');
    await admin.page.getByRole('button', { name: `Restaurar ${productName}`, exact: true }).click();
    await admin.page.getByRole('status').filter({ hasText: 'Producto restaurado' }).waitFor();
    results.push('Baja lógica y restauración desde interfaz');

    await admin.page.setViewportSize({ width: 390, height: 844 });
    await admin.page.getByLabel('Mostrar', { exact: true }).selectOption('activos');
    await admin.page.screenshot({ path: `${output}/catalogo-admin-movil.png`, fullPage: true });
    await client.page.setViewportSize({ width: 390, height: 844 });
    await client.page.screenshot({ path: `${output}/catalogo-menu-movil.png`, fullPage: true });
    assert(await client.page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
    results.push('Menú y administración en viewport móvil de 390 píxeles');

    const limited = await browser.newContext(); contexts.push(limited);
    await limited.addInitScript(() => { const original = Cache.prototype.put; Cache.prototype.put = function (request, response) { if (String(request?.url || request).includes('/catalogo/imagenes/')) return Promise.reject(new DOMException('Test storage full', 'QuotaExceededError')); return original.call(this, request, response); }; });
    const limitedPage = await limited.newPage();
    await limitedPage.goto(`${base}/menu/`);
    await limitedPage.getByRole('alert').filter({ hasText: 'faltan imágenes sin conexión' }).waitFor();
    assert(!(await limitedPage.getByTestId('offline-status').textContent()).includes('Menú y galerías disponibles sin conexión.'));
    results.push('Almacenamiento insuficiente informa descarga incompleta sin prometer galerías offline');
    assert.deepEqual(errors, []);
    await writeFile(`${output}/catalogo-resultados.json`, JSON.stringify({ results, browserErrors: errors, passed: results.length }, null, 2));
    console.log(JSON.stringify({ passed: results.length, results }, null, 2));
} catch (error) {
    await writeFile(`${output}/catalogo-fallo.json`, JSON.stringify({ results, error: error.stack, browserErrors: errors }, null, 2));
    throw error;
} finally {
    for (const context of contexts) await context.close();
    await browser.close();
}
