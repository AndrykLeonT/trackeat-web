<script>
    // HU-06 · Una comanda = un pedido en UNA estación, con solo los productos de esa estación.
    // El botón respeta la regla de T-06 (el servidor la aplica de todos modos): cocinero y administrador preparan y
    // marcan listo. Nadie entrega desde aquí (HU-09: entrega por pedido completo). Solo el administrador cancela (HU-05).
    import Clock from '@lucide/svelte/icons/clock';
    import ChefHat from '@lucide/svelte/icons/chef-hat';
    import { states, stations } from '@/lib/trackeat';
    import { formatoFolio } from '@/lib/folio';
    import CancelarPedido from '@/components/ui/cancelar-pedido.svelte';

    let { comanda, user, motivos = [], ahora, disabled = false, onadvance, oncancelled = () => {} } = $props();

    // Por estación solo se prepara y se marca listo. La entrega es del pedido completo, en "Por entregar" (HU-09).
    const siguiente = $derived({ recibido: 'preparacion', preparacion: 'listo' }[comanda.estado]);
    const puedeAvanzar = $derived(
        !!siguiente &&
            (user?.rol === 'administrador' || (user?.rol === 'cocinero' && (user.estacion === 'general' || user.estacion === comanda.estacion))),
    );
    const minutos = $derived(Math.max(0, Math.floor((ahora - Date.parse(comanda.pedido_creado)) / 60000)));
    // "7 min", "1 h 20 min" o "3 d": legible aunque la comanda lleve días (p. ej., datos de prueba).
    const espera = $derived(
        minutos < 60 ? `${minutos} min` : minutos < 1440 ? `${Math.floor(minutos / 60)} h ${minutos % 60} min` : `${Math.floor(minutos / 1440)} d`,
    );
    // Para cancelar, el componente necesita el pedido; con esta comanda basta para mostrar/ocultar el botón.
    const pedido = $derived({
        id: comanda.id_pedido,
        numero_pedido: comanda.numero_pedido,
        estado: comanda.estado,
        estaciones: [{ estado: comanda.estado }],
    });
</script>

<article
    aria-label={`${formatoFolio(comanda.numero_pedido)} · ${stations[comanda.estacion] ?? comanda.estacion}`}
    class="space-y-3 rounded-xl border border-border bg-card p-4 shadow-xs"
>
    <header class="flex items-start justify-between gap-2">
        <div class="min-w-0">
            <h3 class="break-all text-lg font-bold leading-tight">{formatoFolio(comanda.numero_pedido)}</h3>
            <p class="text-xs text-muted-foreground">{comanda.tipo_entrega === 'comer_aqui' ? `Mesa ${comanda.num_mesa}` : 'Para llevar'}</p>
        </div>
        <span class="shrink-0 rounded-full border border-primary/20 bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary"
            >{stations[comanda.estacion] ?? comanda.estacion}</span
        >
    </header>
    <ul class="space-y-2 text-sm">
        {#each comanda.items as item (item.id)}
            <li>
                <span class="mr-1.5 rounded-md bg-primary/10 px-1.5 py-0.5 text-xs font-bold text-primary">{item.cantidad}×</span><strong
                    >{item.nombre}</strong
                >
                {#if item.notas}<p class="mt-1 rounded-md bg-amber-500/10 px-2 py-1 text-xs font-medium text-amber-800 dark:text-amber-300">
                        Nota: {item.notas}
                    </p>{/if}
            </li>
        {/each}
    </ul>
    <p class="flex items-center gap-1 text-xs {minutos >= 15 && comanda.estado !== 'entregado' ? 'font-semibold text-destructive' : 'text-muted-foreground'}">
        <Clock class="size-3" />{comanda.estado === 'entregado' ? `Entregada ${new Date(comanda.actualizado).toLocaleTimeString('es-MX', { timeStyle: 'short' })}` : `hace ${espera}`}
    </p>
    {#if puedeAvanzar}
        <button
            type="button"
            {disabled}
            onclick={() => onadvance(comanda.id_pedido, comanda.estacion, siguiente)}
            class="flex w-full items-center justify-center gap-1 rounded-lg bg-primary px-2 py-2 text-xs font-semibold text-primary-foreground disabled:opacity-50"
            ><ChefHat class="size-3" />{{ preparacion: 'Empezar a preparar', listo: 'Marcar listo' }[siguiente]}</button
        >
    {/if}
    {#if user?.rol === 'administrador' && comanda.estado !== 'entregado'}<CancelarPedido order={pedido} {user} {motivos} compact {oncancelled} />{/if}
</article>
