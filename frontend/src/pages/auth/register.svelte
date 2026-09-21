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

    let form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    function submit(e) {
        e.preventDefault();
        $form.post(route('register'), {
            onFinish: () => $form.reset('password', 'password_confirmation'),
        });
    }
</script>

<AuthLayout title="Create an account" description="Enter your details below to create your account">
    <PageTitle title="Register" />
    <form class="flex flex-col gap-6" onsubmit={submit}>
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    autofocus
                    tabindex={1}
                    autocomplete="name"
                    bind:value={$form.name}
                    disabled={$form.processing}
                    placeholder="Full name"
                />
                <InputError message={$form.errors.name} class="mt-2" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    tabindex={2}
                    autocomplete="email"
                    bind:value={$form.email}
                    disabled={$form.processing}
                    placeholder="email@example.com"
                />
                <InputError message={$form.errors.email} />
            </div>

            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <Input
                    id="password"
                    type="password"
                    required
                    tabindex={3}
                    autocomplete="new-password"
                    bind:value={$form.password}
                    disabled={$form.processing}
                    placeholder="Password"
                />
                <InputError message={$form.errors.password} />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <Input
                    id="password_confirmation"
                    type="password"
                    required
                    tabindex={4}
                    autocomplete="new-password"
                    bind:value={$form.password_confirmation}
                    disabled={$form.processing}
                    placeholder="Confirm password"
                />
                <InputError message={$form.errors.password_confirmation} />
            </div>

            <Button type="submit" class="mt-2 w-full" tabindex={5} disabled={$form.processing}>
                {#if $form.processing}
                    <LoaderCircle class="h-4 w-4 animate-spin" />
                {/if}
                Create account
            </Button>
        </div>

        <div class="text-muted-foreground text-center text-sm">
            Already have an account?
            <TextLink href={route('login')} tabindex={6}>Log in</TextLink>
        </div>
    </form>
</AuthLayout>
