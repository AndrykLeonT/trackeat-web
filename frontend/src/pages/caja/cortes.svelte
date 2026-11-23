<script>
    import { Link, page } from '@inertiajs/svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import PageTitle from '@/components/page-title.svelte';
    import { dinero, fecha } from '@/lib/caja';

    let { cortes = [] } = $props();

    let esAdmin = $derived($page.props.auth?.user?.rol === 'administrador');
</script>

<PageTitle title="Historial de cortes" />
<AppLayout headerTitle="Caja">
    <div class="space-y-6">
        <div class="border-b border-border pb-5">
            <h1 class="text-2xl font-bold">Historial de cortes</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                {esAdmin ? 'Cortes cerrados de todos los cajeros.' : 'Tus cortes cerrados.'} Son de solo lectura: sus totales se congelaron al cerrar.
            </p>
        </div>

        {#if cortes.length === 0}
            <p class="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground">Todavía no hay cortes cerrados.</p>
        {:else}
            <div class="overflow-x-auto rounded-xl border border-border bg-card">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Corte</th>
                            {#if esAdmin}<th class="px-4 py-3">Cajero</th>{/if}
                            <th class="px-4 py-3">Apertura</th>
                            <th class="px-4 py-3">Cierre</th>
                            <th class="px-4 py-3 text-right">Sistema</th>
                            <th class="px-4 py-3 text-right">Real</th>
                            <th class="px-4 py-3 text-right">Propinas</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each cortes as corte (corte.id)}
                            <tr class="border-t border-border hover:bg-muted/40">
                                <td class="px-4 py-3"
                                    ><Link
                                        href={route('caja.cortes.show', corte.id)}
                                        class="font-medium text-primary underline-offset-4 hover:underline">#{corte.id}</Link
                                    ></td
                                >
                                {#if esAdmin}<td class="px-4 py-3">{corte.cajero}</td>{/if}
                                <td class="px-4 py-3 whitespace-nowrap">{fecha(corte.fecha_apertura)}</td>
                                <td class="px-4 py-3 whitespace-nowrap">{fecha(corte.fecha_cierre)}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{dinero(corte.total_sistema)}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{dinero(corte.total_real)}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{dinero(corte.total_propinas)}</td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        {/if}
    </div>
</AppLayout>
