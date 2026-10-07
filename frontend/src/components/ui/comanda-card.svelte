<script>
    import Clock from '@lucide/svelte/icons/clock';
    import ChefHat from '@lucide/svelte/icons/chef-hat';
    import CheckCircle2 from '@lucide/svelte/icons/check-circle-2';
    import Globe from '@lucide/svelte/icons/globe';
    import Store from '@lucide/svelte/icons/store';
    import User from '@lucide/svelte/icons/user';

    let { order } = $props();

    // Mapas de estilo visual según el estado de la comanda
    const statusConfig = {
        pendiente: {
            label: 'Pendiente',
            badgeClass: 'bg-secondary text-secondary-foreground border-border',
            dotClass: 'bg-secondary-foreground',
            borderAccent: 'border-t-secondary-foreground',
        },
        en_preparacion: {
            label: 'En Preparación',
            badgeClass: 'bg-primary/10 text-primary border-primary/20',
            dotClass: 'bg-primary animate-pulse',
            borderAccent: 'border-t-primary',
        },
        listo: {
            label: 'Listo para Servir',
            badgeClass: 'bg-chart-2/10 text-chart-2 border-chart-2/30',
            dotClass: 'bg-chart-2',
            borderAccent: 'border-t-chart-2',
        },
    };

    const currentStatus = $derived(
        statusConfig[order.status] || {
            label: order.status,
            badgeClass: 'bg-muted text-muted-foreground border-border',
            dotClass: 'bg-muted-foreground',
            borderAccent: 'border-t-muted-foreground',
        },
    );

    const isOnline = $derived(order.origin === 'online');
</script>

<article
    class="flex flex-col justify-between overflow-hidden rounded-2xl border border-border bg-card p-5 shadow-xs transition-all duration-200 hover:shadow-md hover:border-primary/30 border-t-4 {currentStatus.borderAccent}"
>
    <!-- Encabezado de la comanda -->
    <div>
        <div class="flex items-start justify-between gap-2 border-b border-border pb-3">
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-muted-foreground uppercase tracking-wider">
                        {order.code || `#ORD-${order.id}`}
                    </span>

                    {#if isOnline}
                        <span class="inline-flex items-center gap-1 rounded-md bg-primary/10 px-2 py-0.5 text-xs font-bold text-primary border border-primary/20">
                            <Globe class="size-3" />
                            En Línea
                        </span>
                    {:else}
                        <span class="inline-flex items-center gap-1 rounded-md bg-muted px-2 py-0.5 text-xs font-bold text-card-foreground border border-border/50">
                            <Store class="size-3 text-muted-foreground" />
                            {order.table}
                        </span>
                    {/if}
                </div>

                <div class="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                    {#if isOnline}
                        <User class="size-3 text-primary" />
                        <span>Cliente: <strong class="text-card-foreground">{order.customer || 'Usuario Registrado'}</strong> ({order.deliveryType || 'Recoger'})</span>
                    {:else}
                        <span>Mesero: <strong class="text-card-foreground">{order.waiter || 'General'}</strong></span>
                    {/if}
                </div>
            </div>

            <!-- Badge de estado -->
            <span
                class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-bold {currentStatus.badgeClass}"
            >
                <span class="h-1.5 w-1.5 rounded-full {currentStatus.dotClass}"></span>
                {currentStatus.label}
            </span>
        </div>

        <!-- Lista de platillos solicitados -->
        <div class="mt-3.5 space-y-2.5">
            <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
                Platillos de la orden:
            </span>
            <ul class="space-y-1.5 text-sm">
                {#each order.items as item}
                    <li class="flex items-start justify-between text-card-foreground">
                        <div class="flex items-start gap-2">
                            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md bg-primary/10 text-xs font-bold text-primary">
                                {item.quantity}x
                            </span>
                            <div>
                                <span class="font-medium text-card-foreground">{item.name}</span>
                                {#if item.notes}
                                    <p class="text-[11px] text-muted-foreground font-medium italic">
                                        Nota: {item.notes}
                                    </p>
                                {/if}
                            </div>
                        </div>
                    </li>
                {/each}
            </ul>
        </div>
    </div>

    <!-- Pie de tarjeta: Tiempo transcurrido y acciones de cocina -->
    <div class="mt-5 border-t border-border pt-3.5">
        <div class="flex items-center justify-between text-xs text-muted-foreground mb-3">
            <span class="inline-flex items-center gap-1 font-medium text-card-foreground">
                <Clock class="size-3.5 text-primary" />
                {order.timeElapsed || 'Hace 5 min'}
            </span>
            <span class="text-[11px] text-muted-foreground">
                {order.items.reduce((acc, i) => acc + i.quantity, 0)} items
            </span>
        </div>

        <!-- Botones de acción rápida para cocina/mesero -->
        <div class="flex items-center gap-2">
            {#if order.status === 'pendiente'}
                <button
                    type="button"
                    class="w-full inline-flex items-center justify-center gap-1.5 rounded-xl bg-primary px-3 py-2 text-xs font-semibold text-primary-foreground shadow-xs hover:bg-primary/90 transition-colors"
                >
                    <ChefHat class="size-3.5" />
                    <span>Empezar a cocinar</span>
                </button>
            {:else if order.status === 'en_preparacion'}
                <button
                    type="button"
                    class="w-full inline-flex items-center justify-center gap-1.5 rounded-xl bg-chart-2 px-3 py-2 text-xs font-semibold text-primary-foreground shadow-xs hover:bg-chart-2/90 transition-colors"
                >
                    <CheckCircle2 class="size-3.5" />
                    <span>Marcar como Listo</span>
                </button>
            {:else}
                <div class="w-full rounded-xl bg-chart-2/10 border border-chart-2/30 py-1.5 text-center text-xs font-semibold text-chart-2">
                    Comanda despachada
                </div>
            {/if}
        </div>
    </div>
</article>
