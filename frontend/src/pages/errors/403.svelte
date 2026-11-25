<script>
    import { Link, page } from '@inertiajs/svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import PageTitle from '@/components/page-title.svelte';
</script>

<PageTitle title="Acceso denegado — TrackEat" />
<AppLayout headerTitle="Acceso denegado">
    <section class="mx-auto max-w-lg space-y-4 rounded-2xl border border-border bg-card p-6">
        <h1 class="text-2xl font-bold">Acceso denegado</h1>
        <p class="text-muted-foreground">Tu cuenta no tiene permiso para acceder a esta página o realizar esta operación.</p>
        {#if $page.props.auth?.user}
            <a href="/menu/" class="font-medium underline">Ir al menú</a>
        {:else}
            <Link href="/login" class="font-medium underline">Iniciar sesión</Link>
        {/if}
    </section>
</AppLayout>
