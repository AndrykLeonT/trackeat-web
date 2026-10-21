<script>
    import { onMount } from 'svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import PageTitle from '@/components/page-title.svelte';
    import ComandaCard from '@/components/ui/comanda-card.svelte';
    import { api, session, connectLive, staffRoles } from '@/lib/trackeat';
    import CookingPot from '@lucide/svelte/icons/cooking-pot';
    import ChartColumn from '@lucide/svelte/icons/chart-column';
    let orders = $state([]);
    let user = $state(null);
    let error = $state('');
    let busy = $state(null);
    let connected = $state(false);
    let connectionLabel = $state('Conectando…');
    let loading = false;
    let again = false;
    async function refresh() {
        if (loading) {
            again = true;
            return;
        }
        loading = true;
        try {
            orders = await api('pedidos');
            error = '';
        } catch (e) {
            error = e.message;
        } finally {
            loading = false;
            if (again) {
                again = false;
                void refresh();
            }
        }
    }
    onMount(() => {
        let stop = () => {};
        let disposed = false;
        session()
            .then(async (data) => {
                if (disposed) return;
                user = data.user;
                if (!user) {
                    error = 'Inicia sesión para consultar tus comandas.';
                    return;
                }
                stop = connectLive(data, refresh, (label, value) => {
                    connectionLabel = label;
                    connected = value;
                });
                await refresh();
            })
            .catch((e) => {
                error = e.message;
            });
        return () => {
            disposed = true;
            stop();
        };
    });
    async function advance(id, station, state) {
        if (busy !== null) return;
        busy = id;
        error = '';
        try {
            await api(`pedidos/${id}/estacion`, { method: 'PATCH', body: { estacion: station, estado: state } });
            await refresh();
        } catch (e) {
            error = e.message;
            await refresh();
            error = e.message;
        } finally {
            busy = null;
        }
    }
</script>

<PageTitle title="Comandas — TrackEat" />
<AppLayout {connected} {connectionLabel} headerTitle="Comandas">
    <div class="space-y-6">
        <div class="flex flex-wrap justify-between gap-3 border-b border-border pb-5">
            <div>
                <h1 class="flex items-center gap-2 text-2xl font-bold">
                    <CookingPot class="size-6 text-primary" />{staffRoles.includes(user?.rol) ? 'Comandas en Cocina' : 'Comandas'}
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {staffRoles.includes(user?.rol)
                        ? 'Pedidos guardados y preparación por estación.'
                        : 'Consulta la preparación de tus pedidos en tiempo real.'}
                </p>
            </div>
            <a href="/menu/" class="self-start rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground">Ver menú</a>
        </div>
        <section
            aria-labelledby="orders-breakdown-title"
            class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-border bg-card p-5 shadow-xs"
        >
            <div>
                <h2 id="orders-breakdown-title" class="flex items-center gap-2 text-lg font-bold">
                    <ChartColumn class="size-5 text-primary" />Información y gráfica de pedidos
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Consulta los pedidos, sus estados y el desglose de comandas en una pantalla dedicada.
                </p>
            </div>
            <a href="/comandas/desglose" class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground"
                >Ver desglose de comandas</a
            >
        </section>
        {#if error}<p role="alert" class="text-sm text-destructive">{error}</p>{/if}
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            {#each orders.filter((order) => !['entregado', 'cancelado'].includes(order.estado)) as order (order.id)}<ComandaCard
                    {order}
                    role={user?.rol}
                    disabled={busy !== null}
                    onadvance={advance}
                />{:else}<p class="text-sm text-muted-foreground">No hay comandas activas.</p>{/each}
        </div>
    </div>
</AppLayout>
