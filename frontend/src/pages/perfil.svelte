<script>
    import { page, useForm } from '@inertiajs/svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import CamposCuenta from '@/components/campos-cuenta.svelte';
    import InputError from '@/components/input-error.svelte';
    import PageTitle from '@/components/page-title.svelte';
    import Button from '@/components/ui/button.svelte';
    import Input from '@/components/ui/input.svelte';
    import Label from '@/components/ui/label.svelte';

    let { profile, name, demo } = $props();
    const form = useForm(() => ({
        ...profile,
        primer_nombre: profile.primer_nombre ?? name,
        current_password: '',
    }));
    const password = useForm({ current_password: '', password: '', password_confirmation: '' });
    const emailChanged = $derived($form.email.trim().toLowerCase() !== profile.email);

    function saveProfile(event) {
        event.preventDefault();
        $form.patch('/perfil', {
            preserveScroll: true,
            errorBag: 'profile',
            onSuccess: () => ($form.email = $form.email.trim().toLowerCase()),
            onFinish: () => $form.reset('current_password'),
        });
    }

    function changePassword(event) {
        event.preventDefault();
        $password.put('/perfil/contrasena', {
            preserveScroll: true,
            errorBag: 'password',
            onFinish: () => $password.reset(),
        });
    }
</script>

<PageTitle title="Mi perfil — TrackEat" />
<AppLayout headerTitle="Mi perfil">
    <div class="mx-auto max-w-3xl space-y-6">
        <h1 class="text-2xl font-bold">Mi perfil</h1>

        {#if $page.props.flash?.success}
            <p role="status" class="rounded-lg border border-chart-2/40 px-4 py-3 text-sm">{$page.props.flash.success}</p>
        {/if}

        {#if demo}
            <p>Las cuentas temporales de demostración no se pueden modificar.</p>
        {:else}
            <form onsubmit={saveProfile} class="space-y-5 rounded-2xl border border-border bg-card p-5">
                <h2 class="text-lg font-semibold">Datos de contacto</h2>
                <CamposCuenta {form} />
                {#if emailChanged}
                    <div class="grid gap-2">
                        <Label for="profile-current-password">Confirma tu contraseña para cambiar el correo</Label>
                        <Input
                            id="profile-current-password"
                            type="password"
                            autocomplete="current-password"
                            bind:value={$form.current_password}
                            required
                        />
                        <InputError message={$form.errors.current_password} />
                    </div>
                {/if}
                <Button type="submit" disabled={$form.processing}>Guardar perfil</Button>
            </form>

            <form onsubmit={changePassword} class="space-y-5 rounded-2xl border border-border bg-card p-5">
                <h2 class="text-lg font-semibold">Cambiar contraseña</h2>
                <div class="grid gap-2">
                    <Label for="current-password">Contraseña actual</Label>
                    <Input id="current-password" type="password" autocomplete="current-password" bind:value={$password.current_password} required />
                    <InputError message={$password.errors.current_password} />
                </div>
                <div class="grid gap-2">
                    <Label for="new-password">Nueva contraseña</Label>
                    <Input id="new-password" type="password" autocomplete="new-password" bind:value={$password.password} required minlength={8} />
                    <InputError message={$password.errors.password} />
                </div>
                <div class="grid gap-2">
                    <Label for="confirm-password">Repite la nueva contraseña</Label>
                    <Input id="confirm-password" type="password" autocomplete="new-password" bind:value={$password.password_confirmation} required />
                </div>
                <Button type="submit" disabled={$password.processing}>Cambiar contraseña</Button>
            </form>
        {/if}
    </div>
</AppLayout>
