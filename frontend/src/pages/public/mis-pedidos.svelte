<script>
    // HU-04 · Mis pedidos por día. El servidor decide el día de cada pedido (corte en que se cobró o, sin pago,
    // el día en que se creó) y solo devuelve los del usuario; aquí solo se elige el día y se pagina.
    import { Link, router } from '@inertiajs/svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import PageTitle from '@/components/page-title.svelte';
    import { states } from '@/lib/trackeat';
    import { formatoFolio } from '@/lib/folio';
    import { dinero, fecha } from '@/lib/caja';
    import ClipboardList from '@lucide/svelte/icons/clipboard-list';

    let { pedidos, dia, hoy } = $props();
    const todos = $derived(dia === 'todos');
    const etiquetaDia = $derived(
        todos
            ? 'Todos tus pedidos'
            : dia === hoy
              ? 'Hoy'
              : new Date(`${dia}T12:00:00`).toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }),
    );
    const estilos = {
        entregado: 'border-emerald-600/30 bg-emerald-600/10 text-emerald-700 dark:text-emerald-300',
        cancelado: 'border-destructive/30 bg-destructive/10 text-destructive',
    };

    function ir(nuevoDia) {
        router.get('/mis-pedidos', nuevoDia === hoy ? {} : { dia: nuevoDia }, { preserveScroll: true });
    }
</script>

<PageTitle title="Mis pedidos — TrackEat" />
<AppLayout headerTitle="Mis pedidos">
    <div class="max-w-4xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4 border-b border-border pb-5">
            <div>
                <h1 class="flex items-center gap-2 text-2xl font-bold"><ClipboardList class="size-6 text-primary" />Mis pedidos</h1>
                <p class="mt-1 text-sm capitalize text-muted-foreground" data-testid="dia-actual">{etiquetaDia}</p>
            </div>
            <div class="flex flex-wrap items-end gap-2">
                <label class="text-sm"
                    >Día<input
                        type="date"
                        aria-label="Día"
                        value={todos ? '' : dia}
                        max={hoy}
                        onchange={(e) => e.currentTarget.value && ir(e.currentTarget.value)}
                        class="mt-1 block rounded-lg border border-input bg-background p-2"
                    /></label
                >
                <button
                    type="button"
                    onclick={() => ir(hoy)}
                    disabled={dia === hoy}
                    class="rounded-lg border border-border px-3 py-2 text-sm font-semibold disabled:opacity-50">Hoy</button
                >
                <button
                    type="button"
                    onclick={() => ir('todos')}
                    disabled={todos}
                    class="rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-primary-foreground disabled:opacity-50">Ver todos</button
                >
            </div>
        </div>

        {#if pedidos.data.length === 0}
            <div class="space-y-3 rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                <p>{todos ? 'Todavía no tienes pedidos.' : 'No tienes pedidos este día.'}</p>
                <a href="/menu/" class="inline-block rounded-lg bg-primary px-4 py-2 font-semibold text-primary-foreground">Ir al menú</a>
            </div>
        {:else}
            <ul class="divide-y divide-border overflow-hidden rounded-xl border border-border bg-card" aria-label="Pedidos">
                {#each pedidos.data as pedido (pedido.id)}
                    <li>
                        <Link
                            href={`/pedidos/${pedido.id}`}
                            class="grid grid-cols-[auto_1fr_auto] items-center gap-x-4 gap-y-1 px-4 py-3 hover:bg-muted/40 sm:grid-cols-[6rem_1fr_auto_7rem]"
                        >
                            <span class="font-bold">{formatoFolio(pedido.numero_pedido)}</span>
                            <span class="text-sm text-muted-foreground"
                                >{fecha(pedido.created_at)} · {pedido.tipo_entrega === 'comer_aqui' ? `Mesa ${pedido.num_mesa}` : 'Para llevar'}</span
                            >
                            <span
                                class="justify-self-start rounded-full border px-2 py-0.5 text-xs font-semibold sm:justify-self-end {estilos[pedido.estado] ??
                                    'border-primary/20 bg-primary/10 text-primary'}">{states[pedido.estado] ?? pedido.estado}</span
                            >
                            <span class="text-right font-semibold tabular-nums">{dinero(pedido.total)}</span>
                        </Link>
                    </li>
                {/each}
            </ul>
            {#if pedidos.last_page > 1}
                <nav aria-label="Paginación" class="flex items-center justify-between text-sm">
                    {#if pedidos.prev_page_url}<Link href={pedidos.prev_page_url} preserveScroll class="font-semibold text-primary">← Anterior</Link
                        >{:else}<span></span>{/if}
                    <span class="text-muted-foreground">Página {pedidos.current_page} de {pedidos.last_page}</span>
                    {#if pedidos.next_page_url}<Link href={pedidos.next_page_url} preserveScroll class="font-semibold text-primary">Siguiente →</Link
                        >{:else}<span></span>{/if}
                </nav>
            {/if}
        {/if}
    </div>
</AppLayout>
