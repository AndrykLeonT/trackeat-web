<script>
    // HU-09 · Tarjeta a nivel de PEDIDO para mesero, cajero y administrador ("Por entregar").
    // El pedido solo está "Listo" cuando todas sus estaciones lo están, y la entrega es todo o nada:
    // "Marcar entregado" aparece únicamente entonces. Al desplegarla se ve cada producto con su estación y su estado.
    import Clock from '@lucide/svelte/icons/clock';
    import ChevronRight from '@lucide/svelte/icons/chevron-right';
    import { states, stations } from '@/lib/trackeat';
    import { formatoFolio } from '@/lib/folio';
    import { dinero } from '@/lib/caja';
    import CancelarPedido from '@/components/ui/cancelar-pedido.svelte';

    let { pedido, user, motivos = [], ahora, disabled = false, onentregar, oncancelled = () => {} } = $props();
    let abierto = $state(false);

    const listo = $derived(pedido.estado === 'listo');
    const minutos = $derived(Math.max(0, Math.floor((ahora - Date.parse(pedido.created_at)) / 60000)));
    const espera = $derived(
        minutos < 60 ? `${minutos} min` : minutos < 1440 ? `${Math.floor(minutos / 60)} h ${minutos % 60} min` : `${Math.floor(minutos / 1440)} d`,
    );
    const piezas = $derived(pedido.items.reduce((suma, item) => suma + item.cantidad, 0));
    const porCobrar = $derived(Number(pedido.saldo) > 0);
</script>

<article
    aria-label={`Pedido ${formatoFolio(pedido.numero_pedido)}`}
    class="rounded-xl border bg-card p-4 shadow-xs {listo ? 'border-emerald-600/50 ring-1 ring-emerald-600/30' : 'border-border'}"
>
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h3 class="break-all text-lg font-bold leading-tight">{formatoFolio(pedido.numero_pedido)}</h3>
            <p class="flex flex-wrap items-center gap-x-2 text-sm text-muted-foreground">
                <span>{pedido.tipo_entrega === 'comer_aqui' ? `Mesa ${pedido.num_mesa}` : 'Para llevar'}</span>
                <span class="inline-flex items-center gap-1"><Clock class="size-3" />hace {espera}</span>
                <span>{dinero(pedido.total)}</span>
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            {#if user?.rol === 'cajero'}<span
                    class="rounded-full border px-2 py-0.5 text-xs font-semibold {porCobrar
                        ? 'border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-300'
                        : 'border-emerald-600/30 bg-emerald-600/10 text-emerald-700 dark:text-emerald-300'}"
                    >{porCobrar ? `Saldo ${dinero(pedido.saldo)}` : 'Pagado'}</span
                >{/if}
            <span
                class="rounded-full border px-2.5 py-0.5 text-sm font-bold {listo
                    ? 'border-emerald-600/30 bg-emerald-600/10 text-emerald-700 dark:text-emerald-300'
                    : 'border-primary/20 bg-primary/10 text-primary'}">{states[pedido.estado]}</span
            >
        </div>
    </header>

    <button
        type="button"
        aria-expanded={abierto}
        onclick={() => (abierto = !abierto)}
        class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-primary"
        ><ChevronRight class="size-4 transition-transform {abierto ? 'rotate-90' : ''}" />{abierto ? 'Ocultar' : 'Ver'} productos ({piezas})</button
    >
    {#if abierto}
        <ul class="mt-2 divide-y divide-border rounded-lg border border-border text-sm" aria-label="Productos">
            {#each pedido.items as item (item.id)}
                <li class="flex flex-wrap items-start justify-between gap-2 px-3 py-2">
                    <div class="min-w-0">
                        <p><strong>{item.cantidad}×</strong> {item.nombre}</p>
                        {#if item.notas}<p class="text-xs text-amber-800 dark:text-amber-300">Nota: {item.notas}</p>{/if}
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {stations[item.estacion] ?? item.estacion} · <span class="font-semibold text-foreground">{states[item.estado] ?? item.estado}</span>
                    </p>
                </li>
            {/each}
        </ul>
    {/if}

    <div class="mt-3 flex flex-wrap items-center gap-2">
        {#if listo}<button
                type="button"
                {disabled}
                onclick={() => onentregar(pedido.id)}
                class="rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-primary-foreground disabled:opacity-50">Marcar entregado</button
            >{/if}
        {#if user?.rol === 'cajero' && porCobrar}<a
                href={`/caja/pedidos/${pedido.id}/cobrar`}
                class="rounded-lg border border-border px-3 py-2 text-sm font-semibold">Cobrar</a
            >{/if}
        <CancelarPedido order={pedido} {user} {motivos} compact {oncancelled} />
    </div>
</article>
