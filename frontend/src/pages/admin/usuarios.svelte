<script>
    import { Link, page, router, useForm } from '@inertiajs/svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import InputError from '@/components/input-error.svelte';
    import PageTitle from '@/components/page-title.svelte';
    import Button from '@/components/ui/button.svelte';
    import Input from '@/components/ui/input.svelte';

    let { users, roles, filters } = $props();
    const query = useForm(() => ({ buscar: filters.buscar ?? '', rol: filters.rol ?? '', estado: filters.estado ?? '' }));
    const removal = useForm({});
    let selected = $state(null);

    function filter(event) {
        event.preventDefault();
        router.get('/admin/usuarios', $query.data(), { preserveState: true, replace: true });
    }

    function deactivate() {
        $removal.delete(`/admin/usuarios/${selected.id}`, {
            preserveScroll: true,
            onSuccess: () => (selected = null),
        });
    }
</script>

<PageTitle title="Usuarios — TrackEat" />
<AppLayout headerTitle="Usuarios">
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">Usuarios</h1>
            <Link href="/admin/usuarios/crear" class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground">Crear usuario</Link
            >
        </div>
        {#if $page.props.flash?.success}
            <p role="status" class="rounded-lg border border-chart-2/40 px-4 py-3 text-sm">{$page.props.flash.success}</p>
        {/if}
        <form onsubmit={filter} class="flex flex-wrap items-end gap-3">
            <div class="grid gap-1.5">
                <label for="buscar" class="text-sm font-medium">Nombre o correo</label>
                <Input id="buscar" bind:value={$query.buscar} maxlength={120} />
            </div>
            <div class="grid gap-1.5">
                <label for="filter-role" class="text-sm font-medium">Rol</label>
                <select id="filter-role" class="h-9 rounded-md border border-input bg-background px-3 text-sm" bind:value={$query.rol}>
                    <option value="">Todos</option>
                    {#each roles as role (role)}
                        <option value={role}>{role}</option>
                    {/each}
                </select>
            </div>
            <div class="grid gap-1.5">
                <label for="filter-status" class="text-sm font-medium">Estado</label>
                <select id="filter-status" class="h-9 rounded-md border border-input bg-background px-3 text-sm" bind:value={$query.estado}>
                    <option value="">Todos</option>
                    <option value="activas">Activas</option>
                    <option value="inactivas">Inactivas</option>
                </select>
            </div>
            <Button type="submit" variant="outline">Filtrar</Button>
        </form>
        {#if selected}
            <section aria-labelledby="deactivate-title" class="space-y-3 rounded-xl border border-destructive/40 bg-card p-4">
                <h2 id="deactivate-title" class="font-semibold">¿Desactivar a {selected.name}?</h2>
                <p class="text-sm text-muted-foreground">La cuenta perderá el acceso y se conservará su historial.</p>
                <InputError message={$removal.errors.cuenta} />
                <div class="flex flex-wrap gap-2">
                    <Button variant="destructive" onclick={deactivate} disabled={$removal.processing}>Confirmar desactivación</Button>
                    <Button variant="outline" onclick={() => (selected = null)} disabled={$removal.processing}>Volver</Button>
                </div>
            </section>
        {/if}
        <div class="overflow-x-auto rounded-xl border border-border bg-card">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-border bg-muted/40">
                    <tr>
                        <th scope="col" class="px-4 py-3">Cuenta</th>
                        <th scope="col" class="px-4 py-3">Rol</th>
                        <th scope="col" class="px-4 py-3">Estado</th>
                        <th scope="col" class="px-4 py-3">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    {#each users.data as user (user.id)}
                        <tr>
                            <td class="px-4 py-3"
                                ><span class="font-semibold">{user.name}</span><br /><span class="text-muted-foreground">{user.email}</span></td
                            >
                            <td class="px-4 py-3 capitalize">{user.rol}{user.estacion ? ' · ' + user.estacion : ''}</td>
                            <td class="px-4 py-3">{user.eliminado ? 'Inactiva' : 'Activa'}</td>
                            <td class="space-x-3 whitespace-nowrap px-4 py-3">
                                <Link href={`/admin/usuarios/${user.id}/editar`} aria-label={`Editar a ${user.name}`} class="underline">Editar</Link>
                                {#if !user.eliminado}
                                    <button
                                        type="button"
                                        aria-label={`Desactivar a ${user.name}`}
                                        class="cursor-pointer text-destructive underline"
                                        onclick={() => {
                                            $removal.clearErrors();
                                            selected = user;
                                        }}>Desactivar</button
                                    >
                                {/if}
                            </td>
                        </tr>
                    {:else}
                        <tr><td colspan="4" class="px-4 py-6 text-center text-muted-foreground">No hay cuentas que coincidan.</td></tr>
                    {/each}
                </tbody>
            </table>
        </div>
        {#if users.last_page > 1}
            <nav aria-label="Paginación de usuarios" class="flex items-center gap-4 text-sm">
                {#if users.prev_page_url}<Link href={users.prev_page_url} class="underline">Anterior</Link>{/if}
                <span>Página {users.current_page} de {users.last_page}</span>
                {#if users.next_page_url}<Link href={users.next_page_url} class="underline">Siguiente</Link>{/if}
            </nav>
        {/if}
    </div>
</AppLayout>
