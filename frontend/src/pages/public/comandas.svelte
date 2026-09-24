<script>
    import AppLayout from '@/layouts/AppLayout.svelte';
    import PageTitle from '@/components/page-title.svelte';
    import ComandaCard from '@/components/ui/comanda-card.svelte';
    import Plus from '@lucide/svelte/icons/plus';
    import CookingPot from '@lucide/svelte/icons/cooking-pot';

    // Datos demostrativos de comandas (Presenciales y Pedidos en Línea)
    const comandas = [
        {
            id: 1,
            code: '#ORD-101',
            origin: 'local',
            table: 'Mesa 4',
            waiter: 'Carlos G.',
            status: 'pendiente',
            timeElapsed: 'Hace 3 min',
            items: [
                { name: 'Tacos al Pastor Especiales', quantity: 2, notes: 'Sin cebolla morada uno de ellos' },
                { name: 'Papas Rústicas Trufadas', quantity: 1 },
                { name: 'Agua Fresca de Horchata Artesanal', quantity: 2 }
            ]
        },
        {
            id: 2,
            code: '#ORD-WEB-042',
            origin: 'online',
            customer: 'Mariana Ríos',
            deliveryType: 'Para Llevar',
            status: 'en_preparacion',
            timeElapsed: 'Hace 8 min',
            items: [
                { name: 'Hamburguesa Artesanal TrackEat', quantity: 2, notes: 'Término medio' },
                { name: 'Papas Rústicas Trufadas', quantity: 2 }
            ]
        },
        {
            id: 3,
            code: '#ORD-WEB-043',
            origin: 'online',
            customer: 'Alejandro Peña',
            deliveryType: 'Domicilio',
            status: 'pendiente',
            timeElapsed: 'Hace 2 min',
            items: [
                { name: 'Burrito Norteño de Asada', quantity: 1, notes: 'Guacamole extra' },
                { name: 'Limonada Mineral con Hierbabuena', quantity: 1 }
            ]
        },
        {
            id: 4,
            code: '#ORD-103',
            origin: 'local',
            table: 'Barra 1',
            waiter: 'Roberto L.',
            status: 'listo',
            timeElapsed: 'Hace 15 min',
            items: [
                { name: 'Smash Burger Doble Queso', quantity: 1 },
                { name: 'Limonada Mineral con Hierbabuena', quantity: 1 }
            ]
        }
    ];
</script>

<PageTitle title="Comandas — TrackEat" />

<AppLayout>
    <div class="space-y-6">
        <!-- ==================== HEADER ==================== -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-gray-200/80 pb-5">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-orange-100 text-orange-600">
                        <CookingPot class="size-4" />
                    </span>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900">Comandas en Cocina</h1>
                </div>
                <p class="mt-1 text-sm text-gray-500">
                    Control de órdenes activas, tiempos y estado de preparación.
                </p>
            </div>

            <!-- Botón Nueva Comanda -->
            <div>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-orange-600 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-orange-700 transition-colors"
                >
                    <Plus class="size-4" />
                    <span>Nueva Comanda</span>
                </button>
            </div>
        </div>

        <!-- ==================== GRID DE COMANDAS RESPONSIVO ==================== -->
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            {#each comandas as order (order.id)}
                <ComandaCard {order} />
            {/each}
        </div>
    </div>
</AppLayout>
