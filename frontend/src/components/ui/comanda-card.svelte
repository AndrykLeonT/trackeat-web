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
            badgeClass: 'bg-amber-50 text-amber-700 border-amber-200/80',
            dotClass: 'bg-amber-500',
            borderAccent: 'border-t-amber-500',
        },
        en_preparacion: {
            label: 'En Preparación',
            badgeClass: 'bg-orange-50 text-orange-700 border-orange-200/80',
            dotClass: 'bg-orange-500 animate-pulse',
            borderAccent: 'border-t-orange-500',
        },
        listo: {
            label: 'Listo para Servir',
            badgeClass: 'bg-emerald-50 text-emerald-700 border-emerald-200/80',
            dotClass: 'bg-emerald-500',
            borderAccent: 'border-t-emerald-500',
        },
    };

    const currentStatus = $derived(
        statusConfig[order.status] || {
            label: order.status,
            badgeClass: 'bg-gray-50 text-gray-700 border-gray-200',
            dotClass: 'bg-gray-400',
            borderAccent: 'border-t-gray-400',
        }
    );

    const isOnline = $derived(order.origin === 'online');
</script>

<article
    class="flex flex-col justify-between overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-xs transition-all duration-200 hover:shadow-md hover:border-orange-200 border-t-4 {currentStatus.borderAccent}"
>
    <!-- Encabezado de la comanda -->
    <div>
        <div class="flex items-start justify-between gap-2 border-b border-gray-100 pb-3">
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">
                        {order.code || `#ORD-${order.id}`}
                    </span>

                    {#if isOnline}
                        <span class="inline-flex items-center gap-1 rounded-md bg-blue-50 px-2 py-0.5 text-xs font-bold text-blue-700 border border-blue-200/60">
                            <Globe class="size-3" />
                            En Línea
                        </span>
                    {:else}
                        <span class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2 py-0.5 text-xs font-bold text-gray-800">
                            <Store class="size-3 text-gray-500" />
                            {order.table}
                        </span>
                    {/if}
                </div>

                <div class="mt-1 flex items-center gap-1.5 text-xs text-gray-500">
                    {#if isOnline}
                        <User class="size-3 text-blue-500" />
                        <span>Cliente: <strong class="text-gray-800">{order.customer || 'Usuario Registrado'}</strong> ({order.deliveryType || 'Recoger'})</span>
                    {:else}
                        <span>Mesero: <strong class="text-gray-800">{order.waiter || 'General'}</strong></span>
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
            <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">
                Platillos de la orden:
            </span>
            <ul class="space-y-1.5 text-sm">
                {#each order.items as item}
                    <li class="flex items-start justify-between text-gray-700">
                        <div class="flex items-start gap-2">
                            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md bg-orange-100 text-xs font-bold text-orange-700">
                                {item.quantity}x
                            </span>
                            <div>
                                <span class="font-medium text-gray-900">{item.name}</span>
                                {#if item.notes}
                                    <p class="text-[11px] text-amber-600 font-medium italic">
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
    <div class="mt-5 border-t border-gray-100 pt-3.5">
        <div class="flex items-center justify-between text-xs text-gray-500 mb-3">
            <span class="inline-flex items-center gap-1 font-medium text-gray-600">
                <Clock class="size-3.5 text-orange-500" />
                {order.timeElapsed || 'Hace 5 min'}
            </span>
            <span class="text-[11px] text-gray-400">
                {order.items.reduce((acc, i) => acc + i.quantity, 0)} items
            </span>
        </div>

        <!-- Botones de acción rápida para cocina/mesero -->
        <div class="flex items-center gap-2">
            {#if order.status === 'pendiente'}
                <button
                    type="button"
                    class="w-full inline-flex items-center justify-center gap-1.5 rounded-xl bg-orange-600 px-3 py-2 text-xs font-semibold text-white shadow-xs hover:bg-orange-700 transition-colors"
                >
                    <ChefHat class="size-3.5" />
                    <span>Empezar a cocinar</span>
                </button>
            {:else if order.status === 'en_preparacion'}
                <button
                    type="button"
                    class="w-full inline-flex items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-3 py-2 text-xs font-semibold text-white shadow-xs hover:bg-emerald-700 transition-colors"
                >
                    <CheckCircle2 class="size-3.5" />
                    <span>Marcar como Listo</span>
                </button>
            {:else}
                <div class="w-full rounded-xl bg-emerald-50 border border-emerald-200/60 py-1.5 text-center text-xs font-semibold text-emerald-700">
                    Comanda despachada
                </div>
            {/if}
        </div>
    </div>
</article>
