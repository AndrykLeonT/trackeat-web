<script>
    import { useForm } from '@inertiajs/svelte';
    import LoaderCircle from '@lucide/svelte/icons/loader-circle';

    import InputError from '@/components/input-error.svelte';
    import PageTitle from '@/components/page-title.svelte';
    import TextLink from '@/components/text-link.svelte';
    import Button from '@/components/ui/button.svelte';
    import Checkbox from '@/components/ui/checkbox.svelte';
    import Input from '@/components/ui/input.svelte';
    import Label from '@/components/ui/label.svelte';
    import AuthLayout from '@/layouts/auth-layout.svelte';

    let { status, canResetPassword } = $props();

    let form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    function submit(e) {
        e.preventDefault();
        $form.post(route('login'), {
            onFinish: () => $form.reset('password'),
        });
    }
</script>

<AuthLayout title="Log in to your account" description="Enter your email and password below to log in">
    <PageTitle title="Log in" />

    <form class="flex flex-col gap-6" onsubmit={submit}>
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    autofocus
                    tabindex={1}
                    autocomplete="email"
                    bind:value={$form.email}
                    placeholder="email@example.com"
                />
                <InputError message={$form.errors.email} />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center">
                    <Label for="password">Password</Label>
                    {#if canResetPassword}
                        <TextLink href={route('password.request')} class="ml-auto text-sm" tabindex={5}>Forgot password?</TextLink>
                    {/if}
                </div>
                <Input
                    id="password"
                    type="password"
                    required
                    tabindex={2}
                    autocomplete="current-password"
                    bind:value={$form.password}
                    placeholder="Password"
                />
                <InputError message={$form.errors.password} />
            </div>

            <div class="flex items-center space-x-3">
                <Checkbox id="remember" name="remember" tabindex={3} bind:checked={$form.remember} />
                <Label for="remember">Remember me</Label>
            </div>

            <Button type="submit" class="mt-4 w-full" tabindex={4} disabled={$form.processing}>
                {#if $form.processing}
                    <LoaderCircle class="h-4 w-4 animate-spin" />
                {/if}
                Log in
            </Button>
        </div>

        <div class="text-muted-foreground text-center text-sm">
            Don't have an account?
            <TextLink href={route('register')} tabindex={5}>Sign up</TextLink>
        </div>
    </form>

    {#if status}
        <div class="mb-4 text-center text-sm font-medium text-green-600">{status}</div>
    {/if}
</AuthLayout>
