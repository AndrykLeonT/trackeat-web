<script>
    import { useForm } from '@inertiajs/svelte';
    import LoaderCircle from '@lucide/svelte/icons/loader-circle';

    import PageTitle from '@/components/page-title.svelte';
    import TextLink from '@/components/text-link.svelte';
    import Button from '@/components/ui/button.svelte';
    import AuthLayout from '@/layouts/auth-layout.svelte';

    let { status } = $props();

    let form = useForm({});

    function submit(e) {
        e.preventDefault();

        $form.post(route('verification.send'));
    }
</script>

<AuthLayout title="Verify email" description="Please verify your email address by clicking on the link we just emailed to you.">
    <PageTitle title="Email verification" />

    {#if status === 'verification-link-sent'}
        <div class="mb-4 text-center text-sm font-medium text-green-600">
            A new verification link has been sent to the email address you provided during registration.
        </div>
    {/if}

    <form onsubmit={submit} class="space-y-6 text-center">
        <Button disabled={$form.processing} variant="secondary">
            {#if $form.processing}
                <LoaderCircle class="h-4 w-4 animate-spin" />
            {/if}
            Resend verification email
        </Button>

        <TextLink href={route('logout')} method="post" class="mx-auto block text-sm">Log out</TextLink>
    </form>
</AuthLayout>
