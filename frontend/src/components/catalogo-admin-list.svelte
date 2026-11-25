<script>
    import { tick } from 'svelte';
    import { page, router, Link } from '@inertiajs/svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import PageTitle from '@/components/page-title.svelte';
    import Package from '@lucide/svelte/icons/package';
    import Tags from '@lucide/svelte/icons/tags';
    import Plus from '@lucide/svelte/icons/plus';
    import Search from '@lucide/svelte/icons/search';
    import ExternalLink from '@lucide/svelte/icons/external-link';
    import Pencil from '@lucide/svelte/icons/pencil';
    import Trash from '@lucide/svelte/icons/trash';
    import RotateCcw from '@lucide/svelte/icons/rotate-ccw';
    import CircleCheck from '@lucide/svelte/icons/circle-check';
    import CircleAlert from '@lucide/svelte/icons/circle-alert';
    import X from '@lucide/svelte/icons/x';

    let { rows, kind } = $props();
    let search = $state('');
    let state = $state('activos');
    let pending = $state(false);
    let target = $state(null);
    let confirmDialog = $state(null);
    let cancelButton = $state(null);

    const isProducts = $derived(kind === 'productos');
    const title = $derived(isProducts ? 'Productos' : 'Categorías');
    const subtitle = $derived(
        isProducts
            ? 'Administra el catálogo de productos, precios y disponibilidad en el menú.'
            : 'Organiza las categorías del menú y su estación de preparación.',
    );
    const base = $derived(`/admin/${kind}`);
    const stationLabels = { cocina: 'Cocina', barra: 'Barra' };
    const key = (value) =>
        value
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim();
    const filtered = $derived(
        rows.filter((row) => key(row.nombre).includes(key(search)) && (state === 'todos' || row.eliminado === (state === 'eliminados'))),
    );
    const errorEntries = $derived(Object.entries($page.props.errors || {}));

    async function requestDelete(row) {
        target = row;
        await tick();
        if (confirmDialog && !confirmDialog.open) {
            confirmDialog.showModal();
        }
        cancelButton?.focus();
    }

    function closeDeleteDialog() {
        if (pending) return;
        confirmDialog?.close();
        target = null;
    }

    function operate(row, restore = false) {
        pending = true;
        const options = {
            preserveScroll: true,
            onFinish: () => {
                pending = false;
                confirmDialog?.close();
                target = null;
            },
        };
        if (restore) router.post(`${base}/${row.id}/restaurar`, {}, options);
        else router.delete(`${base}/${row.id}`, options);
    }
</script>

<PageTitle title={`Administrar ${title.toLowerCase()} — TrackEat`} />
<AppLayout headerTitle={`Administrar ${title.toLowerCase()}`}>
    <div class="space-y-6">
        <!-- Encabezado principal alineado con los módulos de TrackEat -->
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-border pb-5">
            <div class="min-w-0">
                <h1 class="flex items-center gap-2.5 text-2xl font-bold tracking-tight text-foreground">
                    <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        {#if isProducts}
                            <Package class="size-5" aria-hidden="true" />
                        {:else}
                            <Tags class="size-5" aria-hidden="true" />
                        {/if}
                    </span>
                    <span>{title}</span>
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">{subtitle}</p>
            </div>
            <Link
                href={`${base}/crear`}
                class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground shadow-xs transition-colors hover:bg-primary/90 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            >
                <Plus class="size-4" aria-hidden="true" />
                <span>{isProducts ? 'Crear producto' : 'Crear categoría'}</span>
            </Link>
        </div>

        <!-- Subnavegación tipo pestañas + acceso rápido al menú -->
        <nav aria-label="Administración de catálogo" class="flex flex-wrap items-center justify-between gap-3">
            <div class="inline-flex items-center gap-1 rounded-xl border border-border bg-muted/60 p-1">
                <Link
                    href="/admin/productos"
                    aria-current={isProducts ? 'page' : undefined}
                    class="inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium transition-colors {isProducts
                        ? 'bg-card font-semibold text-foreground shadow-xs ring-1 ring-border'
                        : 'text-muted-foreground hover:bg-card/60 hover:text-foreground'}"
                >
                    <Package class="size-4" aria-hidden="true" />
                    <span>Productos</span>
                </Link>
                <Link
                    href="/admin/categorias"
                    aria-current={!isProducts ? 'page' : undefined}
                    class="inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium transition-colors {!isProducts
                        ? 'bg-card font-semibold text-foreground shadow-xs ring-1 ring-border'
                        : 'text-muted-foreground hover:bg-card/60 hover:text-foreground'}"
                >
                    <Tags class="size-4" aria-hidden="true" />
                    <span>Categorías</span>
                </Link>
            </div>
            <a
                href="/menu/"
                class="inline-flex items-center gap-2 rounded-xl border border-border bg-card px-3.5 py-2 text-sm font-semibold text-foreground shadow-2xs transition-colors hover:bg-accent hover:text-accent-foreground"
            >
                <ExternalLink class="size-4 text-muted-foreground" aria-hidden="true" />
                <span>Ver menú</span>
            </a>
        </nav>

        <!-- Mensajes de estado y errores -->
        {#if $page.props.flash?.success}
            <div
                role="status"
                class="flex items-start gap-3 rounded-xl border border-chart-2/30 bg-chart-2/10 px-4 py-3 text-sm font-medium text-chart-2"
            >
                <CircleCheck class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                <p>{$page.props.flash.success}</p>
            </div>
        {/if}
        {#if errorEntries.length}
            <div class="space-y-2">
                {#each errorEntries as [field, error] (field)}
                    <div
                        role="alert"
                        class="flex items-start gap-3 rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive"
                    >
                        <CircleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                        <p>{error}</p>
                    </div>
                {/each}
            </div>
        {/if}

        <!-- Barra de filtros y búsqueda -->
        <div class="grid gap-4 rounded-2xl border border-border bg-card p-4 shadow-xs sm:grid-cols-[minmax(0,1fr)_220px]">
            <div>
                <label for="catalogo-buscar" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    Buscar por nombre
                </label>
                <div class="relative mt-1.5">
                    <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                    <input
                        id="catalogo-buscar"
                        type="search"
                        bind:value={search}
                        placeholder={isProducts ? 'Ej. Hamburguesa, Café…' : 'Ej. Bebidas, Hamburguesas…'}
                        class="h-10 w-full rounded-xl border border-input bg-background py-2 pr-3 pl-9 text-sm text-foreground placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    />
                </div>
            </div>
            <div>
                <label for="catalogo-mostrar" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground">Mostrar</label>
                <select
                    id="catalogo-mostrar"
                    aria-label="Mostrar"
                    bind:value={state}
                    class="mt-1.5 h-10 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm text-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                >
                    <option value="activos">Sin eliminar</option>
                    <option value="eliminados">Eliminados</option>
                    <option value="todos">Todos</option>
                </select>
            </div>
        </div>

        <!-- Tabla de catálogo -->
        <div class="overflow-hidden rounded-2xl border border-border bg-card shadow-xs">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border bg-muted/30 px-4 py-3 text-xs text-muted-foreground">
                <span class="inline-flex items-center gap-2 font-semibold text-foreground">
                    <span class="rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-bold text-primary">
                        {filtered.length} {filtered.length === 1 ? 'registro' : 'registros'}
                    </span>
                </span>
                <span>Desactivar oculta categorías y marca productos como agotados.</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <caption class="sr-only">
                        {filtered.length} registros. Desactivar oculta categorías y marca productos como agotados.
                    </caption>
                    <thead class="border-b border-border bg-muted/50 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        <tr>
                            <th scope="col" class="px-4 py-3">Nombre</th>
                            <th scope="col" class="px-4 py-3">{isProducts ? 'Categoría y precio' : 'Estación'}</th>
                            <th scope="col" class="px-4 py-3">Estado</th>
                            <th scope="col" class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        {#each filtered as row (row.id)}
                            <tr class="transition-colors hover:bg-muted/30">
                                <td class="px-4 py-3.5 font-semibold break-words text-foreground">{row.nombre}</td>
                                <td class="px-4 py-3.5">
                                    {#if isProducts}
                                        <div class="flex flex-col items-start gap-1">
                                            {#if row.categoria?.nombre}
                                                <span
                                                    class="inline-flex items-center rounded-md border border-border bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                                                >
                                                    {row.categoria.nombre}
                                                </span>
                                            {/if}
                                            <span class="font-semibold tabular-nums text-foreground">
                                                ${Number(row.precio).toFixed(2)} MXN
                                            </span>
                                        </div>
                                    {:else}
                                        <div class="flex flex-col items-start gap-1">
                                            <span
                                                class="inline-flex items-center rounded-md border border-border bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                                            >
                                                {stationLabels[row.estacion] ?? row.estacion}
                                            </span>
                                            <span class="text-xs text-muted-foreground">
                                                {row.productos_count} {row.productos_count === 1 ? 'producto' : 'productos'}
                                            </span>
                                        </div>
                                    {/if}
                                </td>
                                <td class="px-4 py-3.5">
                                    {#if row.eliminado}
                                        <span
                                            class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border border-destructive/25 bg-destructive/10 px-2.5 py-0.5 text-xs font-semibold text-destructive"
                                        >
                                            <span class="size-1.5 rounded-full bg-destructive" aria-hidden="true"></span>
                                            Eliminado
                                        </span>
                                    {:else if row.disponible}
                                        <span
                                            class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border border-chart-2/30 bg-chart-2/10 px-2.5 py-0.5 text-xs font-semibold text-chart-2"
                                        >
                                            <span class="size-1.5 rounded-full bg-chart-2" aria-hidden="true"></span>
                                            Disponible
                                        </span>
                                    {:else}
                                        <span
                                            class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border border-border bg-muted px-2.5 py-0.5 text-xs font-semibold text-muted-foreground"
                                        >
                                            <span class="size-1.5 rounded-full bg-muted-foreground/70" aria-hidden="true"></span>
                                            No disponible
                                        </span>
                                    {/if}
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    <div class="inline-flex flex-wrap items-center justify-end gap-2">
                                        {#if row.eliminado}
                                            <button
                                                type="button"
                                                disabled={pending}
                                                onclick={() => operate(row, true)}
                                                aria-label={`Restaurar ${row.nombre}`}
                                                title={`Restaurar ${row.nombre}`}
                                                class="inline-flex cursor-pointer items-center gap-1.5 whitespace-nowrap rounded-lg border border-chart-2/30 bg-chart-2/10 px-3 py-1.5 text-xs font-semibold text-chart-2 transition-colors hover:bg-chart-2 hover:text-white focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                            >
                                                <RotateCcw class="size-3.5" aria-hidden="true" />
                                                <span>Restaurar</span>
                                            </button>
                                        {:else}
                                            <Link
                                                href={`${base}/${row.id}/editar`}
                                                aria-label={`Editar ${row.nombre}`}
                                                title={`Editar ${row.nombre}`}
                                                class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg border border-border bg-background px-3 py-1.5 text-xs font-semibold text-foreground shadow-2xs transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring"
                                            >
                                                <Pencil class="size-3.5" aria-hidden="true" />
                                                <span>Editar</span>
                                            </Link>
                                            <button
                                                type="button"
                                                disabled={pending}
                                                onclick={() => requestDelete(row)}
                                                aria-label={`Eliminar ${row.nombre}`}
                                                title={`Eliminar ${row.nombre}`}
                                                class="inline-flex cursor-pointer items-center gap-1.5 whitespace-nowrap rounded-lg border border-destructive/25 bg-destructive/5 px-3 py-1.5 text-xs font-semibold text-destructive transition-colors hover:bg-destructive hover:text-destructive-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                            >
                                                <Trash class="size-3.5" aria-hidden="true" />
                                                <span>Eliminar</span>
                                            </button>
                                        {/if}
                                    </div>
                                </td>
                            </tr>
                        {:else}
                            <tr>
                                <td colspan="4" class="px-4 py-10 text-center">
                                    <div class="mx-auto flex max-w-sm flex-col items-center gap-2 text-muted-foreground">
                                        <span class="inline-flex size-10 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                                            {#if isProducts}
                                                <Package class="size-5" aria-hidden="true" />
                                            {:else}
                                                <Tags class="size-5" aria-hidden="true" />
                                            {/if}
                                        </span>
                                        <p class="text-sm font-medium text-foreground">No hay registros que coincidan.</p>
                                        <p class="text-xs text-muted-foreground">
                                            Ajusta el filtro de búsqueda o cambia el estado seleccionado.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal de confirmación de eliminación -->
    <dialog
        bind:this={confirmDialog}
        aria-labelledby="confirm-delete-title"
        aria-describedby="confirm-delete-desc"
        oncancel={(event) => {
            if (pending) event.preventDefault();
        }}
        onclose={() => {
            if (!pending) target = null;
        }}
        class="fixed inset-0 z-50 m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl border border-border bg-card p-6 text-card-foreground shadow-xl backdrop:bg-foreground/50 backdrop:backdrop-blur-xs"
    >
        {#if target}
            <div class="space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-destructive/10 text-destructive">
                            <Trash class="size-5" aria-hidden="true" />
                        </span>
                        <div>
                            <h2 id="confirm-delete-title" class="text-base font-bold text-foreground">Confirmar eliminación</h2>
                            <p class="text-xs text-muted-foreground">Baja lógica reversible</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onclick={closeDeleteDialog}
                        disabled={pending}
                        aria-label="Cerrar diálogo"
                        class="inline-flex size-8 cursor-pointer items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground disabled:opacity-50"
                    >
                        <X class="size-4" aria-hidden="true" />
                    </button>
                </div>
                <p id="confirm-delete-desc" class="text-sm leading-relaxed text-muted-foreground">
                    ¿Eliminar <strong class="font-semibold text-foreground">«{target.nombre}»</strong>? Podrás restaurarlo más adelante. Los pedidos
                    anteriores se conservan.
                </p>
                <div class="flex flex-wrap items-center justify-end gap-2.5 pt-2">
                    <button
                        bind:this={cancelButton}
                        type="button"
                        disabled={pending}
                        onclick={closeDeleteDialog}
                        class="inline-flex cursor-pointer items-center justify-center rounded-xl border border-border bg-background px-4 py-2 text-sm font-semibold text-foreground transition-colors hover:bg-accent hover:text-accent-foreground disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        disabled={pending}
                        onclick={() => operate(target)}
                        class="inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-xl bg-destructive px-4 py-2 text-sm font-semibold text-destructive-foreground shadow-xs transition-colors hover:bg-destructive/90 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <Trash class="size-4" aria-hidden="true" />
                        <span>Confirmar eliminación</span>
                    </button>
                </div>
            </div>
        {/if}
    </dialog>
</AppLayout>
