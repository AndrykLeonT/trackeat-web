<script>
    import { onDestroy, untrack } from 'svelte';
    import { SvelteSet } from 'svelte/reactivity';
    import { useForm, Link } from '@inertiajs/svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import PageTitle from '@/components/page-title.svelte';
    import Package from '@lucide/svelte/icons/package';
    import ArrowLeft from '@lucide/svelte/icons/arrow-left';
    import ArrowUp from '@lucide/svelte/icons/arrow-up';
    import ArrowDown from '@lucide/svelte/icons/arrow-down';
    import Trash from '@lucide/svelte/icons/trash';
    import ImagePlus from '@lucide/svelte/icons/image-plus';
    import Upload from '@lucide/svelte/icons/upload';
    import CircleAlert from '@lucide/svelte/icons/circle-alert';

    let { producto = null, categorias } = $props();
    const initialGallery = () => (producto?.imagenes ?? []).map((image) => ({ ref: `existente:${image.id}`, id: image.id, url: image.url }));
    let gallery = $state(initialGallery());
    let fileError = $state('');
    let isDragging = $state(false);
    const previews = new SvelteSet();
    const values = () => ({
        nombre: producto?.nombre ?? '',
        descripcion: producto?.descripcion ?? '',
        id_categoria: producto?.id_categoria ?? '',
        precio: producto?.precio ?? '',
        disponible: producto?.disponible ?? true,
        imagenes_existentes: [],
        imagenes: [],
        orden_imagenes: [],
    });
    const form = useForm(values());
    let loadedId = untrack(() => producto?.id ?? null);
    const formErrors = $derived(Object.entries($form.errors || {}));
    const maxReached = $derived(gallery.length >= 5);

    $effect(() => {
        const id = producto?.id ?? null;
        untrack(() => {
            if (id !== loadedId) {
                loadedId = id;
                $form.defaults(values());
                $form.reset();
                $form.clearErrors();
                for (const url of previews) URL.revokeObjectURL(url);
                previews.clear();
                gallery = initialGallery();
            }
        });
    });

    function addFiles(files) {
        if (!files.length) return;
        fileError = '';
        if (gallery.length + files.length > 5) {
            fileError = 'Solo se permiten cinco imágenes por producto.';
            return;
        }
        if (files.some((file) => !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024)) {
            fileError = 'Usa JPG, PNG o WebP de hasta 2 MB.';
            return;
        }
        gallery = [
            ...gallery,
            ...files.map((file) => {
                const url = URL.createObjectURL(file);
                previews.add(url);
                return { file, url, ref: crypto.randomUUID() };
            }),
        ];
    }

    function selectFiles(event) {
        const files = [...event.target.files];
        event.target.value = '';
        addFiles(files);
    }

    function handleDragOver(event) {
        event.preventDefault();
        if (!$form.processing && !maxReached) {
            isDragging = true;
        }
    }

    function handleDragLeave(event) {
        if (!event.currentTarget.contains(event.relatedTarget)) {
            isDragging = false;
        }
    }

    function handleDrop(event) {
        event.preventDefault();
        isDragging = false;
        if ($form.processing || maxReached) return;
        const files = [...(event.dataTransfer?.files ?? [])];
        addFiles(files);
    }

    function move(index, delta) {
        const copy = [...gallery];
        [copy[index], copy[index + delta]] = [copy[index + delta], copy[index]];
        gallery = copy;
    }

    function remove(index) {
        const entry = gallery[index];
        if (previews.has(entry.url)) {
            URL.revokeObjectURL(entry.url);
            previews.delete(entry.url);
        }
        gallery = gallery.filter((_, i) => i !== index);
    }

    function submit(event) {
        event.preventDefault();
        let next = 0;
        $form.imagenes_existentes = gallery.filter((image) => image.id).map((image) => image.id);
        $form.imagenes = gallery.filter((image) => image.file).map((image) => image.file);
        $form.orden_imagenes = gallery.map((image) => (image.id ? `existente:${image.id}` : `nueva:${next++}`));
        $form.post(producto ? `/admin/productos/${producto.id}` : '/admin/productos');
    }

    onDestroy(() => {
        for (const url of previews) URL.revokeObjectURL(url);
    });
</script>

<PageTitle title={`${producto ? 'Editar producto' : 'Crear producto'} — TrackEat`} />
<AppLayout headerTitle="Administrar productos">
    <div class="mx-auto max-w-3xl space-y-6">
        <!-- Navegación superior y encabezado -->
        <div class="space-y-3 border-b border-border pb-5">
            <Link
                href="/admin/productos"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                <span>Volver a productos</span>
            </Link>
            <div>
                <h1 class="flex items-center gap-2.5 text-2xl font-bold tracking-tight text-foreground">
                    <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <Package class="size-5" aria-hidden="true" />
                    </span>
                    <span>{producto ? 'Editar producto' : 'Crear producto'}</span>
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {producto
                        ? 'Actualiza los datos, disponibilidad y fotografías del producto en el menú digital.'
                        : 'Completa la información y agrega fotografías para publicar el producto en el menú.'}
                </p>
            </div>
        </div>

        <form onsubmit={submit} class="space-y-6 rounded-2xl border border-border bg-card p-5 shadow-xs sm:p-6">
            {#if formErrors.length || fileError}
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
                    {#if fileError}
                        <div
                            role="alert"
                            class="flex items-start gap-3 rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive"
                        >
                            <CircleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                            <p>{fileError}</p>
                        </div>
                    {/if}
                </div>
            {/if}

            <!-- Información general -->
            <div class="space-y-4">
                <div>
                    <label for="producto-nombre" class="block text-sm font-semibold text-foreground">Nombre</label>
                    <input
                        id="producto-nombre"
                        required
                        maxlength="255"
                        bind:value={$form.nombre}
                        placeholder="Ej. Café especial de la casa"
                        class="mt-1.5 h-10 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    />
                </div>

                <div>
                    <label for="producto-descripcion" class="block text-sm font-semibold text-foreground">Descripción</label>
                    <textarea
                        id="producto-descripcion"
                        maxlength="5000"
                        rows="4"
                        bind:value={$form.descripcion}
                        placeholder="Describe ingredientes, presentación o notas relevantes para el cliente…"
                        class="mt-1.5 w-full rounded-xl border border-input bg-background px-3 py-2.5 text-sm text-foreground placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    ></textarea>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="producto-categoria" class="block text-sm font-semibold text-foreground">Categoría</label>
                        <select
                            id="producto-categoria"
                            aria-label="Categoría"
                            required
                            bind:value={$form.id_categoria}
                            class="mt-1.5 h-10 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm text-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="" disabled>Selecciona una categoría</option>
                            {#each categorias as category (category.id)}
                                <option value={category.id}>{category.nombre}{category.disponible ? '' : ' (oculta)'}</option>
                            {/each}
                        </select>
                    </div>
                    <div>
                        <label for="producto-precio" class="block text-sm font-semibold text-foreground">Precio final MXN</label>
                        <input
                            id="producto-precio"
                            required
                            type="number"
                            min="0.01"
                            max="9999999999.99"
                            step="0.01"
                            bind:value={$form.precio}
                            placeholder="0.00"
                            class="mt-1.5 h-10 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm tabular-nums text-foreground placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        />
                    </div>
                </div>
            </div>

            <!-- Disponibilidad -->
            <div class="rounded-xl border border-border bg-muted/30 p-4">
                <label class="flex cursor-pointer items-start gap-3">
                    <input
                        type="checkbox"
                        bind:checked={$form.disponible}
                        class="mt-0.5 size-4 rounded border-input accent-primary focus-visible:ring-2 focus-visible:ring-ring"
                    />
                    <span class="space-y-1">
                        <span class="block text-sm font-semibold text-foreground">Disponible para pedir</span>
                        <span class="block text-xs leading-relaxed text-muted-foreground">
                            Un producto no disponible se muestra como agotado. Su categoría debe estar disponible para aparecer en el menú.
                        </span>
                    </span>
                </label>
            </div>

            <!-- Galería de imágenes -->
            <fieldset disabled={$form.processing} class="space-y-4 border-t border-border pt-6">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <legend class="text-base font-bold text-foreground">Galería de imágenes</legend>
                        <p class="mt-0.5 text-xs text-muted-foreground">La primera imagen se muestra como portada del producto en el menú.</p>
                    </div>
                    <span
                        class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold tabular-nums {maxReached
                            ? 'border-chart-2/30 bg-chart-2/10 text-chart-2'
                            : 'border-border bg-muted text-muted-foreground'}"
                    >
                        {gallery.length} / 5 imágenes
                    </span>
                </div>

                <!-- Zona de carga intuitiva (drag & drop + botón explícito) -->
                <div
                    role="presentation"
                    ondragover={handleDragOver}
                    ondragleave={handleDragLeave}
                    ondrop={handleDrop}
                    class="flex flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed px-5 py-7 text-center transition-colors {isDragging
                        ? 'border-primary bg-primary/5'
                        : maxReached
                          ? 'border-border bg-muted/20 opacity-75'
                          : 'border-border bg-muted/30 hover:border-primary/40 hover:bg-muted/50'}"
                >
                    <span class="inline-flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <ImagePlus class="size-5" aria-hidden="true" />
                    </span>

                    <div class="space-y-1">
                        <p class="text-sm font-semibold text-foreground">
                            {maxReached
                                ? 'Alcanzaste el límite de 5 imágenes'
                                : isDragging
                                  ? 'Suelta las imágenes aquí para agregarlas'
                                  : 'Arrastra tus imágenes aquí o selecciónalas desde tu equipo'}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {maxReached
                                ? 'Quita alguna imagen de la galería si deseas subir una nueva.'
                                : 'Los metadatos se eliminan automáticamente al guardar.'}
                        </p>
                    </div>

                    <input
                        id="producto-imagenes"
                        type="file"
                        multiple
                        accept="image/jpeg,image/png,image/webp"
                        onchange={selectFiles}
                        disabled={$form.processing || maxReached}
                        class="sr-only"
                    />
                    <label
                        for="producto-imagenes"
                        class="inline-flex items-center gap-2 rounded-xl border border-border bg-card px-4 py-2 text-sm font-semibold text-foreground shadow-2xs transition-colors {maxReached ||
                        $form.processing
                            ? 'pointer-events-none opacity-50'
                            : 'cursor-pointer hover:bg-accent hover:text-accent-foreground'}"
                    >
                        <Upload class="size-4 text-primary" aria-hidden="true" />
                        <span>Agregar imágenes</span>
                    </label>

                    <div class="flex flex-wrap items-center justify-center gap-1.5 pt-1 text-[11px] font-medium text-muted-foreground">
                        <span class="rounded-md border border-border bg-background px-2 py-0.5">JPG, PNG o WebP</span>
                        <span class="rounded-md border border-border bg-background px-2 py-0.5">Máx. 2 MB c/u</span>
                        <span class="rounded-md border border-border bg-background px-2 py-0.5">Hasta 4096 × 4096 px</span>
                        <span class="rounded-md border border-border bg-background px-2 py-0.5">Máximo 5 fotos</span>
                    </div>
                </div>

                <!-- Vista previa y ordenamiento de imágenes -->
                {#if gallery.length}
                    <ol class="grid gap-4 sm:grid-cols-2">
                        {#each gallery as image, index (image.ref)}
                            <li class="flex flex-col overflow-hidden rounded-xl border border-border bg-background shadow-2xs">
                                <div class="relative flex h-40 items-center justify-center bg-muted/40 p-2">
                                    <img src={image.url} alt={`Imagen ${index + 1} del producto`} class="h-full w-full rounded-lg object-contain" />
                                    <span
                                        class="absolute top-2.5 left-2.5 inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold shadow-2xs {index ===
                                        0
                                            ? 'bg-primary text-primary-foreground'
                                            : 'border border-border bg-card/95 text-foreground'}"
                                    >
                                        {index === 0 ? 'Portada' : `Imagen ${index + 1}`}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between gap-2 border-t border-border bg-card px-3 py-2.5">
                                    <div class="flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            disabled={index === 0}
                                            onclick={() => move(index, -1)}
                                            aria-label={`Subir imagen ${index + 1}`}
                                            title="Mover antes"
                                            class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-border bg-background px-2.5 py-1.5 text-xs font-semibold text-foreground transition-colors hover:bg-accent disabled:cursor-not-allowed disabled:opacity-40"
                                        >
                                            <ArrowUp class="size-3.5" aria-hidden="true" />
                                            <span>Subir</span>
                                        </button>
                                        <button
                                            type="button"
                                            disabled={index === gallery.length - 1}
                                            onclick={() => move(index, 1)}
                                            aria-label={`Bajar imagen ${index + 1}`}
                                            title="Mover después"
                                            class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-border bg-background px-2.5 py-1.5 text-xs font-semibold text-foreground transition-colors hover:bg-accent disabled:cursor-not-allowed disabled:opacity-40"
                                        >
                                            <ArrowDown class="size-3.5" aria-hidden="true" />
                                            <span>Bajar</span>
                                        </button>
                                    </div>
                                    <button
                                        type="button"
                                        onclick={() => remove(index)}
                                        aria-label={`Quitar imagen ${index + 1}`}
                                        title="Quitar imagen"
                                        class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-destructive/25 bg-destructive/5 px-2.5 py-1.5 text-xs font-semibold text-destructive transition-colors hover:bg-destructive hover:text-destructive-foreground"
                                    >
                                        <Trash class="size-3.5" aria-hidden="true" />
                                        <span>Quitar</span>
                                    </button>
                                </div>
                            </li>
                        {/each}
                    </ol>
                {/if}
            </fieldset>

            {#if !categorias.length}
                <div
                    role="alert"
                    class="flex items-start gap-3 rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive"
                >
                    <CircleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <p>Crea o restaura una categoría antes de agregar productos.</p>
                </div>
            {/if}

            <!-- Acciones del formulario -->
            <div class="flex flex-wrap items-center justify-end gap-3 border-t border-border pt-5">
                <Link
                    href="/admin/productos"
                    class="inline-flex items-center justify-center rounded-xl border border-border bg-background px-4 py-2.5 text-sm font-semibold text-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                >
                    Cancelar
                </Link>
                <button
                    type="submit"
                    disabled={$form.processing || !categorias.length}
                    class="inline-flex cursor-pointer items-center justify-center rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground shadow-xs transition-colors hover:bg-primary/90 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {$form.processing ? 'Guardando…' : 'Guardar producto'}
                </button>
            </div>
        </form>
    </div>
</AppLayout>
