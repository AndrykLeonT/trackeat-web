<script>
    import { untrack } from 'svelte';
    import { useForm, Link } from '@inertiajs/svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    let { categoria = null } = $props();
    const values = () => ({ nombre: categoria?.nombre ?? '', estacion: categoria?.estacion ?? 'general', disponible: categoria?.disponible ?? true });
    const form = useForm(values());
    let loadedId = untrack(() => categoria?.id ?? null);
    $effect(() => {
        const id = categoria?.id ?? null;
        untrack(() => {
            if (id !== loadedId) {
                loadedId = id;
                $form.defaults(values());
                $form.reset();
                $form.clearErrors();
            }
        });
    });
    function submit(event) {
        event.preventDefault();
        $form.post(categoria ? `/admin/categorias/${categoria.id}` : '/admin/categorias');
    }
</script>

<AppLayout headerTitle="Categorías">
    <form onsubmit={submit} class="max-w-2xl space-y-5 rounded-2xl border border-border bg-card p-5">
        <h1 class="text-2xl font-bold">{categoria ? 'Editar categoría' : 'Crear categoría'}</h1>
        {#each Object.entries($form.errors) as [field, error] (field)}<p role="alert" class="text-destructive">{error}</p>{/each}
        <label class="block text-sm font-medium"
            >Nombre<input
                required
                maxlength="255"
                bind:value={$form.nombre}
                class="mt-1 w-full rounded-xl border border-input bg-background p-2.5"
            /></label
        >
        <label class="block text-sm font-medium"
            >Estación<select aria-label="Estación" bind:value={$form.estacion} class="mt-1 w-full rounded-xl border border-input bg-background p-2.5"
                ><option value="general">General</option><option value="cocina">Cocina</option><option value="barra">Barra</option></select
            ></label
        >
        {#if categoria && $form.estacion !== categoria.estacion}<p role="status" class="rounded-xl bg-muted p-3 text-sm">
                La estación cambiará únicamente para pedidos nuevos. Los pedidos existentes conservarán su estación.
            </p>{/if}
        <label class="flex gap-2"><input type="checkbox" bind:checked={$form.disponible} />Disponible en el menú</label>
        <p class="text-sm text-muted-foreground">Desactivar la categoría oculta todos sus productos. No cambia los pedidos existentes.</p>
        <div class="flex items-center gap-4">
            <button disabled={$form.processing} class="rounded-xl bg-primary px-4 py-2 font-semibold text-primary-foreground disabled:opacity-50"
                >{$form.processing ? 'Guardando…' : 'Guardar categoría'}</button
            ><Link href="/admin/categorias">Cancelar</Link>
        </div>
    </form>
</AppLayout>
