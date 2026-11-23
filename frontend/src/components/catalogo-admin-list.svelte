<script>
    import { page, router, Link } from '@inertiajs/svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    let { rows, kind } = $props();
    let search = $state('');
    let state = $state('activos');
    let pending = $state(false);
    let target = $state(null);
    const title = $derived(kind === 'productos' ? 'Productos' : 'Categorías');
    const base = $derived(`/admin/${kind}`);
    const key = (value) =>
        value
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim();
    const filtered = $derived(
        rows.filter((row) => key(row.nombre).includes(key(search)) && (state === 'todos' || row.eliminado === (state === 'eliminados'))),
    );
    function operate(row, restore = false) {
        pending = true;
        const options = {
            preserveScroll: true,
            onFinish: () => {
                pending = false;
                target = null;
            },
        };
        if (restore) router.post(`${base}/${row.id}/restaurar`, {}, options);
        else router.delete(`${base}/${row.id}`, options);
    }
</script>

<AppLayout headerTitle={`Administrar ${title.toLowerCase()}`}>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">{title}</h1>
            <Link href={`${base}/crear`} class="rounded-xl bg-primary px-4 py-2 font-semibold text-primary-foreground"
                >{kind === 'productos' ? 'Crear producto' : 'Crear categoría'}</Link
            >
        </div>
        <nav aria-label="Administración de catálogo" class="flex gap-4 text-primary">
            <Link href="/admin/productos">Productos</Link><Link href="/admin/categorias">Categorías</Link><a href="/menu/">Ver menú</a>
        </nav>
        {#if $page.props.flash?.success}<p role="status" class="text-primary">{$page.props.flash.success}</p>{/if}
        {#each Object.entries($page.props.errors || {}) as [field, error] (field)}<p role="alert" class="text-destructive">{error}</p>{/each}
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="text-sm font-medium"
                >Buscar por nombre<input
                    type="search"
                    bind:value={search}
                    class="mt-1 w-full rounded-xl border border-input bg-background p-2.5"
                /></label
            >
            <label class="text-sm font-medium"
                >Mostrar<select aria-label="Mostrar" bind:value={state} class="mt-1 w-full rounded-xl border border-input bg-background p-2.5"
                    ><option value="activos">Sin eliminar</option><option value="eliminados">Eliminados</option><option value="todos">Todos</option
                    ></select
                ></label
            >
        </div>
        <div class="overflow-x-auto rounded-2xl border border-border bg-card">
            <table class="w-full text-left text-sm">
                <caption class="p-3 text-left text-muted-foreground"
                    >{filtered.length} registros. Desactivar oculta categorías y marca productos como agotados.</caption
                >
                <thead class="bg-muted"
                    ><tr
                        ><th class="p-3">Nombre</th><th class="p-3">{kind === 'productos' ? 'Categoría y precio' : 'Estación'}</th><th class="p-3"
                            >Estado</th
                        ><th class="p-3">Acciones</th></tr
                    ></thead
                >
                <tbody
                    >{#each filtered as row (row.id)}<tr class="border-t border-border">
                            <td class="p-3 font-semibold break-words">{row.nombre}</td>
                            <td class="p-3"
                                >{#if kind === 'productos'}{row.categoria?.nombre}<br />${Number(row.precio).toFixed(2)} MXN{:else}{row.estacion}<br
                                    />{row.productos_count} productos{/if}</td
                            >
                            <td class="p-3">{row.eliminado ? 'Eliminado' : row.disponible ? 'Disponible' : 'No disponible'}</td>
                            <td class="p-3"
                                ><div class="flex flex-wrap gap-3">
                                    {#if row.eliminado}<button
                                            disabled={pending}
                                            onclick={() => operate(row, true)}
                                            class="font-semibold text-primary disabled:opacity-50">Restaurar {row.nombre}</button
                                        >
                                    {:else}<Link href={`${base}/${row.id}/editar`} class="font-semibold text-primary">Editar {row.nombre}</Link
                                        ><button disabled={pending} onclick={() => (target = row)} class="text-destructive"
                                            >Eliminar {row.nombre}</button
                                        >{/if}
                                </div></td
                            >
                        </tr>{:else}<tr><td colspan="4" class="p-5 text-muted-foreground">No hay registros que coincidan.</td></tr>{/each}</tbody
                >
            </table>
        </div>
        {#if target}<section aria-label="Confirmar eliminación" class="space-y-3 rounded-xl border border-border bg-card p-4">
                <p>¿Eliminar «{target.nombre}»? Podrás restaurarlo. Los pedidos anteriores se conservan.</p>
                <div class="flex gap-4">
                    <button disabled={pending} onclick={() => operate(target)} class="rounded-xl bg-destructive px-4 py-2 text-white"
                        >Confirmar eliminación</button
                    ><button disabled={pending} onclick={() => (target = null)}>Cancelar</button>
                </div>
            </section>{/if}
    </div>
</AppLayout>
