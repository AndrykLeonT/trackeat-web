import { svelte } from '@sveltejs/vite-plugin-svelte';
import tailwindcss from '@tailwindcss/vite';
import { createHash } from 'node:crypto';
import { readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { fileURLToPath, URL } from 'node:url';

export function offlineConfig(module) {
    const output = fileURLToPath(new URL(`../backend/public/${module}/`, import.meta.url));
    return {
        root: fileURLToPath(new URL(`./${module}/`, import.meta.url)),
        base: './',
        plugins: [
            svelte({ configFile: fileURLToPath(new URL('./svelte.config.js', import.meta.url)) }),
            tailwindcss(),
            {
                name: `${module}-offline-shell`,
                apply: 'build',
                closeBundle() {
                    const assets = readdirSync(output, { recursive: true })
                        .filter((file) => /\.(html|js|css|png|svg|webp)$/.test(file) && file !== 'sw.js')
                        .map((file) => file.replaceAll('\\', '/'))
                        .sort();
                    const hash = createHash('sha256');
                    for (const asset of assets) hash.update(readFileSync(`${output}/${asset}`));
                    const template = readFileSync(new URL('./personal/service-worker.js', import.meta.url), 'utf8');
                    writeFileSync(
                        `${output}/sw.js`,
                        template
                            .replaceAll('trackeat-personal:', `trackeat-${module}:`)
                            .replaceAll('__BUILD_REVISION__', hash.digest('hex').slice(0, 16))
                            .replaceAll('__PRECACHE_ASSETS__', JSON.stringify(assets)),
                    );
                },
            },
        ],
        resolve: { alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) } },
        build: { outDir: output, emptyOutDir: true },
    };
}
