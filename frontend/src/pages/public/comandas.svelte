<script>
    // /comandas: tablero de comandas por estación (HU-06) para cocinero y administrador, columnas
    // Recibido → En preparación → Listo. Mesero y cajero no llegan aquí: el servidor los manda a /mesero/pedidos (HU-09).
    // Cualquier otro usuario (cliente) ve un aviso con enlace a Mis pedidos.
    import { onMount } from 'svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import PageTitle from '@/components/page-title.svelte';
    import ComandaCard from '@/components/ui/comanda-card.svelte';
    import { api, session, connectLive, states, stations } from '@/lib/trackeat';
    import CookingPot from '@lucide/svelte/icons/cooking-pot';
    import ChartColumn from '@lucide/svelte/icons/chart-column';

    let user = $state(null);
    let motivos = $state([]);
    let error = $state('');
    let busy = $state(null);
    let connected = $state(false);
    let connectionLabel = $state('Conectando…');
    let cargado = $state(false);
    // Tablero (cocina y administrador)
    let comandas = $state([]);
    let estacionesVisibles = $state(null);
    let filtro = $state('');
    let mostrarEntregados = $state(false);
    let ahora = $state(Date.now());

    const tablero = $derived(['cocinero', 'administrador'].includes(user?.rol));
    const columnas = $derived(['recibido', 'preparacion', 'listo', ...(mostrarEntregados ? ['entregado'] : [])]);
    // Hasta que responde el servidor no se sabe qué estación le toca: no se muestra una equivocada.
    const etiquetaEstacion = $derived(
        !cargado ? '…' : estacionesVisibles === null ? 'Todas las estaciones' : estacionesVisibles.map((e) => stations[e] ?? e).join(', '),
    );

    let loading = false;
    let again = false;
    async function refresh() {
        if (loading) {
            again = true;
            return;
        }
        loading = true;
        try {
            const query = new URLSearchParams({ ...(filtro ? { estacion: filtro } : {}), ...(mostrarEntregados ? { entregados: '1' } : {}) });
            const data = await api(`comandas${query.size ? `?${query}` : ''}`);
            comandas = data.comandas;
            estacionesVisibles = data.estaciones;
            error = '';
            cargado = true;
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

    // El filtro y la casilla viven en la dirección: se conservan al recargar o compartir el enlace.
    function aplicarFiltros() {
        const query = new URLSearchParams({ ...(filtro ? { estacion: filtro } : {}), ...(mostrarEntregados ? { entregados: '1' } : {}) });
        history.replaceState(history.state, '', `/comandas${query.size ? `?${query}` : ''}`);
        void refresh();
    }

    onMount(() => {
        let stop = () => {};
        let disposed = false;
        const params = new URLSearchParams(location.search);
        filtro = ['cocina', 'barra'].includes(params.get('estacion')) ? params.get('estacion') : '';
        mostrarEntregados = params.get('entregados') === '1';
        // Reloj para "hace N min" sin pedir nada al servidor.
        const reloj = setInterval(() => (ahora = Date.now()), 30000);
        session()
            .then(async (data) => {
                if (disposed) return;
                user = data.user;
                motivos = data.motivos_cancelacion ?? [];
                if (!user) {
                    error = 'Inicia sesión para consultar las comandas.';
                    return;
                }
                if (!tablero) return;
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
            clearInterval(reloj);
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
        <div class="flex flex-wrap items-end justify-between gap-3 border-b border-border pb-5">
            <div>
                <h1 class="flex items-center gap-2 text-2xl font-bold"><CookingPot class="size-6 text-primary" />Comandas</h1>
                {#if tablero}<p class="mt-1 text-sm text-muted-foreground" data-testid="estacion-visible">
                        Estación: <strong class="text-foreground">{etiquetaEstacion}</strong>
                    </p>{/if}
            </div>
            {#if tablero}
                <div class="flex flex-wrap items-end gap-3">
                    {#if user?.rol === 'administrador'}<label class="text-sm"
                            >Estación<select
                                aria-label="Filtrar por estación"
                                bind:value={filtro}
                                onchange={aplicarFiltros}
                                class="mt-1 block rounded-lg border border-input bg-background p-2"
                                ><option value="">Todas</option><option value="cocina">Cocina</option><option value="barra">Barra</option></select
                            ></label
                        >{/if}
                    <label class="flex items-center gap-2 pb-2 text-sm"
                        ><input type="checkbox" bind:checked={mostrarEntregados} onchange={aplicarFiltros} class="size-4" />Mostrar entregados</label
                    >
                </div>
            {/if}
            {#if tablero}<a href="/comandas/desglose" class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground"
                    ><ChartColumn class="mr-1 inline size-4" />Ver desglose</a
                >{/if}
        </div>

        {#if error}<p role="alert" class="text-sm text-destructive">{error}</p>{/if}

        {#if tablero}
            <div class="grid gap-4 {mostrarEntregados ? 'lg:grid-cols-4' : 'lg:grid-cols-3'}">
                {#each columnas as columna (columna)}
                    {@const lista = comandas.filter((c) => c.estado === columna)}
                    <section aria-label={`Columna ${states[columna]}`} class="space-y-3 rounded-2xl bg-muted/50 p-3">
                        <h2 class="flex items-center justify-between px-1 text-sm font-bold">
                            {columna === 'entregado' ? 'Entregados hoy' : states[columna]}<span
                                class="rounded-full bg-background px-2 py-0.5 text-xs font-semibold">{lista.length}</span
                            >
                        </h2>
                        {#each lista as comanda (comanda.id)}
                            <ComandaCard {comanda} {user} {motivos} {ahora} disabled={busy !== null} onadvance={advance} oncancelled={refresh} />
                        {:else}
                            <p class="px-1 py-6 text-center text-xs text-muted-foreground">{cargado ? 'Sin comandas' : 'Cargando…'}</p>
                        {/each}
                    </section>
                {/each}
            </div>
        {:else if user}
            <div class="space-y-3 rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                <p>Esta pantalla es para el personal de cocina.</p>
                <a href="/mis-pedidos" class="inline-block rounded-lg bg-primary px-4 py-2 font-semibold text-primary-foreground">Ver mis pedidos</a>
            </div>
        {/if}
    </div>
</AppLayout>
