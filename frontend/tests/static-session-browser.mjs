import assert from 'node:assert/strict';
import { chromium } from 'playwright';

const base = process.env.TRACKEAT_TEST_URL;
assert(base && new URL(base).hostname === '127.0.0.1' && new URL(base).port === '8005', 'Usa una instancia de prueba local en 127.0.0.1:8005.');
const browser = await chromium.launch({ channel: 'chrome', headless: true });

async function login(page, email, destination) {
    await page.goto(base + '/login');
    await page.locator('#email').fill(email);
    await page.locator('#password').fill('TrackEatDemo!2026');
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await page.waitForURL(base + destination);
}

function browserMenu(page) {
    return page.getByRole('navigation', { name: 'Módulos de TrackEat' });
}

async function assertAccount(page, name, role) {
    const sidebar = browserMenu(page);
    await sidebar.getByText(name, { exact: true }).waitFor();
    await sidebar.getByText(role, { exact: true }).waitFor();
    await sidebar.getByRole('link', { name: 'Mi perfil', exact: true }).waitFor();
    await sidebar.getByRole('button', { name: 'Cerrar sesión', exact: true }).waitFor();
    assert.equal(await sidebar.getByText('Chef de Cocina', { exact: true }).count(), 0);
    assert.equal(await sidebar.getByRole('link', { name: 'Usuarios', exact: true }).count(), role === 'Administrador' ? 1 : 0);
    assert.equal(await sidebar.getByRole('link', { name: 'Mis pedidos', exact: true }).count(), role === 'Cliente' ? 1 : 0);
}

try {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));

    await login(page, 'cliente1@example.test', '/menu/');
    await assertAccount(page, 'Cliente1 Demo', 'Cliente');
    await browserMenu(page).getByRole('link', { name: 'Personal', exact: true }).click();
    await page.waitForURL(base + '/personal/');
    await assertAccount(page, 'Cliente1 Demo', 'Cliente');
    await browserMenu(page).getByRole('link', { name: 'Mi perfil', exact: true }).click();
    await page.waitForURL(base + '/perfil');
    await assertAccount(page, 'Cliente1 Demo', 'Cliente');
    assert.equal((await context.request.get(base + '/admin')).status(), 403);
    console.log('El cliente conserva identidad y navegación al cambiar de pantalla.');

    // Comprobar que las pantallas siguen disponibles offline sin guardar la identidad.
    for (const path of ['/menu/', '/personal/']) {
        await page.goto(base + path);
        await assertAccount(page, 'Cliente1 Demo', 'Cliente');
        await page.waitForFunction(() => Boolean(navigator.serviceWorker.controller));
        await context.setOffline(true);
        await page.reload();
        await browserMenu(page).getByText('Visitante', { exact: true }).waitFor();
        assert.equal(await browserMenu(page).getByRole('link', { name: 'Mi perfil', exact: true }).count(), 0);
        await context.setOffline(false);
        await assertAccount(page, 'Cliente1 Demo', 'Cliente');
    }
    console.log('Ambas vistas offline usan identidad neutra y recuperan la sesión al volver la red.');

    await browserMenu(page).getByRole('button', { name: 'Cerrar sesión' }).click();
    await page.waitForURL(base + '/');
    const response = await context.request.get(base + '/api/sesion');
    assert.equal((await response.json()).user, null);
    assert.equal(response.headers()['cache-control']?.includes('no-store'), true);
    console.log('Cerrar sesión desde Personal invalida la sesión del servidor.');

    await login(page, 'administrador@example.test', '/admin');
    for (const path of ['/menu/', '/personal/']) {
        await page.goto(base + path);
        await assertAccount(page, 'Administrador Demo', 'Administrador');
    }
    assert.deepEqual(errors, []);
    console.log('El administrador conserva los enlaces de administración en ambas vistas.');
} finally {
    await browser.close();
}
