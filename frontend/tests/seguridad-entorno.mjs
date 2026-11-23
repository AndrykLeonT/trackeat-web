import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';

const origin = process.env.TRACKEAT_SECURITY_URL;
assert(origin && new URL(origin).protocol === 'https:', 'Define TRACKEAT_SECURITY_URL con el túnel HTTPS ficticio.');
const names = ['strict-transport-security', 'x-content-type-options', 'x-frame-options', 'referrer-policy', 'permissions-policy', 'content-security-policy'];
const results = { origin, observations: [] };
for (const path of ['/menu/', '/login', '/api/sesion']) {
    const response = await fetch(new URL(path, origin), { redirect: 'manual' });
    const cookies = response.headers.getSetCookie().map((cookie) => ({
        name: cookie.split('=')[0], httpOnly: /;\s*httponly(?:;|$)/i.test(cookie),
        secure: /;\s*secure(?:;|$)/i.test(cookie), sameSite: cookie.match(/;\s*samesite=([^;]+)/i)?.[1] ?? null,
    }));
    results.observations.push({ path, status: response.status, headers: Object.fromEntries(names.map((name) => [name, response.headers.get(name)])), cookies });
    await response.body?.cancel();
}
const http = new URL('/menu/', origin); http.protocol = 'http:';
const redirect = await fetch(http, { redirect: 'manual' });
results.httpRedirect = { status: redirect.status, location: redirect.headers.get('location'), redirectsToHttps: [301, 302, 307, 308].includes(redirect.status) && redirect.headers.get('location')?.startsWith('https://') === true };
await redirect.body?.cancel();
const directory = fileURLToPath(new URL('../../docs/evidencias/', import.meta.url));
await mkdir(directory, { recursive: true });
await writeFile(`${directory}/seguridad-entorno.json`, JSON.stringify(results, null, 2));
console.log(JSON.stringify(results, null, 2));
