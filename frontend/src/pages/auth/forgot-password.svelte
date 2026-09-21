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

<AuthLayout title="Forgot password" description="Enter your email to receive a password reset link">
    <PageTitle title="Forgot password" />

    {#if status}
        <div class="mb-4 text-center text-sm font-medium text-green-600">{status}</div>
    {/if}

    <div class="space-y-6">
        <form onsubmit={submit}>
            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input id="email" type="email" name="email" autocomplete="off" bind:value={$form.email} autofocus placeholder="email@example.com" />

                <InputError message={$form.errors.email} />
            </div>

            <div class="my-6 flex items-center justify-start">
                <Button class="w-full" disabled={$form.processing}>
                    {#if $form.processing}
                        <LoaderCircle class="h-4 w-4 animate-spin" />
                    {/if}
                    Email password reset link
                </Button>
            </div>
        </form>

        <div class="text-muted-foreground space-x-1 text-center text-sm">
            <span>Or, return to</span>
            <TextLink href={route('login')}>log in</TextLink>
        </div>
    </div>
</AuthLayout>
