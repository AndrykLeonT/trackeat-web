<script>
    // HU-09 · Pedidos por entregar (mesero, cajero y administrador), a nivel de pedido.
    // Por defecto solo comer aquí; "Todos" incluye para llevar. Los listos primero. La entrega es todo o nada.
    import { onMount } from 'svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import PageTitle from '@/components/page-title.svelte';
    import PedidoCard from '@/components/ui/pedido-card.svelte';
    import { api, session, connectLive } from '@/lib/trackeat';
    import { formatoFolio } from '@/lib/folio';
    import HandPlatter from '@lucide/svelte/icons/hand-platter';

    let user = $state(null);
    let motivos = $state([]);
    let pedidos = $state([]);
    let entrega = $state('comer_aqui');
    let error = $state('');
    let aviso = $state('');
    let busy = $state(null);
    let cargado = $state(false);
    let connected = $state(false);
    let connectionLabel = $state('Conectando…');
    let ahora = $state(Date.now());
    const listos = $derived(pedidos.filter((p) => p.estado === 'listo').length);

    let loading = false;
    let again = false;
    async function refresh() {
        if (loading) {
            again = true;
            return;
        }
        loading = true;
        try {
            pedidos = (await api(`mesero/pedidos${entrega === 'todos' ? '?entrega=todos' : ''}`)).pedidos;
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

    // El filtro vive en la dirección: se conserva al recargar.
    function cambiarEntrega(valor) {
        entrega = valor;
        history.replaceState(history.state, '', valor === 'todos' ? '/mesero/pedidos?entrega=todos' : '/mesero/pedidos');
        void refresh();
    }

    async function entregar(id) {
        if (busy !== null) return;
        busy = id;
        error = '';
        aviso = '';
        try {
            const pedido = await api(`pedidos/${id}/entregar`, { method: 'POST' });
            aviso = `Pedido ${formatoFolio(pedido.numero_pedido)} entregado.`;
        } catch (e) {
            error = e.message;
        } finally {
            busy = null;
            await refresh();
        }
    }

    onMount(() => {
        let stop = () => {};
        let disposed = false;
        entrega = new URLSearchParams(location.search).get('entrega') === 'todos' ? 'todos' : 'comer_aqui';
        const reloj = setInterval(() => (ahora = Date.now()), 30000);
        session()
            .then(async (data) => {
                if (disposed) return;
                user = data.user;
                motivos = data.motivos_cancelacion ?? [];
                stop = connectLive(data, refresh, (label, value) => {
                    connectionLabel = label;
                    connected = value;
                });
                await refresh();
            })
            .catch((e) => (error = e.message));
        return () => {
            disposed = true;
            clearInterval(reloj);
            stop();
        };
    });
</script>

<PageTitle title="Por entregar — TrackEat" />
<AppLayout {connected} {connectionLabel} headerTitle="Por entregar">
    <div class="max-w-4xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-3 border-b border-border pb-5">
            <div>
                <h1 class="flex items-center gap-2 text-2xl font-bold"><HandPlatter class="size-6 text-primary" />Pedidos por entregar</h1>
                <p class="mt-1 text-sm text-muted-foreground" data-testid="resumen">
                    {cargado ? `${pedidos.length} en curso · ${listos} listo${listos === 1 ? '' : 's'} para entregar` : 'Cargando…'}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div role="group" aria-label="Tipo de entrega" class="inline-flex rounded-lg border border-border p-0.5 text-sm">
                    {#each [['comer_aqui', 'Comer aquí'], ['todos', 'Todos']] as [valor, nombre] (valor)}
                        <button
                            type="button"
                            aria-pressed={entrega === valor}
                            onclick={() => cambiarEntrega(valor)}
                            class="rounded-md px-3 py-1.5 font-semibold {entrega === valor ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'}"
                            >{nombre}</button
                        >
                    {/each}
                </div>
                <a href="/menu/" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold">Capturar pedido</a>
            </div>
        </div>

        {#if aviso}<p role="status" class="rounded-xl border border-emerald-600/30 bg-emerald-600/10 p-3 text-sm text-emerald-800 dark:text-emerald-300">
                {aviso}
            </p>{/if}
        {#if error}<p role="alert" class="rounded-xl border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">{error}</p>{/if}

        <div class="space-y-3">
            {#each pedidos as pedido (pedido.id)}
                <PedidoCard {pedido} {user} {motivos} {ahora} disabled={busy !== null} onentregar={entregar} oncancelled={refresh} />
            {:else}
                <p class="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                    {cargado ? (entrega === 'todos' ? 'No hay pedidos en curso.' : 'No hay pedidos para comer aquí en curso.') : 'Cargando…'}
                </p>
            {/each}
        </div>
    </div>
</AppLayout>
