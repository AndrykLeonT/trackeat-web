<script>
    import { Link } from '@inertiajs/svelte';
    import GraficaAdmin from '@/components/grafica-admin.svelte';
    import PageTitle from '@/components/page-title.svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';

    let { from, through, days, products, sales, stations, salesTotal } = $props();
    const number = new Intl.NumberFormat('es-MX');
    const currency = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });
    const date = new Intl.DateTimeFormat('es-MX', { day: 'numeric', month: 'short', timeZone: 'UTC' });
    const showDate = (value) => date.format(new Date(value));
    const orders = $derived(days.reduce((sum, day) => sum + day.value, 0));
    const shortDays = $derived(days.map((day) => ({ ...day, label: showDate(day.label) })));
</script>

<PageTitle title="Administración — TrackEat" />
<AppLayout headerTitle="Administración">
    <div class="space-y-6">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold">Panel de administración</h1>
                <p class="mt-1 text-sm text-muted-foreground">Últimos 30 días · {showDate(from)} al {showDate(through)}</p>
            </div>
            <Link href="/admin/usuarios" class="rounded-md border border-border px-4 py-2 text-sm font-medium hover:bg-muted">Gestionar usuarios</Link
            >
        </header>
        <dl class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-border bg-card p-5">
                <dt class="text-sm text-muted-foreground">Pedidos</dt>
                <dd class="mt-1 text-3xl font-bold">{number.format(orders)}</dd>
            </div>
            <div class="rounded-xl border border-border bg-card p-5">
                <dt class="text-sm text-muted-foreground">Ventas netas · MXN</dt>
                <dd class="mt-1 text-3xl font-bold">{currency.format(salesTotal)}</dd>
            </div>
        </dl>
        <div class="grid gap-5 lg:grid-cols-2">
            <GraficaAdmin
                id="daily-orders"
                title="Pedidos por día"
                rows={shortDays}
                unit="Pedidos"
                color="#b94e30"
                format={(value) => number.format(value)}
            />
            <GraficaAdmin
                id="top-products"
                title="Productos más pedidos"
                rows={products}
                unit="Unidades"
                color="#287866"
                horizontal
                format={(value) => number.format(value)}
            />
            <GraficaAdmin
                id="sales-by-method"
                title="Ventas por método de pago"
                rows={sales}
                unit="MXN"
                color="#315b99"
                format={(value) => currency.format(value)}
            />
            <GraficaAdmin
                id="station-times"
                title="Tiempo promedio de recibido a listo"
                rows={stations}
                unit="Minutos"
                color="#a66c21"
                format={(value) => `${number.format(value)} min`}
            />
        </div>
    </div>
</AppLayout>
