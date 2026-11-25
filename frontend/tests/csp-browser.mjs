import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdir, mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium, firefox } from 'playwright';

const origin = 'https://localhost:8443';
const fixture = origin + '/__trackeat-prueba-csp';
const visible = process.argv.includes('--visible');
const ca = process.env.TRACKEAT_CSP_CA;
const profile = ca ? await mkdtemp(join(tmpdir(), 'trackeat-csp-')) : null;
let browser;
let context;

const externalScript = `
    document.querySelector('#externo').textContent = 'Ejecutado';
    document.addEventListener('securitypolicyviolation', (event) => {
        if (event.blockedURI === 'inline' && event.disposition === 'enforce') {
            document.querySelector('#evento').textContent = event.effectiveDirective + ' bloqueó inline';
        }
    });
`;

const fixtureHtml = `
<!doctype html>
<html lang="es">
<meta charset="utf-8">
<title>TrackEat · Verificación de CSP</title>
<main>
    <h1>TrackEat · Verificación de CSP</h1>
    <p>Script externo del mismo origen: <strong id="externo">Sin ejecutar</strong></p>
    <p>Script inline de prueba: <strong id="inline">Sin ejecutar</strong></p>
    <p>Evento del navegador: <strong id="evento">Ninguno</strong></p>
</main>
<script src="/__trackeat-prueba-csp/control.js"></script>
<script>document.querySelector('#inline').textContent = 'Ejecutado';</script>
</html>
`;

try {
    if (profile) {
        // NSS limita la confianza a este perfil temporal de Firefox.
        const store = 'sql:' + profile;
        execFileSync('certutil', ['-N', '-d', store, '--empty-password']);
        execFileSync('certutil', ['-A', '-d', store, '-n', 'TrackEat CA', '-t', 'C,,', '-i', resolve(ca)]);
        context = await firefox.launchPersistentContext(profile, { headless: !visible, serviceWorkers: 'block' });
    } else {
        browser = await chromium.launch({ channel: process.platform === 'win32' ? 'msedge' : 'chrome', headless: !visible });
        context = await browser.newContext({ serviceWorkers: 'block' });
    }

    const page = context.pages()[0] ?? (await context.newPage());
    const live = await page.goto(origin + '/salud');
    assert.equal(live?.status(), 200, 'Nginx debe estar activo por HTTPS.');
    const policy = await live.headerValue('content-security-policy');
    assert.ok(policy, 'Nginx debe emitir una cabecera Content-Security-Policy activa.');

    // Ambas páginas existen solo en este navegador. La segunda recibe la política obtenida de Nginx.
    const withoutPolicy = fixture + '?modo=control';
    const withPolicy = fixture + '?modo=protegido';
    await page.route(fixture + '/control.js', (route) => route.fulfill({ contentType: 'application/javascript', body: externalScript }));
    await page.route(withoutPolicy, (route) => route.fulfill({ contentType: 'text/html; charset=utf-8', body: fixtureHtml }));
    await page.route(withPolicy, (route) =>
        route.fulfill({
            contentType: 'text/html; charset=utf-8',
            headers: { 'content-security-policy': policy },
            body: fixtureHtml,
        }),
    );

    await page.goto(withoutPolicy);
    assert.equal(await page.locator('#externo').textContent(), 'Ejecutado');
    assert.equal(await page.locator('#inline').textContent(), 'Ejecutado');
    console.log('Control sin CSP: se ejecutaron los dos scripts de prueba.');

    const protectedResponse = await page.goto(withPolicy);
    assert.equal(await protectedResponse.headerValue('content-security-policy'), policy);
    await page
        .locator('#evento')
        .filter({ hasText: /^script-src(?:-elem)? bloqueó inline$/ })
        .waitFor({ timeout: 5000 });
    assert.equal(await page.locator('#externo').textContent(), 'Ejecutado');
    assert.equal(await page.locator('#inline').textContent(), 'Sin ejecutar');
    console.log('CSP de Nginx: se ejecutó el script externo y el navegador bloqueó el inline.');
    console.log('Evento observado:', await page.locator('#evento').textContent());

    const output = fileURLToPath(new URL('../../output/playwright/', import.meta.url));
    await mkdir(output, { recursive: true });
    await page.screenshot({ path: join(output, 'csp-trackeat.png') });
    if (visible) {
        console.log('Cierra la pestaña de prueba para terminar.');
        await page.waitForEvent('close', { timeout: 0 });
    }
} finally {
    if (context) await context.close();
    if (browser) await browser.close();
    if (profile) await rm(profile, { recursive: true, force: true });
}
