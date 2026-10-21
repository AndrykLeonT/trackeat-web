<script>
    import { onMount } from 'svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import PageTitle from '@/components/page-title.svelte';
    import OrderChart from '@/components/OrderChart.svelte';
    import { api, session, connectLive } from '@/lib/trackeat';
    let { title = 'Dashboard', description = 'Pedidos y estado de cocina en tiempo real.' } = $props();
    let board = $state({ estados: {}, pedidos: [] });
    let error = $state('');
    let loaded = $state(false);
    let connected = $state(false);
    let connectionLabel = $state('Conectando…');
    onMount(() => {
        let stop = () => {};
        let disposed = false;
        async function refresh() {
            try {
                board = await api('tablero');
                loaded = true;
                error = '';
            } catch (e) {
                error = e.message;
            }
        }
        session()
            .then(async (data) => {
                if (disposed) return;
                if (!data.user) {
                    error = 'Inicia sesión como personal para consultar el panel.';
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
</script>

<PageTitle title="{title} — TrackEat" />
<AppLayout {connected} {connectionLabel} headerTitle={title}>
    <div class="space-y-6">
        <div class="border-b border-border pb-5">
            <h1 class="text-2xl font-bold">{title}</h1>
            <p class="mt-1 text-sm text-muted-foreground">{description}</p>
        </div>
        {#if error}<p role="alert" class="text-sm text-destructive">
                {error} <a href="/login" class="underline">Iniciar sesión</a>
            </p>{:else if loaded}<OrderChart totals={board.estados} />{:else}<p role="status" class="text-sm text-muted-foreground">
                Cargando pedidos…
            </p>{/if}
    </div>
</AppLayout>
