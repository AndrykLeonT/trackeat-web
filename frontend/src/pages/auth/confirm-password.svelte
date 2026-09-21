<script>
    import { useForm } from '@inertiajs/svelte';
    import LoaderCircle from '@lucide/svelte/icons/loader-circle';

    import InputError from '@/components/input-error.svelte';
    import PageTitle from '@/components/page-title.svelte';
    import Button from '@/components/ui/button.svelte';
    import Input from '@/components/ui/input.svelte';
    import Label from '@/components/ui/label.svelte';
    import AuthLayout from '@/layouts/auth-layout.svelte';

    let form = useForm({
        password: '',
    });

    function submit(e) {
        e.preventDefault();

        $form.post(route('password.confirm'), {
            onFinish: () => $form.reset('password'),
        });
    }
</script>

<AuthLayout title="Confirm your password" description="This is a secure area of the application. Please confirm your password before continuing.">
    <PageTitle title="Confirm password" />

    <form onsubmit={submit}>
        <div class="space-y-6">
            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <Input
                    id="password"
                    type="password"
                    name="password"
                    placeholder="Password"
                    autocomplete="current-password"
                    bind:value={$form.password}
                    autofocus
                />

                <InputError message={$form.errors.password} />
            </div>

            <div class="flex items-center">
                <Button class="w-full" disabled={$form.processing}>
                    {#if $form.processing}
                        <LoaderCircle class="h-4 w-4 animate-spin" />
                    {/if}
                    Confirm password
                </Button>
            </div>
        </div>
    </form>
</AuthLayout>
