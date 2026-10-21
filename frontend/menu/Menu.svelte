<script>
    import { onMount } from 'svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import OrderChart from '@/components/OrderChart.svelte';
    import { api, session, connectLive, states, staffRoles } from '@/lib/trackeat';
    import { prepareOffline } from '../personal/offline.js';
    import * as db from './db.js';
    import logo from '@/assets/images/logo.png';
    import Utensils from '@lucide/svelte/icons/utensils';

    const emptyCart = () => ({ items: [], tipo_entrega: 'para_llevar', num_mesa: '', requestId: null, pending: false });
    let catalog = $state([]);
    let categories = $state([]);
    let name = $state('');
    let category = $state('');
    let cart = $state(emptyCart());
    let user = $state(null);
    let online = $state(true);
    let connected = $state(false);
    let connectionLabel = $state('Conectando…');
    let offlineReady = $state(false);
    let lastUpdated = $state('');
    let error = $state('');
    let message = $state('');
    let busy = $state(false);
    let initialized = $state(false);
    let orders = $state([]);
    let board = $state({ estados: {}, pedidos: [] });
    const total = $derived(cart.items.reduce((sum, item) => sum + item.cantidad * Number(item.precio), 0));
    const activeUser = $derived(user && (!user.demo_expires_at || Date.parse(user.demo_expires_at) > Date.now()));
    let disconnect = () => {};
    let searchVersion = 0;
    let refreshing = false;
    let refreshAgain = false;

    function status(label, value) {
        connectionLabel = label;
        connected = value;
    }
    async function persist() {
        await db.saveCart($state.snapshot(cart));
    }
    async function search() {
        const version = ++searchVersion;
        try {
            const rows = await db.products({ nombre: name, categoria: category });
            if (version === searchVersion) catalog = rows;
        } catch (e) {
            error = e.message;
        }
    }
    async function syncCatalog() {
        const data = await api('catalogo');
        await db.saveCatalog(data);
        categories = await db.categories();
        lastUpdated = data.actualizado;
        await search();
    }
    async function refreshOrders() {
        if (!user || !online) return;
        if (refreshing) {
            refreshAgain = true;
            return;
        }
        refreshing = true;
        try {
            orders = await api('pedidos');
            if (user.demo_room || staffRoles.includes(user.rol)) board = await api('tablero');
        } catch (e) {
            error = e.message;
            if (e.status === 401 || e.status === 403) {
                disconnect();
                status('Sesión no disponible', false);
                user = null;
                orders = [];
            }
        } finally {
            refreshing = false;
            if (refreshAgain) {
                refreshAgain = false;
                void refreshOrders();
            }
        }
    }
    async function reconnect() {
        online = navigator.onLine;
        if (!online) {
            disconnect();
            status('Sin conexión', false);
            return;
        }
        try {
            error = '';
            await syncCatalog();
            const data = await session();
            user = data.user;
            disconnect();
            disconnect = connectLive(data, refreshOrders, status);
            if (!user) status('Inicia sesión para pedir', false);
            await refreshOrders();
        } catch (e) {
            error = `Se conserva el catálogo local. ${e.message}`;
            status('Servidor no disponible', false);
        }
    }
    onMount(() => {
        let disposed = false;
        online = navigator.onLine;
        (async () => {
            try {
                cart = (await db.loadCart()) || emptyCart();
                categories = await db.categories();
                lastUpdated = (await db.updated())?.value || '';
                await search();
                if (disposed) return;
                initialized = true;
                prepareOffline()
                    .then(() => {
                        offlineReady = true;
                    })
                    .catch((e) => {
                        error = e.message;
                    });
                await reconnect();
                if (disposed) disconnect();
            } catch (e) {
                error = e.message;
            }
        })();
        window.addEventListener('online', reconnect);
        window.addEventListener('offline', reconnect);
        return () => {
            disposed = true;
            disconnect();
            window.removeEventListener('online', reconnect);
            window.removeEventListener('offline', reconnect);
        };
    });
    async function add(product) {
        if (cart.pending || busy) return;
        const existing = cart.items.find((item) => item.id_producto === product.id);
        if (existing) existing.cantidad = Math.min(100, existing.cantidad + 1);
        else cart.items.push({ id_producto: product.id, cantidad: 1, notas: '', nombre: product.nombre, precio: product.precio });
        try {
            await persist();
            message = `${product.nombre} agregado al carrito.`;
        } catch (e) {
            error = e.message;
        }
    }
    async function changeCart() {
        try {
            await persist();
        } catch (e) {
            error = e.message;
        }
    }
    async function remove(id) {
        cart.items = cart.items.filter((item) => item.id_producto !== id);
        await changeCart();
    }
    async function send(event) {
        event.preventDefault();
        if (!online || !activeUser || !cart.items.length || busy) return;
        busy = true;
        error = '';
        message = '';
        try {
            // Persist the exact request before sending. A lost response can safely be retried.
            cart.requestId ||= crypto.randomUUID();
            cart.pending = true;
            await persist();
            const order = await api('pedidos', {
                method: 'POST',
                body: {
                    client_request_id: cart.requestId,
                    tipo_entrega: cart.tipo_entrega,
                    ...(cart.tipo_entrega === 'comer_aqui' ? { num_mesa: Number(cart.num_mesa) } : {}),
                    items: cart.items.map(({ id_producto, cantidad, notas }) => ({ id_producto, cantidad, notas })),
                },
            });
            cart = emptyCart();
            await persist();
            message = `Pedido confirmado: ${order.numero_pedido}. Total: $${Number(order.total).toFixed(2)} MXN.`;
            await refreshOrders();
        } catch (e) {
            if ([401, 403, 422, 429].includes(e.status)) {
                cart.pending = false;
                cart.requestId = null;
                await changeCart();
            }
            error = cart.pending ? `${e.message} No se recibió confirmación. Reintenta el mismo envío para evitar duplicados.` : e.message;
        } finally {
            busy = false;
        }
    }
</script>

<AppLayout nativeNavigation currentUrl="/menu/" headerTitle="Menú Digital" {connected} {connectionLabel}>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-5">
            <div>
                <h1 class="flex items-center gap-2 text-2xl font-bold"><Utensils class="size-6 text-primary" />Menú Digital</h1>
                <p class="mt-1 text-sm text-muted-foreground">Elige tus productos y sigue tu pedido en tiempo real.</p>
            </div>
            {#if user}<p class="text-sm">{user.name}</p>{:else}<a
                    href="/login"
                    class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground">Iniciar sesión</a
                >{/if}
        </div>
        <p class="text-sm text-muted-foreground" data-testid="offline-status">
            {offlineReady && lastUpdated ? 'Menú disponible sin conexión.' : 'Preparando el menú sin conexión…'}
            {lastUpdated ? `Actualizado: ${new Date(lastUpdated).toLocaleString('es-MX')}.` : 'Abre con conexión para descargar el catálogo.'}
        </p>
        {#if !online}<p class="rounded-xl border border-border bg-muted p-3 text-sm">
                Sin conexión: puedes consultar el menú y preparar tu carrito. Confirma el envío cuando vuelva la red.
            </p>{/if}
        <p role="status" class="break-words text-sm text-primary">{message}</p>
        {#if error}<p role="alert" class="rounded-xl border border-destructive/30 bg-destructive/10 p-3 text-sm">{error}</p>{/if}
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
            <section aria-label="Catálogo de productos" class="min-w-0 space-y-4">
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="text-sm font-medium"
                        >Nombre empieza con<input
                            type="search"
                            bind:value={name}
                            oninput={search}
                            class="mt-1 w-full rounded-xl border border-input bg-background p-2.5"
                        /></label
                    >
                    <label class="text-sm font-medium"
                        >Categoría<select
                            aria-label="Categoría"
                            bind:value={category}
                            onchange={search}
                            class="mt-1 w-full rounded-xl border border-input bg-background p-2.5"
                            ><option value="">Todas</option>{#each categories as entry (entry.id)}<option value={entry.id}>{entry.nombre}</option
                                >{/each}</select
                        ></label
                    >
                </div>
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-2 2xl:grid-cols-3">
                    {#each catalog as product (product.id)}
                        <article class="flex flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-xs">
                            <img
                                src={product.imagen && product.imagen !== '/logo.svg' ? product.imagen : logo}
                                alt={product.nombre}
                                loading="lazy"
                                onerror={(event) => {
                                    event.currentTarget.onerror = null;
                                    event.currentTarget.src = logo;
                                }}
                                class="h-44 w-full bg-muted object-contain"
                            />
                            <div class="flex flex-1 flex-col gap-3 p-4">
                                <span class="text-xs font-semibold text-primary"
                                    >{categories.find((entry) => entry.id === product.id_categoria)?.nombre}</span
                                >
                                <h2 class="text-base font-bold">{product.nombre}</h2>
                                <p class="flex-1 text-sm text-muted-foreground">{product.descripcion}</p>
                                <div class="flex items-center justify-between gap-2">
                                    <strong>${Number(product.precio).toFixed(2)} MXN</strong><button
                                        type="button"
                                        onclick={() => add(product)}
                                        disabled={!initialized || cart.pending || busy}
                                        class="rounded-xl bg-primary px-3 py-2 text-sm font-semibold text-primary-foreground disabled:opacity-50"
                                        aria-label={`Agregar ${product.nombre}`}>Agregar</button
                                    >
                                </div>
                            </div>
                        </article>
                    {:else}<p class="text-sm text-muted-foreground">No hay productos para mostrar.</p>{/each}
                </div>
            </section>
            <section aria-labelledby="cart-title" class="min-w-0 self-start rounded-2xl border border-border bg-card p-5 shadow-xs">
                <h2 id="cart-title" class="text-lg font-bold">Tu carrito</h2>
                <form onsubmit={send} class="mt-3 space-y-4">
                    <fieldset disabled={cart.pending || busy} class="min-w-0 space-y-4">
                        {#each cart.items as item (item.id_producto)}
                            <div class="space-y-2 border-b border-border pb-3">
                                <h3 class="font-semibold">{item.nombre}</h3>
                                <label class="block text-sm"
                                    >Cantidad de {item.nombre}<input
                                        type="number"
                                        min="1"
                                        max="100"
                                        required
                                        bind:value={item.cantidad}
                                        onchange={changeCart}
                                        class="mt-1 w-full rounded-lg border border-input bg-background p-2"
                                    /></label
                                ><label class="block text-sm"
                                    >Notas para {item.nombre}<input
                                        maxlength="1000"
                                        bind:value={item.notas}
                                        onchange={changeCart}
                                        class="mt-1 w-full rounded-lg border border-input bg-background p-2"
                                    /></label
                                ><button type="button" onclick={() => remove(item.id_producto)} class="text-sm text-destructive"
                                    >Quitar {item.nombre}</button
                                >
                            </div>
                        {:else}<p class="text-sm text-muted-foreground">Agrega productos del menú.</p>{/each}
                        <label class="block text-sm"
                            >Entrega<select
                                aria-label="Entrega"
                                bind:value={cart.tipo_entrega}
                                onchange={changeCart}
                                class="mt-1 w-full rounded-lg border border-input bg-background p-2"
                                ><option value="para_llevar">Para llevar</option><option value="comer_aqui">Comer aquí</option></select
                            ></label
                        >
                        {#if cart.tipo_entrega === 'comer_aqui'}<label class="block text-sm"
                                >Número de mesa<input
                                    type="number"
                                    min="1"
                                    required
                                    bind:value={cart.num_mesa}
                                    onchange={changeCart}
                                    class="mt-1 w-full rounded-lg border border-input bg-background p-2"
                                /></label
                            >{/if}
                    </fieldset>
                    <p class="font-bold">Total estimado: ${total.toFixed(2)} MXN</p>
                    <p class="text-xs text-muted-foreground">El servidor verifica disponibilidad y precios al confirmar.</p>
                    {#if user && user.rol !== 'cliente'}<p class="text-sm">
                            Para pedir, usa una cuenta cliente o el enlace de demostración en otro navegador.
                        </p>{/if}
                    <button
                        type="submit"
                        disabled={!initialized || !online || !activeUser || user?.rol !== 'cliente' || !cart.items.length || busy}
                        class="w-full rounded-xl bg-primary p-3 text-sm font-semibold text-primary-foreground disabled:opacity-50"
                        >{busy ? 'Enviando…' : cart.pending ? 'Reintentar envío' : 'Confirmar pedido'}</button
                    >
                </form>
            </section>
        </div>
        {#if user}
            <section class="rounded-2xl border border-border bg-card p-5" aria-labelledby="my-orders">
                <h2 id="my-orders" class="text-lg font-bold">Mis pedidos</h2>
                <ul class="mt-3 space-y-3" aria-live="polite">
                    {#each orders as order (order.id)}<li class="flex flex-wrap justify-between gap-2 border-b border-border py-2 text-sm">
                            <span class="break-all">{order.numero_pedido}</span><strong
                                >{states[order.estado]} · ${Number(order.total).toFixed(2)}</strong
                            >
                        </li>{:else}<li class="text-sm text-muted-foreground">Aún no has enviado pedidos.</li>{/each}
                </ul>
            </section>
            {#if user.demo_room || staffRoles.includes(user.rol)}<div class="grid gap-5 lg:grid-cols-2">
                    <OrderChart totals={board.estados} orders={board.pedidos} showOrders={!!user.demo_room} />
                </div>{/if}
        {/if}
    </div>
</AppLayout>
