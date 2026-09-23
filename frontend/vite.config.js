import { svelte } from '@sveltejs/vite-plugin-svelte';
import laravel from 'laravel-vite-plugin';
import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';

// El frontend vive separado del backend (../backend), así que las rutas
// que Laravel necesita (public, hot, ssr, .env) apuntan hacia allá.
export default defineConfig({
    envDir: '../backend',
    plugins: [
        laravel({
            input: ['src/css/app.css', 'src/app.js'],
            ssr: 'src/ssr.js',
            publicDirectory: '../backend/public',
            hotFile: '../backend/public/hot',
            ssrOutputDirectory: '../backend/bootstrap/ssr',
            refresh: ['../backend/resources/views/**', '../backend/routes/**'],
        }),
        svelte({configFile:'./svelte.config.js' }),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./src', import.meta.url)),
        },
    },
});
