<script module>
    // El script de Cloudflare se carga una sola vez aunque haya varios formularios.
    let cargando = null;

    function cargarTurnstile() {
        if (window.turnstile) return Promise.resolve(window.turnstile);
        cargando ??= new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
            script.async = true;
            script.onload = () => resolve(window.turnstile);
            script.onerror = () => {
                cargando = null;
                reject(new Error('No se pudo cargar el captcha.'));
            };
            document.head.appendChild(script);
        });
        return cargando;
    }
</script>

<script>
    import { onMount } from 'svelte';

    // Captcha de Cloudflare Turnstile (Práctica 6). Cloudflare revisa el navegador y entrega un token
    // de un solo uso; el servidor lo verifica con la llave secreta. Sin llave del sitio no se muestra nada.
    let { siteKey = null, token = $bindable('') } = $props();

    let caja = $state(null);
    let widgetId = null;
    let error = $state('');

    onMount(() => {
        if (!siteKey) return;
        let desmontado = false;
        cargarTurnstile()
            .then((turnstile) => {
                if (desmontado) return;
                widgetId = turnstile.render(caja, {
                    sitekey: siteKey,
                    language: 'es',
                    callback: (t) => (token = t),
                    'expired-callback': () => (token = ''),
                    'error-callback': () => (token = ''),
                });
            })
            .catch((e) => (error = e.message));
        return () => {
            desmontado = true;
            if (widgetId !== null) window.turnstile?.remove(widgetId);
        };
    });

    // Cada token sirve una vez: después de enviar el formulario se pide uno nuevo.
    export function reiniciar() {
        token = '';
        if (widgetId !== null) window.turnstile?.reset(widgetId);
    }
</script>

{#if siteKey}
    <div bind:this={caja} class="min-h-[65px]"></div>
    {#if error}
        <p class="text-sm text-destructive">{error} Revisa tu conexión a internet y recarga la página.</p>
    {/if}
{/if}
