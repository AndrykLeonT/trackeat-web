<script>
    import { states } from '@/lib/trackeat';
    let { totals = {}, orders = [], showOrders = false, demoRoom = true } = $props();
    const entries = $derived(Object.entries(states).map(([state, label]) => ({ state, label, total: Number(totals[state] || 0) })));
    const max = $derived(Math.max(1, ...entries.map((entry) => entry.total)));
</script>

<section class="rounded-2xl border border-border bg-card p-5 shadow-xs" aria-labelledby="orders-chart-title">
    <h2 id="orders-chart-title" class="text-lg font-bold">¿En qué estado están los pedidos?</h2>
    <p class="mt-1 text-sm text-muted-foreground">{entries.reduce((sum, entry) => sum + entry.total, 0)} pedidos guardados en el servidor.</p>
    <svg viewBox="0 0 500 225" role="img" aria-labelledby="orders-chart-description" class="mt-4 w-full max-w-2xl">
        <title id="orders-chart-description">Cantidad de pedidos por estado; cifras disponibles también en la tabla.</title>
        {#each entries as entry, i (entry.state)}
            <text x="0" y={27 + i * 42} fill="currentColor" font-size="13">{entry.label}</text>
            <rect x="140" y={10 + i * 42} width={(entry.total / max) * 310} height="25" rx="5" fill="var(--primary)" />
            <text x="465" y={27 + i * 42} fill="currentColor" font-size="14">{entry.total}</text>
        {/each}
    </svg>
    <table class="mt-3 w-full text-sm">
        <caption class="sr-only">Pedidos por estado</caption>
        <thead><tr class="border-b border-border text-left"><th scope="col" class="py-2">Estado</th><th scope="col">Pedidos</th></tr></thead>
        <tbody
            >{#each entries as entry (entry.state)}<tr><th scope="row" class="py-1 text-left font-normal">{entry.label}</th><td>{entry.total}</td></tr
                >{/each}</tbody
        >
    </table>
</section>
{#if showOrders}
    <section class="rounded-2xl border border-border bg-card p-5">
        <h2 class="text-lg font-bold">Tablero del salón</h2>
        <p class="text-sm text-muted-foreground">
            {demoRoom ? 'Los pedidos de esta demostración' : 'Los pedidos del salón'} aparecen aquí sin recargar.
        </p>
        <ul class="mt-3 space-y-2" aria-live="polite">
            {#each orders.slice(0, 10) as order (order.id)}
                <li class="flex flex-wrap justify-between gap-2 border-b border-border py-2 text-sm">
                    <span class="break-all">{order.numero_pedido}</span><strong>{states[order.estado]}</strong>
                </li>
            {:else}<li class="text-sm text-muted-foreground">Todavía no hay pedidos.</li>{/each}
        </ul>
    </section>
{/if}
