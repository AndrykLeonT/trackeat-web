<script>
    import { onMount } from 'svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import { api, session, connectLive, states } from '@/lib/trackeat';
    let { pedido } = $props();
    let order = $derived(pedido);
    let error = $state('');
    let connectionLabel = $state('Conectando…');
    let connected = $state(false);
    onMount(() => {
        let stopped = false;
        let stop = () => {};
        const refresh = async () => {
            try {
                order = await api(`pedidos/${pedido.id}`);
                error = '';
            } catch (e) {
                error = e.message;
                if ([401, 403, 404].includes(e.status)) {
                    order = null;
                    stop();
                }
            }
        };
        session()
            .then((data) => {
                if (!stopped)
                    stop = connectLive(data, refresh, (label, value) => {
                        connectionLabel = label;
                        connected = value;
                    });
            })
            .catch((e) => (error = e.message));
        return () => {
            stopped = true;
            stop();
        };
    });
</script>

<AppLayout headerTitle="Seguimiento del pedido" {connectionLabel} {connected}>
    <div class="max-w-3xl space-y-5">
        <a href="/menu/" class="text-primary">Volver al menú</a>
        {#if error}<p role="alert" class="text-destructive">{error}</p>{/if}
        {#if order}<h1 class="break-words text-2xl font-bold">Pedido {order.numero_pedido}</h1>
            <p role="status" class="text-lg font-semibold">{states[order.estado] || order.estado}</p>
            <div class="flex flex-wrap gap-3">
                {#each order.estaciones as station (station.id)}<p class="rounded-xl border border-border bg-card p-3">
                        {station.estacion}: {states[station.estado]}
                    </p>{/each}
            </div>
            <section aria-label="Resumen del pedido" class="space-y-3 rounded-2xl border border-border bg-card p-5">
                {#each order.items as item (item.id)}<div class="border-b border-border pb-3">
                        <p class="font-semibold">
                            {item.cantidad} × {item.producto?.nombre ?? 'Producto'} — ${Number(item.precio_unitario).toFixed(2)} MXN
                        </p>
                        {#if item.notas}<p class="whitespace-pre-wrap break-words text-sm">{item.notas}</p>{/if}
                    </div>{/each}
                <p class="text-lg font-bold">Total ${Number(order.total).toFixed(2)} MXN</p>
                <p>{order.tipo_entrega === 'comer_aqui' ? `Mesa ${order.num_mesa}` : 'Para llevar'}</p>
            </section>
        {/if}
    </div>
</AppLayout>
