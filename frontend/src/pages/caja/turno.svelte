<script>
    import { onMount } from 'svelte';
    import { Link, router } from '@inertiajs/svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import PageTitle from '@/components/page-title.svelte';
    import Aviso from '@/components/caja/aviso.svelte';
    import Totales from '@/components/caja/totales.svelte';
    import PagosTabla from '@/components/caja/pagos-tabla.svelte';
    import Button from '@/components/ui/button.svelte';
    import { buttonVariants } from '@/components/ui/button.svelte';
    import { fecha, recargarCada } from '@/lib/caja';

    let { corte, resumen, pagos = [] } = $props();

    // Totales en vivo mientras el turno está abierto.
    onMount(() => recargarCada(['resumen', 'pagos']));

    // Confirmación dentro de la página: { id, accion } del pago que se está por marcar.
    let confirmando = $state(null);
    let enviando = $state(false);

    const textos = {
        eliminar: {
            boton: 'Eliminar',
            pregunta: 'Error de captura: el pago deja de contar y se podrá purgar después.',
            ruta: 'caja.pagos.eliminar',
        },
        reembolsar: {
            boton: 'Reembolsar',
            pregunta: 'Se devolvió el dinero al cliente: el pago se conserva marcado como reembolsado.',
            ruta: 'caja.pagos.reembolsar',
        },
    };

    function confirmar() {
        const { id, accion } = confirmando;
        enviando = true;
        router.post(
            route(textos[accion].ruta, id),
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    enviando = false;
                    confirmando = null;
                },
            },
        );
    }
</script>

<PageTitle title="Turno actual" />
<AppLayout headerTitle="Caja">
    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4 border-b border-border pb-5">
            <div>
                <h1 class="text-2xl font-bold">Turno actual</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Abierto desde {fecha(corte.fecha_apertura)}. Los totales se actualizan cada 5 segundos.
                </p>
            </div>
            <div class="flex gap-2">
                <Link href={route('caja.index')} class={buttonVariants({ variant: 'outline' })}>Por cobrar</Link>
                <Link href={route('caja.cerrar')} class={buttonVariants()}>Cerrar corte</Link>
            </div>
        </div>

        <Aviso campos={['pago', 'corte']} />

        <Totales {resumen} inicial={corte.total_caja_inicial} />

        {#if confirmando}
            <div
                role="alertdialog"
                aria-labelledby="confirmar-titulo"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4"
            >
                <p id="confirmar-titulo" class="text-sm">
                    <b>{textos[confirmando.accion].boton} el pago de {confirmando.pedido}.</b>
                    {textos[confirmando.accion].pregunta}
                </p>
                <div class="flex gap-2">
                    <Button variant="outline" size="sm" onclick={() => (confirmando = null)} disabled={enviando}>Cancelar</Button>
                    <Button variant="destructive" size="sm" onclick={confirmar} disabled={enviando}>Confirmar</Button>
                </div>
            </div>
        {/if}

        <PagosTabla {pagos}>
            {#snippet acciones(pago)}
                {#if pago.estado === 'activo'}
                    <div class="flex justify-end gap-2">
                        <Button variant="ghost" size="sm" onclick={() => (confirmando = { id: pago.id, accion: 'eliminar', pedido: pago.pedido })}
                            >Eliminar</Button
                        >
                        <Button variant="outline" size="sm" onclick={() => (confirmando = { id: pago.id, accion: 'reembolsar', pedido: pago.pedido })}
                            >Reembolsar</Button
                        >
                    </div>
                {/if}
            {/snippet}
        </PagosTabla>
    </div>
</AppLayout>
