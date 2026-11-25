<script>
    // HU-02 (resumen del pedido) y HU-03 (seguimiento en vivo por estación).
    import { onMount } from 'svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import { api, session, connectLive, states, stations, flow } from '@/lib/trackeat';
    import { formatoFolio } from '@/lib/folio';
    import CancelarPedido from '@/components/ui/cancelar-pedido.svelte';
    import Clock from '@lucide/svelte/icons/clock';
    import CircleCheck from '@lucide/svelte/icons/circle-check';
    import CircleX from '@lucide/svelte/icons/circle-x';
    import Receipt from '@lucide/svelte/icons/receipt';
    import Wifi from '@lucide/svelte/icons/wifi';
    import WifiOff from '@lucide/svelte/icons/wifi-off';

    let { pedido } = $props();
    // Arranca con el pedido que manda el servidor y la página lo reemplaza con cada actualización.
    let order = $derived(pedido);
    let error = $state('');
    let connectionLabel = $state('Conectando…');
    let connected = $state(false);
    // HU-05: quién mira la página y, si es personal, los motivos de cancelación.
    let user = $state(null);
    let motivos = $state([]);
    // Entregado y cancelado son finales: ya no cambian, así que se deja de escuchar y de consultar.
    const finished = $derived(['entregado', 'cancelado'].includes(order?.estado));
    const money = (value) => `$${Number(value).toFixed(2)}`;
    // Detiene tiempo real y sondeo; la asigna onMount (la usa también la cancelación de HU-05).
    let stopLive = () => {};

    onMount(() => {
        let stopped = false;
        let stop = () => {};
        const end = () => {
            stop();
            connectionLabel = 'Pedido terminado';
            connected = false;
        };
        stopLive = end;
        const refresh = async () => {
            try {
                order = await api(`pedidos/${pedido.id}`);
                error = '';
                if (['entregado', 'cancelado'].includes(order.estado)) end();
            } catch (e) {
                error = e.message;
                if ([401, 403, 404].includes(e.status)) {
                    order = null;
                    stop();
                }
            }
        };
        if (['entregado', 'cancelado'].includes(order.estado)) {
            end();
        } else {
            session()
                .then((data) => {
                    user = data.user;
                    motivos = data.motivos_cancelacion ?? [];
                    if (!stopped)
                        stop = connectLive(data, refresh, (label, value) => {
                            connectionLabel = label;
                            connected = value;
                        });
                })
                .catch((e) => (error = e.message));
        }
        return () => {
            stopped = true;
            stop();
        };
    });
</script>

<AppLayout headerTitle="Seguimiento del pedido" {connectionLabel} {connected}>
    <div class="max-w-3xl space-y-5">
        <nav class="flex gap-4 text-sm font-semibold text-primary">
            <a href="/menu/">Volver al menú</a><a href="/mis-pedidos">Mis pedidos</a>
        </nav>
        {#if error}<p role="alert" class="rounded-xl border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">{error}</p>{/if}
        {#if order}
            <header class="flex flex-wrap items-start justify-between gap-4 border-b border-border pb-5">
                <div class="space-y-1">
                    <h1 class="break-words text-3xl font-bold">Pedido {formatoFolio(order.numero_pedido)}</h1>
                    <p class="text-sm text-muted-foreground">Te llamaremos con este número.</p>
                    <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground">
                        <span class="inline-flex items-center gap-1"><Clock class="size-3.5" />{new Date(order.created_at).toLocaleString('es-MX')}</span>
                        <span>{order.tipo_entrega === 'comer_aqui' ? `Mesa ${order.num_mesa}` : 'Para llevar'}</span>
                    </p>
                </div>
                <p role="status" aria-label="Estado del pedido" class="rounded-full border border-primary/20 bg-primary/10 px-4 py-1.5 text-base font-bold text-primary">
                    {states[order.estado] || order.estado}
                </p>
            </header>

            <CancelarPedido
                {order}
                {user}
                {motivos}
                oncancelled={(actualizado) => {
                    order = { ...order, ...actualizado };
                    stopLive();
                }}
            />

            {#if order.estado === 'entregado'}
                <p class="flex items-center gap-2 rounded-xl border border-emerald-600/30 bg-emerald-600/10 p-4 text-sm font-semibold text-emerald-700 dark:text-emerald-300">
                    <CircleCheck class="size-5 shrink-0" />Tu pedido fue entregado. ¡Buen provecho!
                </p>
            {:else if order.estado === 'cancelado'}
                <div class="flex items-start gap-2 rounded-xl border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive">
                    <CircleX class="mt-0.5 size-5 shrink-0" />
                    <div>
                        <p class="font-semibold">Este pedido fue cancelado.</p>
                        {#if order.motivo_cancelacion}<p>Motivo: {order.motivo_cancelacion}</p>{/if}
                    </div>
                </div>
            {/if}

            {#if order.estado !== 'cancelado'}
                <section aria-labelledby="avance-titulo" class="space-y-4 rounded-2xl border border-border bg-card p-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 id="avance-titulo" class="text-lg font-bold">Avance por estación</h2>
                        {#if !finished}<p class="inline-flex items-center gap-1.5 text-xs text-muted-foreground" data-testid="conexion">
                                {#if connected}<Wifi class="size-3.5 text-chart-2" />{:else}<WifiOff class="size-3.5" />{/if}{connectionLabel}
                            </p>{/if}
                    </div>
                    {#each order.estaciones as station (station.id)}
                        {@const actual = flow.indexOf(station.estado)}
                        <div class="space-y-2" aria-label={`${stations[station.estacion] ?? station.estacion}: ${states[station.estado]}`}>
                            <p class="flex justify-between text-sm">
                                <span class="font-semibold">{stations[station.estacion] ?? station.estacion}</span>
                                <span class="text-muted-foreground">{states[station.estado]}</span>
                            </p>
                            <ol class="grid grid-cols-4 gap-1.5">
                                {#each flow as paso, i (paso)}
                                    <li class="space-y-1">
                                        <span
                                            class="block h-2 rounded-full {i < actual
                                                ? 'bg-primary'
                                                : i === actual
                                                  ? 'bg-primary ring-2 ring-primary/30'
                                                  : 'bg-muted'}"
                                        ></span>
                                        <span class="block text-[11px] {i === actual ? 'font-semibold text-foreground' : 'text-muted-foreground'}"
                                            >{states[paso]}</span
                                        >
                                    </li>
                                {/each}
                            </ol>
                        </div>
                    {/each}
                </section>
            {/if}

            <section aria-labelledby="resumen-titulo" class="space-y-3 rounded-2xl border border-border bg-card p-5">
                <h2 id="resumen-titulo" class="flex items-center gap-2 text-lg font-bold"><Receipt class="size-5 text-primary" />Resumen</h2>
                <ul class="divide-y divide-border">
                    {#each order.items as item (item.id)}
                        <li class="py-3">
                            <p class="flex flex-wrap justify-between gap-2">
                                <span class="font-semibold">{item.cantidad} × {item.producto?.nombre ?? 'Producto'}</span>
                                <span class="font-semibold">{money(item.cantidad * Number(item.precio_unitario))}</span>
                            </p>
                            <p class="text-xs text-muted-foreground">{money(item.precio_unitario)} c/u</p>
                            {#if item.notas}<p class="mt-1 whitespace-pre-wrap break-words text-sm text-muted-foreground">Nota: {item.notas}</p>{/if}
                        </li>
                    {/each}
                </ul>
                <p class="flex justify-between border-t border-border pt-3 text-lg font-bold">
                    <span>Total</span><span>{money(order.total)} MXN</span>
                </p>
            </section>
        {/if}
    </div>
</AppLayout>
