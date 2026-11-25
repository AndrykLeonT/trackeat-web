<script>
    import { untrack } from 'svelte';
    import { Link, useForm } from '@inertiajs/svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import PageTitle from '@/components/page-title.svelte';
    import Aviso from '@/components/caja/aviso.svelte';
    import Button from '@/components/ui/button.svelte';
    import InputDinero from '@/components/caja/input-dinero.svelte';
    import Label from '@/components/ui/label.svelte';
    import InputError from '@/components/input-error.svelte';
    import { centavos, deCentavos, dinero, entrega, estadosPedido, metodos } from '@/lib/caja';
    import { formatoFolio } from '@/lib/folio';

    let { pedido, pagos = [] } = $props();

    // Por defecto se cobra todo el saldo; para un pago dividido se baja el monto y se repite.
    let form = useForm({ metodo_pago: 'efectivo', monto: untrack(() => pedido.saldo), propina: '' });

    let saldoDespues = $derived.by(() => {
        const s = centavos(pedido.saldo);
        const m = centavos($form.monto);
        return s === null || m === null ? null : s - m;
    });

    const estadoPago = {
        pendiente: 'Sin pagos',
        parcial: 'Pago parcial',
        pagado: 'Pagado',
    };

    function submit(e) {
        e.preventDefault();
        $form.post(route('caja.cobrar.store', pedido.id), {
            preserveScroll: true,
            // Si el pedido sigue con saldo, el formulario se prepara para el siguiente pago.
            onSuccess: () => {
                $form.monto = pedido.saldo;
                $form.propina = '';
            },
        });
    }
</script>

<PageTitle title="Cobrar {formatoFolio(pedido.numero_pedido)}" />
<AppLayout headerTitle="Caja">
    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4 border-b border-border pb-5">
            <div>
                <h1 class="text-2xl font-bold">Cobrar pedido {formatoFolio(pedido.numero_pedido)}</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {pedido.cliente ?? 'Sin cliente'} · {entrega(pedido)} · {estadosPedido[pedido.estado] ?? pedido.estado} · {estadoPago[
                        pedido.estado_pago
                    ]}
                </p>
            </div>
            <Link href={route('caja.index')} class="text-sm text-primary underline-offset-4 hover:underline">← Pedidos por cobrar</Link>
        </div>

        <Aviso campos={['pago', 'pedido', 'corte']} />

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="space-y-4 rounded-xl border border-border bg-card p-5">
                <h2 class="font-semibold">Detalle</h2>
                <ul class="divide-y divide-border text-sm">
                    {#each pedido.items as item (item.id)}
                        <li class="flex justify-between gap-4 py-2">
                            <span
                                >{item.cantidad} × {item.producto}{#if item.notas}<span class="block text-xs text-muted-foreground">{item.notas}</span
                                    >{/if}</span
                            >
                            <span class="tabular-nums">{dinero(Number(item.precio_unitario) * item.cantidad)}</span>
                        </li>
                    {/each}
                </ul>
                <dl class="space-y-1 border-t border-border pt-3 text-sm">
                    <div class="flex justify-between">
                        <dt>Total</dt>
                        <dd class="tabular-nums">{dinero(pedido.total)}</dd>
                    </div>
                    <div class="flex justify-between text-base font-semibold">
                        <dt>Saldo pendiente</dt>
                        <dd class="tabular-nums">{dinero(pedido.saldo)}</dd>
                    </div>
                </dl>

                {#if pagos.length}
                    <h3 class="pt-2 text-sm font-semibold">Pagos registrados</h3>
                    <ul class="space-y-1 text-sm">
                        {#each pagos as pago (pago.id)}
                            <li class="flex justify-between gap-3 {pago.estado === 'activo' ? '' : 'text-muted-foreground line-through'}">
                                <span
                                    >{metodos[pago.metodo_pago]}{#if pago.estado !== 'activo'}
                                        ({pago.estado}){/if}</span
                                >
                                <span class="tabular-nums"
                                    >{dinero(pago.monto)}{#if Number(pago.propina) > 0}
                                        + propina {dinero(pago.propina)}{/if}</span
                                >
                            </li>
                        {/each}
                    </ul>
                {/if}
            </section>

            {#if Number(pedido.saldo) > 0}
                <form class="space-y-5 rounded-xl border border-border bg-card p-5" onsubmit={submit}>
                    <h2 class="font-semibold">Registrar pago</h2>

                    <fieldset class="grid gap-2">
                        <legend class="mb-2 text-sm font-medium">Método de pago</legend>
                        <div class="grid grid-cols-3 gap-2">
                            {#each Object.entries(metodos) as [valor, nombre] (valor)}
                                <label
                                    class="cursor-pointer rounded-lg border px-3 py-2.5 text-center text-sm font-medium transition-colors {$form.metodo_pago ===
                                    valor
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border hover:bg-accent'}"
                                >
                                    <input type="radio" name="metodo_pago" value={valor} bind:group={$form.metodo_pago} class="sr-only" />
                                    {nombre}
                                </label>
                            {/each}
                        </div>
                        <InputError message={$form.errors.metodo_pago} />
                    </fieldset>

                    <div class="grid gap-2 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="monto">Monto (MXN)</Label>
                            <InputDinero id="monto" inputmode="decimal" autocomplete="off" required bind:value={$form.monto} />
                            <InputError message={$form.errors.monto} />
                        </div>
                        <div class="grid gap-2">
                            <Label for="propina">Propina (aparte del monto)</Label>
                            <InputDinero id="propina" inputmode="decimal" autocomplete="off" placeholder="0.00" bind:value={$form.propina} />
                            <InputError message={$form.errors.propina} />
                        </div>
                    </div>

                    <p class="text-sm text-muted-foreground">
                        {#if saldoDespues === null}
                            Escribe el monto con hasta dos decimales, por ejemplo 60.50.
                        {:else if saldoDespues < 0}
                            <span class="text-destructive">El monto supera el saldo pendiente.</span>
                        {:else if saldoDespues === 0}
                            Con este pago el pedido queda <b>pagado</b>.
                        {:else}
                            Saldo restante después de este pago: <b>{dinero(deCentavos(saldoDespues))}</b>. Podrás registrar otro pago (pago
                            dividido).
                        {/if}
                    </p>

                    <Button type="submit" class="w-full" disabled={$form.processing}>Registrar pago</Button>
                </form>
            {:else}
                <p class="self-start rounded-xl border border-emerald-600/30 bg-emerald-600/10 p-5 text-sm">Este pedido ya está pagado.</p>
            {/if}
        </div>
    </div>
</AppLayout>
