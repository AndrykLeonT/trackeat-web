<script>
    import { dinero, fecha, metodos } from '@/lib/caja';
    import { formatoFolio } from '@/lib/folio';

    // Tabla de pagos de un corte. Con `acciones` se muestra una columna extra (turno actual).
    let { pagos = [], acciones = null } = $props();

    const tonos = {
        activo: 'bg-emerald-600/10 text-emerald-700 dark:text-emerald-300',
        eliminado: 'bg-muted text-muted-foreground',
        reembolsado: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    };
</script>

{#if pagos.length === 0}
    <p class="rounded-xl border border-dashed border-border p-6 text-center text-sm text-muted-foreground">Todavía no hay pagos en este corte.</p>
{:else}
    <div class="overflow-x-auto rounded-xl border border-border bg-card">
        <table class="w-full text-sm">
            <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                <tr>
                    <th class="px-4 py-3">Hora</th>
                    <th class="px-4 py-3">Pedido</th>
                    <th class="px-4 py-3">Método</th>
                    <th class="px-4 py-3 text-right">Monto</th>
                    <th class="px-4 py-3 text-right">Propina</th>
                    <th class="px-4 py-3">Estado</th>
                    {#if acciones}<th class="px-4 py-3"></th>{/if}
                </tr>
            </thead>
            <tbody>
                {#each pagos as pago (pago.id)}
                    <tr class="border-t border-border">
                        <td class="px-4 py-3 whitespace-nowrap">{fecha(pago.fecha)}</td>
                        <td class="px-4 py-3 font-medium">{formatoFolio(pago.pedido)}</td>
                        <td class="px-4 py-3">{metodos[pago.metodo_pago]}</td>
                        <td class="px-4 py-3 text-right tabular-nums {pago.estado === 'activo' ? '' : 'line-through text-muted-foreground'}"
                            >{dinero(pago.monto)}</td
                        >
                        <td class="px-4 py-3 text-right tabular-nums">{dinero(pago.propina)}</td>
                        <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium {tonos[pago.estado]}">{pago.estado}</span></td
                        >
                        {#if acciones}<td class="px-4 py-3 text-right">{@render acciones(pago)}</td>{/if}
                    </tr>
                {/each}
            </tbody>
        </table>
    </div>
{/if}
