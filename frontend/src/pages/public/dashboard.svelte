<script>
    import AppLayout from '@/layouts/AppLayout.svelte';
    import PageTitle from '@/components/page-title.svelte';

    let {
        title = 'Dashboard',
        description = 'Visión general de pedidos y estado de cocina.',
    } = $props();
</script>

<PageTitle title="{title} — TrackEat" />

<AppLayout>
    <div class="space-y-6">
        <!-- Encabezado del Módulo -->
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between border-b border-gray-200/80 pb-5">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900">{title}</h1>
                <p class="mt-1 text-sm text-gray-500">{description}</p>
            </div>
            <div class="flex items-center gap-2 mt-2 sm:mt-0">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-orange-50 px-3 py-1 text-xs font-semibold text-orange-700 border border-orange-200/70">
                    <span class="h-2 w-2 rounded-full bg-orange-600 animate-pulse"></span>
                    Módulo Activo
                </span>
            </div>
        </div>

        <!-- Tarjeta de Contenido / Próximo diseño -->
        <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center shadow-xs">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-orange-50 text-orange-600 ring-8 ring-orange-50/50 mb-4">
                <span class="text-2xl font-bold">✨</span>
            </div>
            <h2 class="text-lg font-semibold text-gray-900 mb-1">Módulo de {title}</h2>
            <p class="mx-auto max-w-md text-sm text-gray-500 leading-relaxed">
                Este módulo está integrado en la barra lateral de navegación y listo para que implementes la vista y componentes específicos.
            </p>
        </div>
    </div>
</AppLayout>