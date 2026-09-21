<script>
    import { useForm } from '@inertiajs/svelte';
    import LoaderCircle from '@lucide/svelte/icons/loader-circle';

    import InputError from '@/components/input-error.svelte';
    import PageTitle from '@/components/page-title.svelte';
    import Button from '@/components/ui/button.svelte';
    import Input from '@/components/ui/input.svelte';
    import Label from '@/components/ui/label.svelte';
    import AuthLayout from '@/layouts/auth-layout.svelte';

    let { token, email } = $props();

    let form = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    function submit(e) {
        e.preventDefault();
        $form.post(route('password.store'), {
            onFinish: () => $form.reset('password', 'password_confirmation'),
        });
    }
</script>

<AuthLayout title="Reset password" description="Please enter your new password below">
    <PageTitle title="Reset password" />

    <form onsubmit={submit}>
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">Email</Label>
                <Input id="email" type="email" name="email" autocomplete="email" bind:value={$form.email} class="mt-1 block w-full" readonly />
                <InputError message={$form.errors.email} class="mt-2" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <Input
                    id="password"
                    type="password"
                    name="password"
                    autocomplete="new-password"
                    bind:value={$form.password}
                    class="mt-1 block w-full"
                    autofocus
                    placeholder="Password"
                />
                <InputError message={$form.errors.password} />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <Input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    autocomplete="new-password"
                    bind:value={$form.password_confirmation}
                    class="mt-1 block w-full"
                    placeholder="Confirm password"
                />
                <InputError message={$form.errors.password_confirmation} class="mt-2" />
            </div>

            <Button type="submit" class="mt-4 w-full" disabled={$form.processing}>
                {#if $form.processing}
                    <LoaderCircle class="h-4 w-4 animate-spin" />
                {/if}
                Reset password
            </Button>
        </div>
    </form>
</AuthLayout>
