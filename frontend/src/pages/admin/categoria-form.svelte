<script>
    import { untrack } from 'svelte';
    import { useForm, Link } from '@inertiajs/svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import PageTitle from '@/components/page-title.svelte';
    import Tags from '@lucide/svelte/icons/tags';
    import ArrowLeft from '@lucide/svelte/icons/arrow-left';
    import CircleAlert from '@lucide/svelte/icons/circle-alert';

    let { categoria = null } = $props();
    const values = () => ({ nombre: categoria?.nombre ?? '', estacion: categoria?.estacion ?? 'general', disponible: categoria?.disponible ?? true });
    const form = useForm(values());
    let loadedId = untrack(() => categoria?.id ?? null);
    const formErrors = $derived(Object.entries($form.errors || {}));

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

<PageTitle title={`${categoria ? 'Editar categoría' : 'Crear categoría'} — TrackEat`} />
<AppLayout headerTitle="Administrar categorías">
    <div class="mx-auto max-w-2xl space-y-6">
        <!-- Navegación superior y encabezado -->
        <div class="space-y-3 border-b border-border pb-5">
            <Link
                href="/admin/categorias"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                <span>Volver a categorías</span>
            </Link>
            <div>
                <h1 class="flex items-center gap-2.5 text-2xl font-bold tracking-tight text-foreground">
                    <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <Tags class="size-5" aria-hidden="true" />
                    </span>
                    <span>{categoria ? 'Editar categoría' : 'Crear categoría'}</span>
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {categoria
                        ? 'Modifica el nombre, estación de preparación o visibilidad de esta categoría.'
                        : 'Crea una nueva categoría y asigna su estación de preparación en cocina o barra.'}
                </p>
            </div>
        </div>

        <form onsubmit={submit} class="space-y-5 rounded-2xl border border-border bg-card p-5 shadow-xs sm:p-6">
            {#if formErrors.length}
                <div class="space-y-2">
                    {#each formErrors as [field, error] (field)}
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

            <div>
                <label for="categoria-nombre" class="block text-sm font-semibold text-foreground">Nombre</label>
                <input
                    id="categoria-nombre"
                    required
                    maxlength="255"
                    bind:value={$form.nombre}
                    placeholder="Ej. Bebidas calientes"
                    class="mt-1.5 h-10 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                />
            </div>

            <div>
                <label for="categoria-estacion" class="block text-sm font-semibold text-foreground">Estación</label>
                <select
                    id="categoria-estacion"
                    aria-label="Estación"
                    bind:value={$form.estacion}
                    class="mt-1.5 h-10 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm text-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                >
                    <option value="general">General</option>
                    <option value="cocina">Cocina</option>
                    <option value="barra">Barra</option>
                </select>
            </div>

            {#if categoria && $form.estacion !== categoria.estacion}
                <div role="status" class="flex items-start gap-3 rounded-xl border border-border bg-muted/60 px-4 py-3 text-sm text-foreground">
                    <CircleAlert class="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                    <p>La estación cambiará únicamente para pedidos nuevos. Los pedidos existentes conservarán su estación.</p>
                </div>
            {/if}

            <div class="rounded-xl border border-border bg-muted/30 p-4">
                <label class="flex cursor-pointer items-start gap-3">
                    <input
                        type="checkbox"
                        bind:checked={$form.disponible}
                        class="mt-0.5 size-4 rounded border-input accent-primary focus-visible:ring-2 focus-visible:ring-ring"
                    />
                    <span class="space-y-1">
                        <span class="block text-sm font-semibold text-foreground">Disponible en el menú</span>
                        <span class="block text-xs leading-relaxed text-muted-foreground">
                            Desactivar la categoría oculta todos sus productos. No cambia los pedidos existentes.
                        </span>
                    </span>
                </label>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-3 border-t border-border pt-5">
                <Link
                    href="/admin/categorias"
                    class="inline-flex items-center justify-center rounded-xl border border-border bg-background px-4 py-2.5 text-sm font-semibold text-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                >
                    Cancelar
                </Link>
                <button
                    type="submit"
                    disabled={$form.processing}
                    class="inline-flex cursor-pointer items-center justify-center rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground shadow-xs transition-colors hover:bg-primary/90 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {$form.processing ? 'Guardando…' : 'Guardar categoría'}
                </button>
            </div>
        </form>
    </div>
</AppLayout>
