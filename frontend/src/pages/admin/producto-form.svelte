<script>
    import { onDestroy, untrack } from 'svelte';
    import { SvelteSet } from 'svelte/reactivity';
    import { useForm, Link } from '@inertiajs/svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    let { producto = null, categorias } = $props();
    const initialGallery = () => (producto?.imagenes ?? []).map((image) => ({ ref: `existente:${image.id}`, id: image.id, url: image.url }));
    let gallery = $state(initialGallery());
    let fileError = $state('');
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
    function selectFiles(event) {
        const files = [...event.target.files];
        event.target.value = '';
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

<AppLayout headerTitle="Productos">
    <form onsubmit={submit} class="max-w-3xl space-y-5 rounded-2xl border border-border bg-card p-5">
        <h1 class="text-2xl font-bold">{producto ? 'Editar producto' : 'Crear producto'}</h1>
        {#each Object.entries($form.errors) as [field, error] (field)}<p role="alert" class="text-destructive">{error}</p>{/each}
        {#if fileError}<p role="alert" class="text-destructive">{fileError}</p>{/if}
        <label class="block text-sm font-medium"
            >Nombre<input
                required
                maxlength="255"
                bind:value={$form.nombre}
                class="mt-1 w-full rounded-xl border border-input bg-background p-2.5"
            /></label
        >
        <label class="block text-sm font-medium"
            >Descripción<textarea
                maxlength="5000"
                rows="4"
                bind:value={$form.descripcion}
                class="mt-1 w-full rounded-xl border border-input bg-background p-2.5"></textarea></label
        >
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm font-medium"
                >Categoría<select
                    aria-label="Categoría"
                    required
                    bind:value={$form.id_categoria}
                    class="mt-1 w-full rounded-xl border border-input bg-background p-2.5"
                    ><option value="" disabled>Selecciona una categoría</option>{#each categorias as category (category.id)}<option
                            value={category.id}>{category.nombre}{category.disponible ? '' : ' (oculta)'}</option
                        >{/each}</select
                ></label
            >
            <label class="block text-sm font-medium"
                >Precio final MXN<input
                    required
                    type="number"
                    min="0.01"
                    max="9999999999.99"
                    step="0.01"
                    bind:value={$form.precio}
                    class="mt-1 w-full rounded-xl border border-input bg-background p-2.5"
                /></label
            >
        </div>
        <label class="flex gap-2"><input type="checkbox" bind:checked={$form.disponible} />Disponible para pedir</label>
        <p class="text-sm text-muted-foreground">
            Un producto no disponible se muestra como agotado. Su categoría debe estar disponible para aparecer en el menú.
        </p>
        <fieldset disabled={$form.processing} class="space-y-3">
            <legend class="font-semibold">Galería de imágenes</legend>
            <p class="text-sm text-muted-foreground">
                Máximo cinco JPG, PNG o WebP de 2 MB y 4096 × 4096 píxeles. La primera imagen es la portada. Se eliminan los metadatos al guardar.
            </p>
            <label class="block text-sm"
                >Agregar imágenes<input
                    type="file"
                    multiple
                    accept="image/jpeg,image/png,image/webp"
                    onchange={selectFiles}
                    class="mt-2 block w-full text-sm"
                /></label
            >
            <ol class="grid gap-3 sm:grid-cols-2">
                {#each gallery as image, index (image.ref)}<li class="space-y-2 rounded-xl border border-border p-3">
                        <img src={image.url} alt={`Imagen ${index + 1} del producto`} class="h-36 w-full object-contain" />
                        <p class="text-sm">{index === 0 ? 'Portada' : `Imagen ${index + 1}`}</p>
                        <div class="flex flex-wrap gap-3 text-sm">
                            <button
                                type="button"
                                disabled={index === 0}
                                onclick={() => move(index, -1)}
                                class="text-primary disabled:opacity-40"
                                aria-label={`Subir imagen ${index + 1}`}>Subir</button
                            ><button
                                type="button"
                                disabled={index === gallery.length - 1}
                                onclick={() => move(index, 1)}
                                class="text-primary disabled:opacity-40"
                                aria-label={`Bajar imagen ${index + 1}`}>Bajar</button
                            ><button type="button" onclick={() => remove(index)} class="text-destructive" aria-label={`Quitar imagen ${index + 1}`}
                                >Quitar</button
                            >
                        </div>
                    </li>{/each}
            </ol>
        </fieldset>
        <div class="flex items-center gap-4">
            <button
                disabled={$form.processing || !categorias.length}
                class="rounded-xl bg-primary px-4 py-2 font-semibold text-primary-foreground disabled:opacity-50"
                >{$form.processing ? 'Guardando…' : 'Guardar producto'}</button
            ><Link href="/admin/productos">Cancelar</Link>
        </div>
        {#if !categorias.length}<p role="alert">Crea o restaura una categoría antes de agregar productos.</p>{/if}
    </form>
</AppLayout>
