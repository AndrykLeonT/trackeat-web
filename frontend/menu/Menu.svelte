<script>
    import { onMount } from 'svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import { api, session } from '@/lib/trackeat';
    import { formatoFolio } from '@/lib/folio';
    import { prepareOffline, downloadGalleries, galleryReady } from './offline.js';
    import * as db from './db.js';
    import logo from '@/assets/images/logo.png';
    import Utensils from '@lucide/svelte/icons/utensils';

    const emptyCart = () => ({
        items: [],
        tipo_entrega: 'para_llevar',
        num_mesa: '',
        requestId: null,
        pending: false,
        needsReview: false,
        reviewChanges: [],
    });
    let catalog = $state([]);
    let categories = $state([]);
    let name = $state('');
    let category = $state('');
    let cart = $state(emptyCart());
    let user = $state(null);
    // Límites del pedido (T-07) que manda /api/sesion; sin sesión se usan los valores por omisión del servidor.
    let limites = $state({ mesas_max: 30, cantidad_max: 100, total_max: '5000.00' });
    let online = $state(true);
    let connected = $state(false);
    let connectionLabel = $state('Conectando…');
    let offlineReady = $state(false);
    let imagesReady = $state(false);
    let galleryWarning = $state('');
    let detailId = $state(location.pathname.match(/^\/menu\/(\d+)\/?$/)?.[1] ?? null);
    let detail = $state(null);
    let selectedImage = $state(0);
    let cartChanges = $state([]);
    let needsReview = $state(false);
    let confirmedOrder = $state(null);
    let lastUpdated = $state('');
    let error = $state('');
    let message = $state('');
    let busy = $state(false);
    let initialized = $state(false);
    const total = $derived(cart.items.reduce((sum, item) => sum + item.cantidad * Number(item.precio), 0));
    const overLimit = $derived(total > Number(limites.total_max));
    const activeUser = $derived(user && (!user.demo_expires_at || Date.parse(user.demo_expires_at) > Date.now()));
    let searchVersion = 0;

    async function openDetail(id, push = true) {
        detailId = id;
        selectedImage = 0;
        detail = id ? await db.product(id) : null;
        if (push) history.pushState({}, '', id ? `/menu/${id}` : '/menu/');
    }
    async function reconcileCart() {
        const changes = [];
        for (const item of cart.items) {
            const product = await db.product(item.id_producto);
            item.unavailable = !product || product.disponible === false;
            if (item.unavailable) changes.push(`${item.nombre}: agotado o retirado del menú. Quita este producto para continuar.`);
            else if (Number(item.precio) !== Number(product.precio)) {
                changes.push(`${item.nombre}: cambió de $${Number(item.precio).toFixed(2)} a $${Number(product.precio).toFixed(2)} MXN.`);
                item.precio = product.precio;
            }
        }
        if (changes.length) {
            cartChanges = changes;
            needsReview = true;
            cart.needsReview = true;
            cart.reviewChanges = changes;
        }
        await persist();
    }
    async function reviewCart() {
        needsReview = false;
        cartChanges = [];
        cart.needsReview = false;
        cart.reviewChanges = [];
        await changeCart();
    }

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
    async function clearFilters() {
        name = '';
        category = '';
        await search();
    }
    async function syncCatalog() {
        const data = await api('catalogo');
        await db.saveCatalog(data);
        categories = await db.categories();
        lastUpdated = data.actualizado;
        await search();
        await openDetail(detailId, false);
        if (!cart.pending) await reconcileCart();
        try {
            await downloadGalleries(data);
            imagesReady = true;
            galleryWarning = '';
        } catch {
            imagesReady = false;
            galleryWarning = 'El texto del menú está guardado, pero faltan imágenes sin conexión. Reintenta con internet y espacio disponible.';
        }
    }
    async function reconnect() {
        online = navigator.onLine;
        if (!online) {
            status('Sin conexión', false);
            return;
        }
        try {
            error = '';
            await syncCatalog();
            const data = await session();
            user = data.user;
            if (data.limites) limites = data.limites;
            status(user ? 'Conectado al servidor' : 'Inicia sesión para pedir', !!user);
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
                needsReview = cart.needsReview ?? false;
                cartChanges = cart.reviewChanges ?? [];
                categories = await db.categories();
                lastUpdated = (await db.updated())?.value || '';
                await search();
                await openDetail(detailId, false);
                imagesReady = await galleryReady({ productos: await db.products() });
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
            } catch (e) {
                error = e.message;
            }
        })();
        window.addEventListener('online', reconnect);
        window.addEventListener('offline', reconnect);
        const pop = () => openDetail(location.pathname.match(/^\/menu\/(\d+)\/?$/)?.[1] ?? null, false);
        window.addEventListener('popstate', pop);
        return () => {
            disposed = true;
            window.removeEventListener('online', reconnect);
            window.removeEventListener('offline', reconnect);
            window.removeEventListener('popstate', pop);
        };
    });
    async function add(product) {
        if (cart.pending || busy || product.disponible === false) return;
        const existing = cart.items.find((item) => item.id_producto === product.id);
        if (existing) existing.cantidad = Math.min(limites.cantidad_max, existing.cantidad + 1);
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
        if (!cart.items.length) {
            cartChanges = [];
            needsReview = false;
            cart.needsReview = false;
            cart.reviewChanges = [];
        }
        await changeCart();
    }
    async function send(event) {
        event.preventDefault();
        if (!online || !activeUser || !cart.items.length || overLimit || busy || needsReview || cart.items.some((item) => item.unavailable)) return;
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
                    items: cart.items.map(({ id_producto, cantidad, notas, precio }) => ({
                        id_producto,
                        cantidad,
                        notas,
                        precio_confirmado: String(precio),
                    })),
                },
            });
            cart = emptyCart();
            await persist();
            message = `Pedido confirmado: ${formatoFolio(order.numero_pedido)}. Total: $${Number(order.total).toFixed(2)} MXN.`;
            confirmedOrder = order.id;
            needsReview = false;
            cartChanges = [];
        } catch (e) {
            if ([401, 403, 409, 422, 429].includes(e.status)) {
                cart.pending = false;
                cart.requestId = null;
                await changeCart();
                if ([409, 422].includes(e.status)) await syncCatalog().catch(() => {});
            }
            error = cart.pending ? `${e.message} No se recibió confirmación. Reintenta el mismo envío para evitar duplicados.` : e.message;
        } finally {
            busy = false;
        }
    }
</script>

<AppLayout nativeNavigation currentUrl="/menu/" headerTitle="Menú Digital" userRole={user?.rol} {connected} {connectionLabel}>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-5">
            <div>
                <h1 class="flex items-center gap-2 text-2xl font-bold"><Utensils class="size-6 text-primary" />Menú Digital</h1>
                <p class="mt-1 text-sm text-muted-foreground">Elige tus productos y confirma tu pedido. Consulta su seguimiento en Comandas.</p>
            </div>
            {#if user}<p class="text-sm">{user.name}</p>{:else}<a
                    href="/login"
                    class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground">Iniciar sesión</a
                >{/if}
        </div>
        <p class="text-sm text-muted-foreground" data-testid="offline-status">
            {offlineReady && imagesReady && lastUpdated
                ? 'Menú y galerías disponibles sin conexión.'
                : lastUpdated
                  ? 'Menú guardado; verificando descarga completa de imágenes.'
                  : 'Preparando el menú sin conexión…'}
            {lastUpdated ? `Actualizado: ${new Date(lastUpdated).toLocaleString('es-MX')}.` : 'Abre con conexión para descargar el catálogo.'}
        </p>
        {#if galleryWarning}<p role="alert" class="text-sm text-destructive">{galleryWarning}</p>{/if}
        <button
            type="button"
            onclick={reconnect}
            disabled={!online || busy || cart.pending}
            class="text-sm font-semibold text-primary disabled:opacity-50">Actualizar menú</button
        >
        {#if !online}<p class="rounded-xl border border-border bg-muted p-3 text-sm">
                Sin conexión: puedes consultar el menú y preparar tu carrito. Confirma el envío cuando vuelva la red.
            </p>{/if}
        <p role="status" class="break-words text-sm text-primary">{message}</p>
        {#if confirmedOrder}<a href={`/pedidos/${confirmedOrder}`} class="font-semibold text-primary">Ver seguimiento del pedido</a>{/if}
        {#if error}<p role="alert" class="rounded-xl border border-destructive/30 bg-destructive/10 p-3 text-sm">{error}</p>{/if}
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
            <section aria-label="Catálogo de productos" class="min-w-0 space-y-4">
                {#if detailId}
                    <section aria-label="Detalle de producto" class="space-y-4 rounded-2xl border border-border bg-card p-5">
                        <button type="button" onclick={() => openDetail(null)} class="text-primary">Volver al menú</button>
                        {#if detail}<h2 class="text-2xl font-bold break-words">{detail.nombre}</h2>
                            <img
                                src={detail.imagenes?.[selectedImage]?.url === '/logo.svg' ? logo : detail.imagenes?.[selectedImage]?.url || logo}
                                alt={`${detail.nombre}, imagen ${selectedImage + 1}`}
                                onerror={(e) => {
                                    e.currentTarget.onerror = null;
                                    e.currentTarget.src = logo;
                                }}
                                class="max-h-80 w-full rounded-xl bg-muted object-contain"
                            />
                            <div class="flex flex-wrap gap-2">
                                {#each detail.imagenes || [] as image, index (image.id)}<button
                                        type="button"
                                        aria-label={`Ver imagen ${index + 1}`}
                                        aria-pressed={selectedImage === index}
                                        onclick={() => (selectedImage = index)}
                                        class="rounded-lg border border-input px-3 py-2">{index + 1}</button
                                    >{/each}
                            </div>
                            <p class="whitespace-pre-wrap break-words">{detail.descripcion}</p>
                            <strong>${Number(detail.precio).toFixed(2)} MXN</strong>
                            <button
                                type="button"
                                disabled={!initialized || cart.pending || busy || detail.disponible === false}
                                onclick={() => add(detail)}
                                class="ml-3 rounded-xl bg-primary px-4 py-2 font-semibold text-primary-foreground disabled:opacity-50"
                                >{detail.disponible === false ? 'Agotado' : 'Agregar al carrito'}</button
                            >
                        {:else}<h2 class="text-xl font-bold">Producto no disponible</h2>
                            <p>El producto no está en el catálogo guardado. Actualiza el menú cuando tengas conexión.</p>{/if}
                    </section>
                {/if}
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="text-sm font-medium"
                        >Buscar producto<input
                            type="search"
                            placeholder="Ej. Hamburguesa"
                            aria-describedby="product-search-help"
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
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p id="product-search-help" class="text-sm text-muted-foreground">Busca por el nombre del producto, no por tu nombre.</p>
                    {#if name || category}<button
                            type="button"
                            onclick={clearFilters}
                            class="rounded-xl border border-input px-3 py-2 text-sm font-semibold">Mostrar todo el menú</button
                        >{/if}
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
                                <h2 class="text-base font-bold">
                                    <a
                                        href={`/menu/${product.id}`}
                                        onclick={(event) => {
                                            event.preventDefault();
                                            openDetail(product.id);
                                        }}
                                        class="hover:underline">{product.nombre}</a
                                    >
                                </h2>
                                {#if product.disponible === false}<p class="text-sm font-semibold text-destructive">Agotado</p>{/if}
                                <p class="flex-1 text-sm text-muted-foreground">{product.descripcion}</p>
                                <div class="flex items-center justify-between gap-2">
                                    <strong>${Number(product.precio).toFixed(2)} MXN</strong><button
                                        type="button"
                                        onclick={() => add(product)}
                                        disabled={!initialized || cart.pending || busy || product.disponible === false}
                                        class="rounded-xl bg-primary px-3 py-2 text-sm font-semibold text-primary-foreground disabled:opacity-50"
                                        aria-label={`Agregar ${product.nombre}`}>Agregar</button
                                    >
                                </div>
                            </div>
                        </article>
                    {:else}<p class="text-sm text-muted-foreground">
                            No hay productos que coincidan. Pulsa «Mostrar todo el menú» para quitar los filtros.
                        </p>{/each}
                </div>
            </section>
            <section aria-labelledby="cart-title" class="min-w-0 self-start rounded-2xl border border-border bg-card p-5 shadow-xs">
                <h2 id="cart-title" class="text-lg font-bold">Tu carrito</h2>
                {#if cartChanges.length}<div role="status" class="mt-3 space-y-2 rounded-xl bg-muted p-3 text-sm">
                        <p class="font-semibold">Revisa los cambios del menú</p>
                        {#each cartChanges as change, index (index)}<p>{change}</p>{/each}{#if needsReview}<button
                                type="button"
                                onclick={reviewCart}
                                class="font-semibold text-primary">He revisado los cambios</button
                            >{/if}
                    </div>{/if}
                <form onsubmit={send} class="mt-3 space-y-4">
                    <fieldset disabled={cart.pending || busy} class="min-w-0 space-y-4">
                        {#each cart.items as item (item.id_producto)}
                            <div class="space-y-2 border-b border-border pb-3">
                                <h3 class="font-semibold">{item.nombre}</h3>
                                {#if item.unavailable}<p class="text-sm text-destructive">No disponible. Quita este producto.</p>{/if}
                                <label class="block text-sm"
                                    >Cantidad de {item.nombre}<input
                                        type="number"
                                        min="1"
                                        max={limites.cantidad_max}
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
                                    max={limites.mesas_max}
                                    required
                                    bind:value={cart.num_mesa}
                                    onchange={changeCart}
                                    class="mt-1 w-full rounded-lg border border-input bg-background p-2"
                                /></label
                            >{/if}
                    </fieldset>
                    <p class="font-bold">Total estimado: ${total.toFixed(2)} MXN</p>
                    {#if overLimit}<p role="alert" class="text-sm text-destructive">
                            El pedido supera el máximo de ${Number(limites.total_max).toFixed(2)} MXN. Quita productos o divídelo en varios pedidos.
                        </p>{/if}
                    <p class="text-xs text-muted-foreground">El servidor verifica disponibilidad y precios al confirmar.</p>
                    {#if user && user.rol !== 'cliente'}<p class="text-sm">
                            Para pedir, usa una cuenta cliente o el enlace de demostración en otro navegador.
                        </p>{/if}
                    <button
                        type="submit"
                        disabled={!initialized ||
                            !online ||
                            !activeUser ||
                            user?.rol !== 'cliente' ||
                            !cart.items.length ||
                            overLimit ||
                            busy ||
                            needsReview ||
                            cart.items.some((item) => item.unavailable)}
                        class="w-full rounded-xl bg-primary p-3 text-sm font-semibold text-primary-foreground disabled:opacity-50"
                        >{busy ? 'Enviando…' : cart.pending ? 'Reintentar envío' : 'Confirmar pedido'}</button
                    >
                </form>
            </section>
        </div>
    </div>
</AppLayout>
