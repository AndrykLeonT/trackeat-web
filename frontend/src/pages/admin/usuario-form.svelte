<script>
    import { Link, useForm } from '@inertiajs/svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import CamposCuenta from '@/components/campos-cuenta.svelte';
    import InputError from '@/components/input-error.svelte';
    import PageTitle from '@/components/page-title.svelte';
    import Button from '@/components/ui/button.svelte';
    import Input from '@/components/ui/input.svelte';
    import Label from '@/components/ui/label.svelte';

    let { account = null, roles, stations } = $props();
    const title = $derived(account ? 'Editar usuario' : 'Crear usuario');
    const form = useForm(() => ({
        primer_nombre: account?.primer_nombre ?? account?.name ?? '',
        segundo_nombre: account?.segundo_nombre ?? '',
        primer_apellido: account?.primer_apellido ?? '',
        segundo_apellido: account?.segundo_apellido ?? '',
        email: account?.email ?? '',
        telefono: account?.telefono ?? '',
        rol: account?.rol ?? 'cliente',
        estacion: account?.estacion ?? 'general',
        password: '',
        password_confirmation: '',
    }));

    function save(event) {
        event.preventDefault();
        if (account) {
            $form.patch(`/admin/usuarios/${account.id}`);
        } else {
            $form.post('/admin/usuarios', {
                onFinish: () => $form.reset('password', 'password_confirmation'),
            });
        }
    }
</script>

<PageTitle title="{title} — TrackEat" />
<AppLayout headerTitle={title}>
    <div class="mx-auto max-w-3xl space-y-5">
        <Link href="/admin/usuarios" class="text-sm text-muted-foreground underline">Volver a usuarios</Link>
        <h1 class="text-2xl font-bold">{title}</h1>
        {#if account?.eliminado}
            <p class="text-sm text-muted-foreground">Cuenta inactiva</p>
        {/if}
        <form onsubmit={save} class="space-y-5 rounded-2xl border border-border bg-card p-5">
            <CamposCuenta {form} />
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="rol">Rol</Label>
                    <select id="rol" class="h-9 rounded-md border border-input bg-background px-3 text-sm" bind:value={$form.rol}>
                        {#each roles as role (role)}
                            <option value={role}>{role}</option>
                        {/each}
                    </select>
                    <InputError message={$form.errors.rol} />
                </div>
                {#if $form.rol === 'cocinero'}
                    <div class="grid gap-2">
                        <Label for="estacion">Estación</Label>
                        <select id="estacion" class="h-9 rounded-md border border-input bg-background px-3 text-sm" bind:value={$form.estacion}>
                            {#each stations as station (station)}
                                <option value={station}>{station}</option>
                            {/each}
                        </select>
                        <InputError message={$form.errors.estacion} />
                    </div>
                {/if}
            </div>
            {#if !account}
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="password">Contraseña inicial</Label>
                        <Input id="password" type="password" autocomplete="new-password" minlength={8} required bind:value={$form.password} />
                        <InputError message={$form.errors.password} />
                    </div>
                    <div class="grid gap-2">
                        <Label for="password-confirmation">Repite la contraseña</Label>
                        <Input
                            id="password-confirmation"
                            type="password"
                            autocomplete="new-password"
                            minlength={8}
                            required
                            bind:value={$form.password_confirmation}
                        />
                    </div>
                </div>
            {/if}
            <Button type="submit" disabled={$form.processing}>Guardar usuario</Button>
        </form>
    </div>
</AppLayout>
