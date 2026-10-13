import { svelte } from '@sveltejs/vite-plugin-svelte';
import tailwindcss from '@tailwindcss/vite';
import { createHash } from 'node:crypto';
import { readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';

const outputDirectory = fileURLToPath(new URL('../backend/public/personal/', import.meta.url));

export default defineConfig({
    root: fileURLToPath(new URL('./personal/', import.meta.url)),
    base: './',
    plugins: [
        svelte({ configFile: fileURLToPath(new URL('./svelte.config.js', import.meta.url)) }),
        tailwindcss(),
        {
            name: 'personal-offline-shell',
            apply: 'build',
            closeBundle() {
                const assets = readdirSync(outputDirectory, { recursive: true })
                    .filter((file) => /\.(html|js|css|png|svg|webp)$/.test(file) && file !== 'sw.js')
                    .map((file) => file.replaceAll('\\', '/'))
                    .sort();
                const hash = createHash('sha256');
                for (const asset of assets) hash.update(readFileSync(`${outputDirectory}/${asset}`));
                const template = readFileSync(new URL('./personal/service-worker.js', import.meta.url), 'utf8');
                const worker = template
                    .replaceAll('__BUILD_REVISION__', hash.digest('hex').slice(0, 16))
                    .replaceAll('__PRECACHE_ASSETS__', JSON.stringify(assets));
                writeFileSync(`${outputDirectory}/sw.js`, worker);
            },
        },
    ],
    resolve: { alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) } },
    build: { outDir: outputDirectory, emptyOutDir: true },
});
