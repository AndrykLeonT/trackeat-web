<script>
    import { onMount, tick } from 'svelte';
    import AppLayout from '../src/layouts/AppLayout.svelte';
    import Users from '@lucide/svelte/icons/users';
    import UserRound from '@lucide/svelte/icons/user-round';
    import Plus from '@lucide/svelte/icons/plus';
    import { AREAS, TURNOS, PHOTO_LIMIT, listPeople, getPerson, savePerson, deletePerson, today } from './db.js';
    import { prepareOffline } from './offline.js';

    const emptyForm = () => ({ nombre: '', email: '', telefono: '', area: '', puesto: '', fechaIngreso: today(), turno: 'Matutino', foto: null });
    let form = $state(emptyForm());
    let records = $state([]);
    let visible = $state([]);
    let photoUrls = $state({});
    let preview = $state('');
    let loading = $state(true);
    let busy = $state(false);
    let message = $state('');
    let error = $state('');
    let photoError = $state('');
    let validatingPhoto = $state(false);
    let photoSelection = 0;
    let searchName = $state('');
    let searchArea = $state('');
    let online = $state(true);
    let offlineError = $state('');
    let detailRecord = $state(null);
    let deletingRecord = $state(null);
    let detailDialog;
    let deleteDialog;
    let nameInput;
    let fileInput;
    let cancelDelete;
    let searchInput;
    let channel;
    let refreshSequence = 0;
    let alive = true;

    const areaCounts = $derived(AREAS.map((area) => ({ area, total: records.filter((person) => person.area === area).length })));
    const maxCount = $derived(Math.max(1, ...areaCounts.map((entry) => entry.total)));
    const formatDate = (value) => new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium' }).format(new Date(`${value}T12:00:00`));

    function readableError(caught) {
        if (caught?.name === 'QuotaExceededError')
            return 'No hay espacio suficiente en el navegador. Libera espacio o usa una fotografía más pequeña.';
        return caught?.message || 'No se pudo completar la operación. Vuelve a intentar.';
    }

    async function refresh() {
        const sequence = ++refreshSequence;
        const [all, filtered] = await Promise.all([listPeople(), listPeople({ nombre: searchName, area: searchArea })]);
        if (!alive || sequence !== refreshSequence) return;
        const urls = Object.fromEntries(all.map((person) => [person.id, URL.createObjectURL(person.foto)]));
        Object.values(photoUrls).forEach((url) => URL.revokeObjectURL(url));
        photoUrls = urls;
        records = all;
        visible = filtered;
        loading = false;
    }

    async function reloadCatalog() {
        try {
            await refresh();
        } catch (caught) {
            error = readableError(caught);
            loading = false;
        }
    }

    onMount(() => {
        online = navigator.onLine;
        const connectionChanged = () => {
            online = navigator.onLine;
        };
        window.addEventListener('online', connectionChanged);
        window.addEventListener('offline', connectionChanged);
        window.addEventListener('focus', reloadCatalog);
        if ('BroadcastChannel' in window) {
            channel = new BroadcastChannel('trackeat-personal-cambios');
            channel.onmessage = reloadCatalog;
        }
        reloadCatalog();
        prepareOffline().catch((caught) => {
            if (alive) offlineError = readableError(caught);
        });
        return () => {
            alive = false;
            refreshSequence += 1;
            window.removeEventListener('online', connectionChanged);
            window.removeEventListener('offline', connectionChanged);
            window.removeEventListener('focus', reloadCatalog);
            channel?.close();
            Object.values(photoUrls).forEach((url) => URL.revokeObjectURL(url));
            if (preview) URL.revokeObjectURL(preview);
        };
    });

    function resetForm() {
        photoSelection += 1;
        validatingPhoto = false;
        if (preview) URL.revokeObjectURL(preview);
        preview = '';
        form = emptyForm();
        photoError = '';
        if (fileInput) fileInput.value = '';
    }

    async function startNew() {
        resetForm();
        await tick();
        nameInput.focus();
        nameInput.scrollIntoView({ block: 'center', behavior: 'instant' });
    }

    async function choosePhoto(event) {
        const file = event.currentTarget.files[0];
        const selection = ++photoSelection;
        photoError = '';
        if (!file) return;
        validatingPhoto = true;
        try {
            if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type) || !file.size || file.size > PHOTO_LIMIT) {
                throw new Error('La fotografía debe ser PNG, JPEG o WebP y pesar hasta 5 MiB.');
            }
            // Copia los bytes: un File puede conservar un respaldo en el archivo de origen.
            const photo = new Blob([await file.arrayBuffer()], { type: file.type });
            const bitmap = await createImageBitmap(photo);
            bitmap.close();
            if (selection !== photoSelection) return;
            if (preview) URL.revokeObjectURL(preview);
            preview = URL.createObjectURL(photo);
            form.foto = photo;
        } catch (caught) {
            if (selection !== photoSelection) return;
            photoError = caught instanceof DOMException ? 'El archivo no contiene una imagen válida.' : readableError(caught);
            fileInput.value = '';
        } finally {
            if (selection === photoSelection) validatingPhoto = false;
        }
    }

    async function save(event) {
        event.preventDefault();
        if (busy || validatingPhoto) return;
        error = '';
        message = '';
        if (!form.foto) {
            photoError = 'Selecciona una fotografía para este registro.';
            fileInput.focus();
            return;
        }
        if (photoError) {
            fileInput.focus();
            return;
        }
        busy = true;
        const editing = Boolean(form.id);
        try {
            await savePerson($state.snapshot(form));
            resetForm();
            channel?.postMessage('changed');
            await refresh();
            message = editing ? 'Los cambios del integrante se guardaron.' : 'El integrante se agregó al catálogo.';
        } catch (caught) {
            error = readableError(caught);
        } finally {
            busy = false;
        }
    }

    async function search(event) {
        event?.preventDefault();
        error = '';
        try {
            await refresh();
            message = `Búsqueda completada: ${visible.length} resultado${visible.length === 1 ? '' : 's'}.`;
        } catch (caught) {
            error = readableError(caught);
        }
    }

    async function clearSearch() {
        searchName = '';
        searchArea = '';
        await search();
    }

    async function edit(person) {
        try {
            const current = await getPerson(person.id);
            if (!current) throw new Error('Este registro ya no existe. Actualiza el catálogo.');
            resetForm();
            form = current;
            preview = URL.createObjectURL(current.foto);
            message = '';
            error = '';
            await tick();
            nameInput.focus();
            nameInput.scrollIntoView({ block: 'center', behavior: 'instant' });
        } catch (caught) {
            error = readableError(caught);
        }
    }

    async function view(person) {
        try {
            detailRecord = await getPerson(person.id);
            if (!detailRecord) throw new Error('Este registro ya no existe. Actualiza el catálogo.');
            await tick();
            detailDialog.showModal();
        } catch (caught) {
            error = readableError(caught);
        }
    }

    async function requestDelete(person) {
        deletingRecord = person;
        await tick();
        deleteDialog.showModal();
        cancelDelete.focus();
    }

    async function confirmDelete() {
        if (busy || !deletingRecord) return;
        busy = true;
        const person = deletingRecord;
        error = '';
        message = '';
        try {
            await deletePerson(person.id);
            if (form.id === person.id) resetForm();
            deleteDialog.close();
            deletingRecord = null;
            channel?.postMessage('changed');
            await refresh();
            message = `${person.nombre} se eliminó del catálogo.`;
            searchInput.focus();
        } catch (caught) {
            deleteDialog.close();
            error = readableError(caught);
        } finally {
            busy = false;
        }
    }
</script>

<a class="skip-link" href="#contenido">Saltar al contenido</a>
<AppLayout
    nativeNavigation
    currentUrl="/personal/"
    headerTitle="Personal"
    connected={online}
    connectionLabel={online ? 'Con conexión' : 'Sin conexión'}
    contentId="contenido"
>
    <div class="personal-catalog">
        <div class="page-heading">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        ><Users class="size-4" aria-hidden="true" /></span
                    >
                    <h1>Catálogo de personal</h1>
                </div>
                <p>Las personas que hacen que todo funcione.</p>
            </div>
            <button class="primary inline-flex items-center gap-1.5" onclick={startNew} disabled={busy || loading}
                ><Plus class="size-4" aria-hidden="true" /> Nuevo integrante</button
            >
        </div>

        {#if offlineError}<div class="error-message" role="alert">{offlineError}</div>{/if}
        <p class="success-message" role="status" aria-live="polite">{message}</p>
        {#if error}<div class="error-message" role="alert">
                {error} <button class="text-button" onclick={reloadCatalog}>Volver a cargar</button>
            </div>{/if}

        <div class="catalog-grid">
            <section class="panel form-panel" aria-labelledby="form-title">
                <div class="panel-heading">
                    <div>
                        <h2 id="form-title">{form.id ? 'Editar integrante' : 'Nuevo integrante'}</h2>
                        <p>Todos los campos son obligatorios.</p>
                    </div>
                    <span class="step-tag">{form.id ? `#${form.id}` : 'ALTA'}</span>
                </div>
                <form onsubmit={save}>
                    <fieldset disabled={busy || loading}>
                        <legend class="sr-only">Datos del integrante</legend>
                        <div class="photo-field">
                            <div class="photo-preview">
                                {#if preview}<img src={preview} alt="Vista previa de la fotografía seleccionada" />{:else}<UserRound
                                        class="size-8"
                                        aria-hidden="true"
                                    />{/if}
                            </div>
                            <div>
                                <label for="foto">Fotografía</label><input
                                    bind:this={fileInput}
                                    id="foto"
                                    name="foto"
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp"
                                    required={!form.foto}
                                    onchange={choosePhoto}
                                    aria-describedby="photo-help photo-error"
                                    aria-invalid={Boolean(photoError)}
                                />
                                <p id="photo-help" class="field-help">PNG, JPEG o WebP · hasta 5 MiB</p>
                            </div>
                        </div>
                        <p id="photo-error" class="field-error" role="alert">{photoError}</p>
                        <label for="nombre">Nombre completo</label><input
                            bind:this={nameInput}
                            bind:value={form.nombre}
                            id="nombre"
                            name="nombre"
                            autocomplete="name"
                            minlength="2"
                            maxlength="100"
                            required
                            placeholder="Ej. Angélica Menchaca"
                        />
                        <label for="email">Correo electrónico</label><input
                            bind:value={form.email}
                            id="email"
                            name="email"
                            type="email"
                            autocomplete="email"
                            maxlength="150"
                            required
                            placeholder="nombre@ejemplo.com"
                        />
                        <label for="telefono">Teléfono</label><input
                            bind:value={form.telefono}
                            id="telefono"
                            name="telefono"
                            type="tel"
                            autocomplete="tel"
                            minlength="7"
                            maxlength="25"
                            required
                            placeholder="612 123 4567"
                            title="De 7 a 25 caracteres: números, espacios, +, paréntesis, punto o guion."
                        />
                        <div class="two-fields">
                            <div>
                                <label for="area">Área</label><select bind:value={form.area} id="area" name="area" required
                                    ><option value="" disabled>Selecciona un área</option>{#each AREAS as area (area)}<option value={area}
                                            >{area}</option
                                        >{/each}</select
                                >
                            </div>
                            <div>
                                <label for="turno">Turno</label><select bind:value={form.turno} id="turno" name="turno" required
                                    >{#each TURNOS as turno (turno)}<option value={turno}>{turno}</option>{/each}</select
                                >
                            </div>
                        </div>
                        <label for="puesto">Puesto</label><input
                            bind:value={form.puesto}
                            id="puesto"
                            name="puesto"
                            maxlength="70"
                            required
                            placeholder="Ej. Chef de cocina"
                        />
                        <label for="fechaIngreso">Fecha de ingreso</label><input
                            bind:value={form.fechaIngreso}
                            id="fechaIngreso"
                            name="fechaIngreso"
                            type="date"
                            max={today()}
                            required
                        />
                        <div class="form-actions">
                            <button type="submit" class="primary" disabled={validatingPhoto}
                                >{validatingPhoto
                                    ? 'Validando fotografía…'
                                    : busy
                                      ? 'Guardando…'
                                      : form.id
                                        ? 'Guardar cambios'
                                        : 'Agregar integrante'}</button
                            ><button type="button" class="secondary" onclick={resetForm}>{form.id ? 'Cancelar edición' : 'Limpiar'}</button>
                        </div>
                    </fieldset>
                </form>
            </section>

            <div class="right-column">
                <section class="panel list-panel" aria-labelledby="list-title" aria-busy={loading}>
                    <div class="panel-heading">
                        <div>
                            <h2 id="list-title">Nuestro equipo</h2>
                            <p>{visible.length} de {records.length} integrante{records.length === 1 ? '' : 's'}</p>
                        </div>
                        <span class="count-badge">{records.length}</span>
                    </div>
                    <form class="search-form" onsubmit={search} aria-label="Buscar personal">
                        <div class="search-name">
                            <label for="buscar">Nombre (empieza con)</label><input
                                bind:this={searchInput}
                                bind:value={searchName}
                                id="buscar"
                                name="buscar"
                                type="search"
                                placeholder="Buscar por nombre…"
                                maxlength="100"
                            />
                        </div>
                        <div>
                            <label for="buscar-area">Área del equipo</label><select bind:value={searchArea} id="buscar-area" name="buscar-area"
                                ><option value="">Todas las áreas</option>{#each AREAS as area (area)}<option value={area}>{area}</option
                                    >{/each}</select
                            >
                        </div>
                        <button class="secondary" type="submit" disabled={loading}>Buscar</button>
                    </form>
                    {#if searchName || searchArea}<button class="text-button clear-search" onclick={clearSearch}>Mostrar todo el equipo</button>{/if}
                    {#if loading}<p class="empty-state" role="status">Abriendo el catálogo…</p>{:else if visible.length === 0}<div
                            class="empty-state"
                        >
                            <span aria-hidden="true"><Users class="size-6" /></span>
                            <h3>{records.length ? 'No encontramos coincidencias' : 'Tu equipo empieza aquí'}</h3>
                            <p>
                                {records.length
                                    ? 'Prueba con las primeras letras del nombre o con otra área.'
                                    : 'Agrega al primer integrante con su fotografía. Su información estará disponible sin conexión.'}
                            </p>
                        </div>{:else}
                        <ul class="people-list">
                            {#each visible as person (person.id)}<li>
                                    <article class="person-row" aria-labelledby={`person-${person.id}`}>
                                        <img class="avatar" src={photoUrls[person.id]} alt={`Fotografía de ${person.nombre}`} />
                                        <div class="person-info">
                                            <h3 id={`person-${person.id}`}>{person.nombre}</h3>
                                            <p>{person.puesto} <span aria-hidden="true">·</span> {person.turno}</p>
                                            <span class="area-pill">{person.area}</span>
                                        </div>
                                        <div class="row-actions">
                                            <button class="text-button" aria-label={`Consultar ${person.nombre}`} onclick={() => view(person)}
                                                >Ver</button
                                            ><button
                                                class="text-button"
                                                aria-label={`Editar ${person.nombre}`}
                                                onclick={() => edit(person)}
                                                disabled={busy}>Editar</button
                                            ><button
                                                class="text-button danger-text"
                                                aria-label={`Eliminar ${person.nombre}`}
                                                onclick={() => requestDelete(person)}
                                                disabled={busy}>Eliminar</button
                                            >
                                        </div>
                                    </article>
                                </li>{/each}
                        </ul>
                    {/if}
                </section>

                <section class="panel chart-panel" aria-labelledby="chart-title">
                    <div class="panel-heading">
                        <div>
                            <p class="eyebrow">UNA MIRADA AL EQUIPO</p>
                            <h2 id="chart-title">Personal por área</h2>
                            <p>¿Cómo se distribuye el personal por área?</p>
                        </div>
                    </div>
                    <figure>
                        <svg class="area-chart" viewBox="0 0 440 220" role="img" aria-labelledby="chart-description"
                            ><title id="chart-description"
                                >Distribución del personal: {areaCounts.map((entry) => `${entry.area}, ${entry.total}`).join('; ')}. Total: {records.length}.</title
                            >{#each areaCounts as entry, index (entry.area)}<text x="0" y={33 + index * 50} class="chart-label">{entry.area}</text
                                ><rect x="145" y={16 + index * 50} width="240" height="24" rx="6" class="chart-track" /><rect
                                    x="145"
                                    y={16 + index * 50}
                                    width={(entry.total / maxCount) * 240}
                                    height="24"
                                    rx="6"
                                    class={`chart-bar bar-${index}`}
                                /><text x="405" y={33 + index * 50} class="chart-value">{entry.total}</text>{/each}</svg
                        >
                        <figcaption>
                            {records.length
                                ? `Datos de los ${records.length} registros del catálogo completo, sin aplicar filtros.`
                                : 'Agrega un integrante para empezar a ver la distribución.'}
                        </figcaption>
                    </figure>
                    <table class="sr-only">
                        <caption>Personal por área, valores del gráfico</caption><thead
                            ><tr><th scope="col">Área</th><th scope="col">Integrantes</th></tr></thead
                        ><tbody
                            >{#each areaCounts as entry (entry.area)}<tr><th scope="row">{entry.area}</th><td>{entry.total}</td></tr>{/each}</tbody
                        >
                    </table>
                </section>
            </div>
        </div>
    </div>
</AppLayout>

<dialog
    class="personal-dialog"
    bind:this={detailDialog}
    aria-labelledby="detail-title"
    onclose={() => {
        detailRecord = null;
    }}
>
    {#if detailRecord}<div class="dialog-content">
            <p class="eyebrow">FICHA DEL INTEGRANTE</p>
            <h2 id="detail-title">{detailRecord.nombre}</h2>
            <img class="detail-photo" src={photoUrls[detailRecord.id]} alt={`Fotografía de ${detailRecord.nombre}`} />
            <dl class="person-details">
                <div>
                    <dt>Correo</dt>
                    <dd>{detailRecord.email}</dd>
                </div>
                <div>
                    <dt>Teléfono</dt>
                    <dd>{detailRecord.telefono}</dd>
                </div>
                <div>
                    <dt>Área</dt>
                    <dd>{detailRecord.area}</dd>
                </div>
                <div>
                    <dt>Puesto</dt>
                    <dd>{detailRecord.puesto}</dd>
                </div>
                <div>
                    <dt>Turno</dt>
                    <dd>{detailRecord.turno}</dd>
                </div>
                <div>
                    <dt>Fecha de ingreso</dt>
                    <dd><time datetime={detailRecord.fechaIngreso}>{formatDate(detailRecord.fechaIngreso)}</time></dd>
                </div>
            </dl>
            <button class="primary" onclick={() => detailDialog.close()}>Cerrar ficha</button>
        </div>{/if}
</dialog>
<dialog
    class="personal-dialog"
    bind:this={deleteDialog}
    aria-labelledby="delete-title"
    aria-describedby="delete-description"
    oncancel={(event) => {
        if (busy) event.preventDefault();
    }}
    onclose={() => {
        deletingRecord = null;
    }}
>
    <div class="dialog-content">
        <p class="eyebrow">CONFIRMAR BAJA</p>
        <h2 id="delete-title">¿Eliminar a este integrante?</h2>
        <p id="delete-description">
            Se eliminará el registro de <strong>{deletingRecord?.nombre}</strong> y su fotografía del catálogo local. Esta acción no se puede deshacer.
        </p>
        <div class="form-actions">
            <button bind:this={cancelDelete} class="secondary" onclick={() => deleteDialog.close()} disabled={busy}>Cancelar</button><button
                class="danger"
                onclick={confirmDelete}
                disabled={busy}>{busy ? 'Eliminando…' : 'Sí, eliminar'}</button
            >
        </div>
    </div>
</dialog>
