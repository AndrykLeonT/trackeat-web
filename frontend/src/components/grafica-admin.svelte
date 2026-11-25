<script>
    import { BarController, BarElement, CategoryScale, Chart, LinearScale, Tooltip } from 'chart.js';
    import { onMount } from 'svelte';

    Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);

    let { id, title, rows, unit, color, horizontal = false, format = (value) => value } = $props();
    let canvas = $state();

    onMount(() => {
        if (!canvas) return;
        const chart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: rows.map((row) => row.label),
                datasets: [{ label: unit, data: rows.map((row) => row.value), backgroundColor: color, borderRadius: 4 }],
            },
            options: {
                indexAxis: horizontal ? 'y' : 'x',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    [horizontal ? 'x' : 'y']: { beginAtZero: true },
                },
                plugins: { tooltip: { callbacks: { label: (point) => format(point.raw) } } },
            },
        });

        return () => chart.destroy();
    });
</script>

<section aria-labelledby={id} class="min-w-0 space-y-4 rounded-2xl border border-border bg-card p-5">
    <h2 {id} class="font-semibold">{title}</h2>
    {#if rows.length}
        <div class="relative h-64">
            <canvas bind:this={canvas} role="img" aria-labelledby={id}></canvas>
        </div>
        <details class="text-sm">
            <summary class="cursor-pointer text-muted-foreground">Ver datos</summary>
            <table class="mt-3 w-full text-left">
                <thead><tr><th scope="col">Categoría</th><th scope="col" class="text-right">{unit}</th></tr></thead>
                <tbody class="divide-y divide-border">
                    {#each rows as row}
                        <tr><td class="py-1.5">{row.label}</td><td class="py-1.5 text-right">{format(row.value)}</td></tr>
                    {/each}
                </tbody>
            </table>
        </details>
    {:else}
        <p class="text-sm text-muted-foreground">Sin datos en este periodo.</p>
    {/if}
</section>
