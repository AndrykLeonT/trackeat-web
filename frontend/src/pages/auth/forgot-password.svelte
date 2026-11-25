<script>
    import { useForm } from '@inertiajs/svelte';
    import LoaderCircle from '@lucide/svelte/icons/loader-circle';

    import InputError from '@/components/input-error.svelte';
    import PageTitle from '@/components/page-title.svelte';
    import TextLink from '@/components/text-link.svelte';
    import Button from '@/components/ui/button.svelte';
    import Input from '@/components/ui/input.svelte';
    import Label from '@/components/ui/label.svelte';
    import AuthLayout from '@/layouts/auth-layout.svelte';

    let { status } = $props();

    let form = useForm({
        email: '',
    });

    function submit(e) {
        e.preventDefault();

        $form.post(route('password.email'));
    }
</script>

<AuthLayout title="Recuperar contraseña" description="Ingresa el correo de tu cuenta para recibir un enlace de recuperación">
    <PageTitle title="Recuperar contraseña" />

    {#if status}
        <div class="mb-4 text-center text-sm font-medium text-chart-2">{status}</div>
    {/if}

    <div class="space-y-6">
        <form onsubmit={submit}>
            <div class="grid gap-2">
                <Label for="email">Correo electrónico</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="email"
                    required
                    bind:value={$form.email}
                    autofocus
                    placeholder="email@example.com"
                />

                <InputError message={$form.errors.email} />
            </div>

            <div class="my-6 flex items-center justify-start">
                <Button type="submit" class="w-full" disabled={$form.processing}>
                    {#if $form.processing}
                        <LoaderCircle class="h-4 w-4 animate-spin" />
                    {/if}
                    Enviar enlace de recuperación
                </Button>
            </div>
        </form>

        <div class="text-muted-foreground space-x-1 text-center text-sm">
            <span>Volver a</span>
            <TextLink href={route('login')}>iniciar sesión</TextLink>
        </div>
    </div>
</AuthLayout>
