import './css/app.css';

import { createInertiaApp } from '@inertiajs/svelte';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { mount } from 'svelte';
import { route } from 'ziggy-js';
import { initializeTheme } from './lib/appearance';

globalThis.route = route;

createInertiaApp({
    resolve: (name) => resolvePageComponent(`./pages/${name}.svelte`, import.meta.glob('./pages/**/*.svelte')),
    setup({ el, App, props }) {
        mount(App, { target: el, props });
    },
    progress: {
        includeCSS: false,
    },
});

// This will set light / dark mode on load...
initializeTheme();
