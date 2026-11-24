<script>
    import { Link } from '@inertiajs/svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import PageTitle from '@/components/page-title.svelte';
    import Aviso from '@/components/caja/aviso.svelte';
    import Totales from '@/components/caja/totales.svelte';
    import PagosTabla from '@/components/caja/pagos-tabla.svelte';
    import { diferencia, dinero, fecha } from '@/lib/caja';

    let { corte, resumen, pagos = [] } = $props();

    let cerrado = $derived(corte.estado === 'cerrado');
</script>

<PageTitle title="Corte #{corte.id}" />
<AppLayout headerTitle="Caja">
    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4 border-b border-border pb-5">
            <div>
                <h1 class="text-2xl font-bold">
                    Corte #{corte.id} <span class="text-base font-normal text-muted-foreground">· {corte.cajero}</span>
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {fecha(corte.fecha_apertura)} → {cerrado ? fecha(corte.fecha_cierre) : 'abierto'}.
                    {cerrado ? 'Solo lectura: los totales se congelaron al cerrar.' : 'El turno sigue abierto.'}
                </p>
            </div>
            <Link href={route('caja.cortes')} class="text-sm text-primary underline-offset-4 hover:underline">← Historial de cortes</Link>
        </div>

        <Aviso />

        <Totales {resumen} inicial={corte.total_caja_inicial} />

        {#if cerrado}
            <div class="overflow-x-auto rounded-xl border border-border bg-card">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr
                            ><th class="px-4 py-3">Método</th><th class="px-4 py-3 text-right">Contado</th><th class="px-4 py-3 text-right"
                                >Diferencia</th
                            ></tr
                        >
                    </thead>
                    <tbody>
                        {#each [['Efectivo (con fondo)', corte.total_caja_final, resumen.diferencia_efectivo], ['Tarjeta', corte.total_tarjeta_real, resumen.diferencia_tarjeta], ['Transferencia', corte.total_transferencia_real, resumen.diferencia_transferencia]] as [nombre, contado, dif] (nombre)}
                            {@const d = diferencia(dif)}
                            <tr class="border-t border-border">
                                <td class="px-4 py-3">{nombre}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{dinero(contado)}</td>
                                <td class="px-4 py-3 text-right font-medium {d.tono}">{d.texto}</td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
            <p class="text-sm text-muted-foreground">
                Total real del turno (sin el fondo): <b>{dinero(corte.total_real)}</b> contra {dinero(resumen.total_sistema)} del sistema.
            </p>
        {/if}

        <h2 class="text-lg font-semibold">Pagos del corte</h2>
        <PagosTabla {pagos} />
    </div>
</AppLayout>
