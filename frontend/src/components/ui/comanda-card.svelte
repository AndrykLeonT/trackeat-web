<script>
    import Clock from '@lucide/svelte/icons/clock';
    import ChefHat from '@lucide/svelte/icons/chef-hat';
    import { states } from '@/lib/trackeat';
    let { order, role, disabled = false, onadvance } = $props();
    function next(state) {
        return { recibido: 'preparacion', preparacion: 'listo', listo: 'entregado' }[state];
    }
    function allowed(state) {
        return (
            role === 'administrador' ||
            (role === 'cocinero' && state !== 'entregado') ||
            (['mesero', 'cajero'].includes(role) && state === 'entregado')
        );
    }
</script>

<article class="flex flex-col justify-between overflow-hidden rounded-2xl border border-border border-t-4 border-t-primary bg-card p-5 shadow-xs">
    <div>
        <div class="space-y-2 border-b border-border pb-3">
            <h2 class="break-all text-xs font-bold text-muted-foreground">{order.numero_pedido}</h2>
            <span class="inline-flex rounded-full border border-primary/20 bg-primary/10 px-2 py-1 text-xs font-bold text-primary"
                >{states[order.estado]}</span
            >
            <p class="text-sm">{order.tipo_entrega === 'comer_aqui' ? `Mesa ${order.num_mesa}` : 'Para llevar'}</p>
        </div>
        <ul class="mt-4 space-y-3 text-sm">
            {#each order.items as item (item.id)}<li>
                    <span class="mr-2 rounded-md bg-primary/10 px-1.5 py-0.5 text-xs font-bold text-primary">{item.cantidad}x</span><strong
                        >{item.producto?.nombre || 'Producto'}</strong
                    >{#if item.notas}<p class="mt-1 text-xs text-muted-foreground">{item.notas}</p>{/if}
                </li>{/each}
        </ul>
    </div>
    <div class="mt-5 space-y-3 border-t border-border pt-3">
        <p class="flex items-center gap-1 text-xs text-muted-foreground">
            <Clock class="size-3" />{new Date(order.created_at).toLocaleString('es-MX')}
        </p>
        {#each order.estaciones as station (station.id)}
            <div class="rounded-xl border border-border p-2.5">
                <p class="mb-2 text-xs font-semibold capitalize">{station.estacion} · {states[station.estado]}</p>
                {#if next(station.estado) && allowed(next(station.estado))}<button
                        type="button"
                        {disabled}
                        onclick={() => onadvance(order.id, station.estacion, next(station.estado))}
                        class="flex w-full items-center justify-center gap-1 rounded-lg bg-primary px-2 py-2 text-xs font-semibold text-primary-foreground disabled:opacity-50"
                        ><ChefHat class="size-3" />{{ preparacion: 'Empezar a preparar', listo: 'Marcar listo', entregado: 'Marcar entregado' }[
                            next(station.estado)
                        ]}</button
                    >{/if}
            </div>
        {/each}
    </div>
</article>
