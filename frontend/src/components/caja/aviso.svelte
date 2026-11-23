<script>
    import { page } from '@inertiajs/svelte';

    // Avisos del servidor (session 'status') y errores generales de la operación.
    let { campos = [] } = $props();

    let estado = $derived($page.props.flash?.status);
    let errores = $derived(campos.map((c) => $page.props.errors?.[c]).filter(Boolean));
</script>

{#if estado}
    <p role="status" class="rounded-lg border border-emerald-600/30 bg-emerald-600/10 px-4 py-2.5 text-sm text-emerald-700 dark:text-emerald-300">
        {estado}
    </p>
{/if}
{#each errores as error (error)}
    <p role="alert" class="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-2.5 text-sm text-destructive">{error}</p>
{/each}
