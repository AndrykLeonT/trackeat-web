import './css/app.css';

import { createInertiaApp } from '@inertiajs/svelte';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { mount } from 'svelte';
import { initializeTheme } from './lib/appearance';

createInertiaApp({
    resolve: (name) => resolvePageComponent(`./pages/${name}.svelte`, import.meta.glob('./pages/**/*.svelte')),
    setup({ el, App, props }) {
        mount(App, { target: el, props });
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
