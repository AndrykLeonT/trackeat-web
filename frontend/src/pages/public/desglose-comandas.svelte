<script>
    import { onMount } from 'svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import PageTitle from '@/components/page-title.svelte';
    import OrderChart from '@/components/OrderChart.svelte';
    import { api, session, connectLive, states, staffRoles } from '@/lib/trackeat';
    import ChartColumn from '@lucide/svelte/icons/chart-column';

    let orders = $state([]);
    let board = $state({ estados: {}, pedidos: [] });
    let user = $state(null);
    let loaded = $state(false);
    let error = $state('');
    let connected = $state(false);
    let connectionLabel = $state('Conectando…');
    const isStaff = $derived(staffRoles.includes(user?.rol));
    const showBoard = $derived(!!user?.demo_room || isStaff);

    onMount(() => {
        let stop = () => {};
        let disposed = false;
        let refreshing = false;
        let again = false;
        async function refresh() {
            if (refreshing) {
                again = true;
                return;
            }
            refreshing = true;
            try {
                const [nextOrders, nextBoard] = await Promise.all([api('pedidos'), showBoard ? api('tablero') : Promise.resolve(null)]);
                if (disposed) return;
                orders = nextOrders;
                board = nextBoard || {
                    estados: Object.fromEntries(Object.keys(states).map((state) => [state, orders.filter((order) => order.estado === state).length])),
                    pedidos: [],
                };
                loaded = true;
                error = '';
            } catch (e) {
                if (!disposed) error = e.message;
            } finally {
                refreshing = false;
                if (again && !disposed) {
                    again = false;
                    void refresh();
                }
            }
        }
        session()
            .then(async (data) => {
                if (disposed) return;
                user = data.user;
                if (!user) {
                    error = 'Inicia sesión para consultar el desglose de comandas.';
                    connectionLabel = 'Sesión no disponible';
                    return;
                }
                stop = connectLive(data, refresh, (label, value) => {
                    connectionLabel = label;
                    connected = value;
                });
                await refresh();
            })
            .catch((e) => {
                if (!disposed) error = e.message;
            });
        return () => {
            disposed = true;
            stop();
        };
    });
</script>

<PageTitle title="Desglose de comandas — TrackEat" />
<AppLayout {connected} {connectionLabel} headerTitle="Desglose de comandas">
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-5">
            <div>
                <h1 class="flex items-center gap-2 text-2xl font-bold"><ChartColumn class="size-6 shrink-0 text-primary" />Desglose de comandas</h1>
                <p class="mt-1 text-sm text-muted-foreground">Información de pedidos y gráfica por estado, actualizadas en tiempo real.</p>
            </div>
            <a href="/comandas" class="rounded-xl border border-input px-4 py-2 text-sm font-semibold">Volver a Comandas</a>
        </div>
        {#if error}
            <p role="alert" class="text-sm text-destructive">
                {error}
                {#if !user}<a href="/login" class="underline">Iniciar sesión</a>{/if}
            </p>
        {/if}
        {#if loaded}
            <section class="rounded-2xl border border-border bg-card p-5" aria-labelledby="my-orders">
                <h2 id="my-orders" class="text-lg font-bold">{isStaff ? 'Pedidos de comandas' : 'Mis pedidos'}</h2>
                <ul class="mt-3 space-y-3" aria-live="polite">
                    {#each orders as order (order.id)}
                        <li class="flex flex-wrap justify-between gap-2 border-b border-border py-2 text-sm">
                            <span class="break-all">{order.numero_pedido}</span>
                            <strong>{states[order.estado]} · ${Number(order.total).toFixed(2)} MXN</strong>
                        </li>
                    {:else}
                        <li class="text-sm text-muted-foreground">{isStaff ? 'Todavía no hay pedidos.' : 'Aún no has enviado pedidos.'}</li>
                    {/each}
                </ul>
            </section>
            <div class="grid gap-5 {showBoard ? 'lg:grid-cols-2' : ''}">
                <OrderChart totals={board.estados} orders={board.pedidos} showOrders={showBoard} demoRoom={!!user?.demo_room} />
            </div>
        {:else if !error}
            <p role="status" class="text-sm text-muted-foreground">Cargando pedidos…</p>
        {/if}
    </div>
</AppLayout>
