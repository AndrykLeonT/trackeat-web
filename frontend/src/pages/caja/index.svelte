<script>
    import { onMount } from 'svelte';
    import { Link } from '@inertiajs/svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import PageTitle from '@/components/page-title.svelte';
    import Aviso from '@/components/caja/aviso.svelte';
    import { buttonVariants } from '@/components/ui/button.svelte';
    import { dinero, entrega, estadosPedido, fecha, recargarCada } from '@/lib/caja';
    import { formatoFolio } from '@/lib/folio';

    // `corte` es el turno abierto de este cajero (cada cajero tiene el suyo), o null.
    let { pedidos = [], corte = null } = $props();

    // Se refresca solo: los pedidos nuevos del módulo 2 aparecen sin recargar la página.
    onMount(() => recargarCada(['pedidos', 'corte']));

    let puedeCobrar = $derived(corte !== null);
</script>

<PageTitle title="Pedidos por cobrar" />
<AppLayout headerTitle="Caja">
    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4 border-b border-border pb-5">
            <div>
                <h1 class="text-2xl font-bold">Pedidos por cobrar</h1>
                <p class="mt-1 text-sm text-muted-foreground">Pedidos no cancelados con saldo pendiente. Se actualiza cada 5 segundos.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <!-- Módulo 2 · HU-08: el cajero captura pedidos por teléfono en el menú (quedan a su nombre). -->
                <a href="/menu/" class={buttonVariants()}>Capturar pedido</a>
                {#if puedeCobrar}
                    <Link href={route('caja.turno')} class={buttonVariants({ variant: 'outline' })}>Ver mi turno</Link>
                {/if}
            </div>
        </div>

        <Aviso campos={['corte']} />

        {#if !corte}
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card p-4">
                <p class="text-sm">No tienes un turno abierto. Abre uno para empezar a cobrar.</p>
                <Link href={route('caja.abrir')} class={buttonVariants()}>Abrir turno</Link>
            </div>
        {:else}
            <p class="text-sm text-muted-foreground">
                Tu turno está abierto desde {fecha(corte.fecha_apertura)} con un fondo de {dinero(corte.total_caja_inicial)}.
            </p>
        {/if}

        {#if pedidos.length === 0}
            <p class="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                No hay pedidos con saldo pendiente.
            </p>
        {:else}
            <div class="overflow-x-auto rounded-xl border border-border bg-card">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Pedido</th>
                            <th class="px-4 py-3">Cliente</th>
                            <th class="px-4 py-3">Entrega</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3 text-right">Pagado</th>
                            <th class="px-4 py-3 text-right">Saldo</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each pedidos as pedido (pedido.id)}
                            <tr class="border-t border-border">
                                <td class="px-4 py-3 font-medium">{formatoFolio(pedido.numero_pedido)}</td>
                                <td class="px-4 py-3">{pedido.cliente ?? '—'}</td>
                                <td class="px-4 py-3">{entrega(pedido)}</td>
                                <td class="px-4 py-3">{estadosPedido[pedido.estado] ?? pedido.estado}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{dinero(pedido.total)}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{dinero(pedido.pagado)}</td>
                                <td class="px-4 py-3 text-right font-semibold tabular-nums">{dinero(pedido.saldo)}</td>
                                <td class="px-4 py-3 text-right">
                                    {#if puedeCobrar}
                                        <Link href={route('caja.cobrar', pedido.id)} class={buttonVariants({ size: 'sm' })}>Cobrar</Link>
                                    {/if}
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        {/if}
    </div>
</AppLayout>
