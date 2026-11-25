<script>
    // HU-10 · Detalle de un pedido para el administrador: productos, estaciones, bitácora y motivo de cancelación.
    import { Link } from '@inertiajs/svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import PageTitle from '@/components/page-title.svelte';
    import { states, stations } from '@/lib/trackeat';
    import { formatoFolio } from '@/lib/folio';

    let { pedido } = $props();
    const money = (value) => `$${Number(value).toFixed(2)}`;
    const fecha = new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'medium' });
    const estacion = (valor) => stations[valor] ?? valor;
</script>

<PageTitle title={`Pedido ${formatoFolio(pedido.numero_pedido)} — TrackEat`} />
<AppLayout headerTitle="Pedidos">
    <div class="max-w-4xl space-y-5">
        <nav class="flex gap-4 text-sm font-semibold text-primary">
            <Link href="/admin/pedidos">Volver a pedidos</Link>
            <Link href={`/pedidos/${pedido.id}`}>Ver seguimiento y cancelación</Link>
        </nav>

        <header class="flex flex-wrap items-start justify-between gap-4 border-b border-border pb-5">
            <div class="space-y-1">
                <h1 class="break-words text-3xl font-bold">Pedido {formatoFolio(pedido.numero_pedido)}</h1>
                {#if formatoFolio(pedido.numero_pedido) !== pedido.numero_pedido}<p class="text-sm text-muted-foreground">{pedido.numero_pedido}</p>{/if}
                <p class="text-sm text-muted-foreground">
                    {fecha.format(new Date(pedido.created_at))} · {pedido.tipo_entrega === 'comer_aqui' ? `Mesa ${pedido.num_mesa}` : 'Para llevar'}
                </p>
                <p class="text-sm">
                    <span class="text-muted-foreground">Levantado por:</span>
                    {#if pedido.usuario}
                        <span class="font-medium">{pedido.usuario.name}</span> · {pedido.usuario.email}{pedido.usuario.telefono ? ` · ${pedido.usuario.telefono}` : ''}
                    {:else}—{/if}
                </p>
            </div>
            <p role="status" aria-label="Estado del pedido" class="rounded-full border border-primary/20 bg-primary/10 px-4 py-1.5 text-base font-bold text-primary">
                {states[pedido.estado] ?? pedido.estado}
            </p>
        </header>

        {#if pedido.estado === 'cancelado'}
            <p class="rounded-xl border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive">
                <span class="font-semibold">Pedido cancelado.</span>
                {#if pedido.motivo_cancelacion} Motivo: {pedido.motivo_cancelacion}{/if}
            </p>
        {/if}

        <section aria-labelledby="estaciones-titulo" class="space-y-3 rounded-2xl border border-border bg-card p-5">
            <h2 id="estaciones-titulo" class="text-lg font-bold">Estaciones</h2>
            <ul class="divide-y divide-border">
                {#each pedido.estaciones as e (e.id)}
                    <li class="flex justify-between gap-2 py-2 text-sm">
                        <span class="font-semibold">{estacion(e.estacion)}</span><span>{states[e.estado] ?? e.estado}</span>
                    </li>
                {/each}
            </ul>
        </section>

        <section aria-labelledby="productos-titulo" class="space-y-3 rounded-2xl border border-border bg-card p-5">
            <h2 id="productos-titulo" class="text-lg font-bold">Productos</h2>
            <ul class="divide-y divide-border">
                {#each pedido.items as item (item.id)}
                    <li class="py-3">
                        <p class="flex flex-wrap justify-between gap-2">
                            <span class="font-semibold">{item.cantidad} × {item.producto?.nombre ?? 'Producto'}</span>
                            <span class="font-semibold">{money(item.cantidad * Number(item.precio_unitario))}</span>
                        </p>
                        <p class="text-xs text-muted-foreground">{money(item.precio_unitario)} c/u · {estacion(item.estacion)}</p>
                        {#if item.notas}<p class="mt-1 whitespace-pre-wrap break-words text-sm text-muted-foreground">Nota: {item.notas}</p>{/if}
                    </li>
                {/each}
            </ul>
            <p class="flex justify-between border-t border-border pt-3 text-lg font-bold"><span>Total</span><span>{money(pedido.total)} MXN</span></p>
        </section>

        <section aria-labelledby="bitacora-titulo" class="space-y-3 rounded-2xl border border-border bg-card p-5">
            <h2 id="bitacora-titulo" class="text-lg font-bold">Bitácora</h2>
            <ol class="divide-y divide-border">
                {#each pedido.historial as h (h.id)}
                    <li class="py-2 text-sm">
                        <p>
                            <span class="font-semibold">{estacion(h.estacion)}</span>:
                            {states[h.estado_anterior] ?? h.estado_anterior} → <span class="font-semibold">{states[h.estado_nuevo] ?? h.estado_nuevo}</span>
                        </p>
                        <p class="text-muted-foreground">
                            {fecha.format(new Date(h.created_at))} · {h.responsable ? `${h.responsable.name} (${h.responsable.rol})` : 'Usuario desconocido'}
                        </p>
                    </li>
                {:else}
                    <li class="py-3 text-sm text-muted-foreground">Todavía no hay cambios de estado.</li>
                {/each}
            </ol>
        </section>
    </div>
</AppLayout>
