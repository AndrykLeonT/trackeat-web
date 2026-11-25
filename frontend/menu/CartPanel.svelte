<script>
    import logo from '@/assets/images/logo.png';
    import Check from '@lucide/svelte/icons/check';
    import ChevronDown from '@lucide/svelte/icons/chevron-down';
    import CircleAlert from '@lucide/svelte/icons/circle-alert';
    import Hash from '@lucide/svelte/icons/hash';
    import Info from '@lucide/svelte/icons/info';
    import LoaderCircle from '@lucide/svelte/icons/loader-circle';
    import LogIn from '@lucide/svelte/icons/log-in';
    import Minus from '@lucide/svelte/icons/minus';
    import NotebookPen from '@lucide/svelte/icons/notebook-pen';
    import Plus from '@lucide/svelte/icons/plus';
    import RotateCw from '@lucide/svelte/icons/rotate-cw';
    import ShoppingBag from '@lucide/svelte/icons/shopping-bag';
    import ShoppingCart from '@lucide/svelte/icons/shopping-cart';
    import Trash from '@lucide/svelte/icons/trash';
    import TriangleAlert from '@lucide/svelte/icons/triangle-alert';
    import UtensilsCrossed from '@lucide/svelte/icons/utensils-crossed';
    import WifiOff from '@lucide/svelte/icons/wifi-off';

    let {
        cart = $bindable(),
        catalog = [],
        limites,
        user = null,
        activeUser = false,
        online = true,
        initialized = false,
        busy = false,
        overLimit = false,
        total = 0,
        cartChanges = [],
        needsReview = false,
        onChangeCart,
        onUpdateQuantity,
        onRemove,
        onClear,
        onReviewCart,
        onSubmit,
    } = $props();

    const itemCount = $derived(cart.items.reduce((sum, item) => sum + Math.max(0, Number(item.cantidad) || 0), 0));
    const hasUnavailable = $derived(cart.items.some((item) => item.unavailable));
    const submitDisabled = $derived(
        !initialized || !online || !activeUser || user?.rol !== 'cliente' || !cart.items.length || overLimit || busy || needsReview || hasUnavailable,
    );

    function productImage(item) {
        const match = catalog.find((entry) => entry.id === item.id_producto);
        const src = item.imagen || match?.imagen;
        return src && src !== '/logo.svg' ? src : logo;
    }

    function lineSubtotal(item) {
        const qty = Math.max(0, Number(item.cantidad) || 0);
        return (qty * Number(item.precio)).toFixed(2);
    }
</script>

<section
    aria-labelledby="cart-title"
    class="min-w-0 self-start overflow-hidden rounded-2xl border border-border bg-card shadow-xs xl:sticky xl:top-20 xl:flex xl:max-h-[calc(100vh-6rem)] xl:flex-col"
>
    <div class="flex items-center justify-between gap-3 border-b border-border px-5 py-4">
        <div class="flex min-w-0 items-center gap-3">
            <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <ShoppingCart class="size-4.5" aria-hidden="true" />
            </div>
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <h2 id="cart-title" class="truncate text-base font-bold text-card-foreground">Tu carrito</h2>
                    {#if itemCount > 0}
                        <span
                            class="inline-flex shrink-0 items-center rounded-full bg-primary px-2 py-0.5 text-[11px] font-bold text-primary-foreground tabular-nums"
                        >
                            {itemCount}
                        </span>
                    {/if}
                </div>
                <p class="text-xs text-muted-foreground">
                    {#if itemCount === 0}
                        Sin artículos agregados
                    {:else}
                        {itemCount} {itemCount === 1 ? 'artículo seleccionado' : 'artículos seleccionados'}
                    {/if}
                </p>
            </div>
        </div>

        {#if cart.items.length > 0}
            <button
                type="button"
                onclick={onClear}
                disabled={cart.pending || busy}
                aria-label="Vaciar carrito"
                class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-border bg-background px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:border-destructive/30 hover:bg-destructive/10 hover:text-destructive focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-hidden disabled:pointer-events-none disabled:opacity-50"
            >
                <Trash class="size-3.5" aria-hidden="true" />
                <span>Vaciar</span>
            </button>
        {/if}
    </div>

    <form onsubmit={onSubmit} class="flex min-h-0 flex-1 flex-col">
        <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-5">
            {#if cartChanges.length}
                <div role="status" class="space-y-3 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3.5 text-sm">
                    <div class="flex items-start gap-2.5">
                        <TriangleAlert class="mt-0.5 size-4 shrink-0 text-amber-700" aria-hidden="true" />
                        <div class="min-w-0 space-y-1">
                            <p class="font-semibold text-foreground">Revisa los cambios del menú</p>
                            <ul class="space-y-1 text-xs leading-relaxed text-foreground/90">
                                {#each cartChanges as change, index (index)}
                                    <li class="break-words">{change}</li>
                                {/each}
                            </ul>
                        </div>
                    </div>
                    {#if needsReview}
                        <button
                            type="button"
                            onclick={onReviewCart}
                            class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-xs font-semibold text-foreground shadow-xs transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-hidden"
                        >
                            <Check class="size-3.5 text-primary" aria-hidden="true" />He revisado los cambios</button
                        >
                    {/if}
                </div>
            {/if}

            <fieldset disabled={cart.pending || busy} class="min-w-0 space-y-4">
                {#if cart.items.length > 0}
                    <ul class="space-y-3">
                        {#each cart.items as item (item.id_producto)}
                            <li
                                class="space-y-3 rounded-xl border p-3.5 transition-colors {item.unavailable
                                    ? 'border-destructive/40 bg-destructive/5'
                                    : 'border-border bg-background'}"
                            >
                                <div class="flex items-start gap-3">
                                    <img
                                        src={productImage(item)}
                                        alt=""
                                        aria-hidden="true"
                                        onerror={(event) => {
                                            event.currentTarget.onerror = null;
                                            event.currentTarget.src = logo;
                                        }}
                                        class="size-12 shrink-0 rounded-lg border border-border bg-muted object-contain"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <h3 class="text-sm leading-snug font-semibold break-words text-foreground">{item.nombre}</h3>
                                        <p class="mt-0.5 text-xs text-muted-foreground tabular-nums">
                                            ${Number(item.precio).toFixed(2)} MXN c/u
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        onclick={() => onRemove(item.id_producto)}
                                        aria-label={`Quitar ${item.nombre}`}
                                        title={`Quitar ${item.nombre}`}
                                        class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg border border-transparent text-muted-foreground transition-colors hover:border-destructive/20 hover:bg-destructive/10 hover:text-destructive focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-hidden disabled:pointer-events-none disabled:opacity-50"
                                    >
                                        <Trash class="size-4" aria-hidden="true" />
                                    </button>
                                </div>

                                {#if item.unavailable}
                                    <div
                                        class="flex items-center gap-1.5 rounded-lg bg-destructive/10 px-2.5 py-1.5 text-xs font-medium text-destructive"
                                    >
                                        <CircleAlert class="size-3.5 shrink-0" aria-hidden="true" />
                                        <span>No disponible. Quita este producto.</span>
                                    </div>
                                {/if}

                                <div class="flex items-center justify-between gap-3">
                                    <div class="inline-flex items-center rounded-lg border border-input bg-card p-0.5">
                                        <button
                                            type="button"
                                            onclick={() => onUpdateQuantity(item.id_producto, -1)}
                                            disabled={(Number(item.cantidad) || 1) <= 1}
                                            aria-label={`Disminuir cantidad de ${item.nombre}`}
                                            class="inline-flex size-7 items-center justify-center rounded-md text-foreground transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-hidden disabled:pointer-events-none disabled:opacity-35"
                                        >
                                            <Minus class="size-3.5" aria-hidden="true" />
                                        </button>
                                        <input
                                            type="number"
                                            min="1"
                                            max={limites.cantidad_max}
                                            required
                                            bind:value={item.cantidad}
                                            onchange={onChangeCart}
                                            aria-label={`Cantidad de ${item.nombre}`}
                                            class="w-11 [appearance:textfield] bg-transparent text-center text-sm font-semibold text-foreground tabular-nums focus:outline-hidden [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                        />
                                        <button
                                            type="button"
                                            onclick={() => onUpdateQuantity(item.id_producto, 1)}
                                            disabled={(Number(item.cantidad) || 0) >= limites.cantidad_max}
                                            aria-label={`Aumentar cantidad de ${item.nombre}`}
                                            class="inline-flex size-7 items-center justify-center rounded-md text-foreground transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-hidden disabled:pointer-events-none disabled:opacity-35"
                                        >
                                            <Plus class="size-3.5" aria-hidden="true" />
                                        </button>
                                    </div>

                                    <span class="text-sm font-bold text-foreground tabular-nums">
                                        ${lineSubtotal(item)} MXN
                                    </span>
                                </div>

                                <div class="relative">
                                    <NotebookPen
                                        class="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <input
                                        type="text"
                                        maxlength="1000"
                                        bind:value={item.notas}
                                        onchange={onChangeCart}
                                        aria-label={`Notas para ${item.nombre}`}
                                        placeholder="Nota opcional (ej. sin cebolla, salsa aparte)"
                                        class="w-full rounded-lg border border-input bg-muted/40 py-1.5 pr-2.5 pl-8 text-xs text-foreground placeholder:text-muted-foreground focus:border-ring focus:bg-background focus:ring-2 focus:ring-ring/20 focus:outline-hidden"
                                    />
                                </div>
                            </li>
                        {/each}
                    </ul>
                {:else}
                    <div class="rounded-xl border border-dashed border-border bg-muted/30 px-4 py-8 text-center">
                        <div class="mx-auto mb-2.5 flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
                            <ShoppingBag class="size-5" aria-hidden="true" />
                        </div>
                        <p class="text-sm font-semibold text-foreground">Tu carrito está vacío</p>
                        <p class="mt-1 text-xs text-muted-foreground">Agrega productos del menú.</p>
                    </div>
                {/if}

                <div class="space-y-3 rounded-xl border border-border bg-muted/30 p-3.5">
                    <div class="space-y-1.5">
                        <label for="cart-delivery" class="block text-xs font-semibold tracking-wide text-muted-foreground uppercase"> Entrega </label>
                        <div class="relative">
                            {#if cart.tipo_entrega === 'comer_aqui'}
                                <UtensilsCrossed
                                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                            {:else}
                                <ShoppingBag
                                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                            {/if}
                            <select
                                id="cart-delivery"
                                aria-label="Entrega"
                                bind:value={cart.tipo_entrega}
                                onchange={onChangeCart}
                                class="w-full appearance-none rounded-lg border border-input bg-background py-2 pr-9 pl-9 text-sm font-medium text-foreground transition-colors focus:border-ring focus:ring-2 focus:ring-ring/20 focus:outline-hidden"
                            >
                                <option value="para_llevar">Para llevar</option>
                                <option value="comer_aqui">Comer aquí</option>
                            </select>
                            <ChevronDown
                                class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground"
                                aria-hidden="true"
                            />
                        </div>
                    </div>

                    {#if cart.tipo_entrega === 'comer_aqui'}
                        <div class="space-y-1.5">
                            <label for="cart-table" class="block text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                Número de mesa
                            </label>
                            <div class="relative">
                                <Hash
                                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <input
                                    id="cart-table"
                                    type="number"
                                    min="1"
                                    max={limites.mesas_max}
                                    required
                                    placeholder={`Ej. 1 a ${limites.mesas_max}`}
                                    bind:value={cart.num_mesa}
                                    onchange={onChangeCart}
                                    class="w-full rounded-lg border border-input bg-background py-2 pr-3 pl-9 text-sm font-medium text-foreground tabular-nums placeholder:text-muted-foreground focus:border-ring focus:ring-2 focus:ring-ring/20 focus:outline-hidden"
                                />
                            </div>
                        </div>
                    {/if}
                </div>
            </fieldset>
        </div>

        <div class="shrink-0 space-y-3 border-t border-border bg-muted/20 p-5">
            <div class="flex items-baseline justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-foreground">Total estimado</p>
                    {#if itemCount > 0}
                        <p class="text-xs text-muted-foreground tabular-nums">
                            {itemCount}
                            {itemCount === 1 ? 'artículo' : 'artículos'}
                        </p>
                    {/if}
                </div>
                <p class="text-lg font-bold tracking-tight text-foreground tabular-nums">${total.toFixed(2)} MXN</p>
            </div>

            {#if overLimit}
                <div
                    role="alert"
                    class="flex items-start gap-2 rounded-xl border border-destructive/30 bg-destructive/10 p-3 text-xs leading-relaxed text-destructive"
                >
                    <CircleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <p>El pedido supera el máximo de ${Number(limites.total_max).toFixed(2)} MXN. Quita productos o divídelo en varios pedidos.</p>
                </div>
            {/if}

            <p class="flex items-start gap-2 text-xs leading-relaxed text-muted-foreground">
                <Info class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                <span>El servidor verifica disponibilidad y precios al confirmar.</span>
            </p>

            {#if !online}
                <div class="flex items-start gap-2 rounded-xl border border-border bg-muted/60 p-3 text-xs leading-relaxed text-muted-foreground">
                    <WifiOff class="mt-0.5 size-4 shrink-0 text-foreground" aria-hidden="true" />
                    <p>Sin conexión. Tu carrito queda guardado para enviarlo al volver la red.</p>
                </div>
            {:else if user && user.rol !== 'cliente'}
                <div class="flex items-start gap-2 rounded-xl border border-border bg-muted/60 p-3 text-xs leading-relaxed text-muted-foreground">
                    <Info class="mt-0.5 size-4 shrink-0 text-foreground" aria-hidden="true" />
                    <p>Para pedir, usa una cuenta cliente o el enlace de demostración en otro navegador.</p>
                </div>
            {:else if initialized && !user}
                <div class="flex items-center justify-between gap-2 rounded-xl border border-border bg-muted/60 p-3 text-xs text-muted-foreground">
                    <p class="leading-relaxed">Inicia sesión con tu cuenta cliente para confirmar.</p>
                    <a
                        href="/login"
                        class="inline-flex shrink-0 items-center gap-1 rounded-lg border border-border bg-card px-2.5 py-1.5 text-xs font-semibold text-foreground transition-colors hover:bg-accent"
                    >
                        <LogIn class="size-3.5" aria-hidden="true" />
                        <span>Entrar</span>
                    </a>
                </div>
            {/if}

            <button
                type="submit"
                disabled={submitDisabled}
                class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground shadow-xs transition-colors hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-hidden disabled:pointer-events-none disabled:opacity-50"
            >
                {#if busy}
                    <LoaderCircle class="size-4 animate-spin" aria-hidden="true" />Enviando…
                {:else if cart.pending}
                    <RotateCw class="size-4" aria-hidden="true" />Reintentar envío
                {:else}
                    <Check class="size-4" aria-hidden="true" />Confirmar pedido
                {/if}
            </button>
        </div>
    </form>
</section>
