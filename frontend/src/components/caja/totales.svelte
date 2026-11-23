<script>
    import { dinero } from '@/lib/caja';

    // Totales de un corte según CajaService::resumen: solo pagos activos y no reembolsados.
    // La propina se muestra aparte y no forma parte del total del sistema.
    let { resumen, inicial } = $props();
</script>

<dl class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
    {#each [['Fondo inicial', inicial], ['Efectivo', resumen.total_efectivo], ['Tarjeta', resumen.total_tarjeta], ['Transferencia', resumen.total_transferencia], ['Propinas', resumen.total_propinas], ['Total del sistema', resumen.total_sistema]] as [nombre, valor] (nombre)}
        <div class="rounded-xl border border-border bg-card p-4">
            <dt class="text-xs uppercase tracking-wide text-muted-foreground">{nombre}</dt>
            <dd class="mt-1 text-lg font-semibold tabular-nums">{dinero(valor)}</dd>
        </div>
    {/each}
</dl>
