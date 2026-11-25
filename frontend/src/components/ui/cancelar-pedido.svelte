<script>
    // HU-05 · Cancelar pedido. Botón + recuadro de confirmación dentro de la página (sin confirm() del navegador).
    // Cliente: su pedido, solo con todo en "Recibido", sin motivo. Mesero/cajero/admin: hasta antes de entregar,
    // con motivo obligatorio de la lista que manda /api/sesion. Ocultar el botón es comodidad: el servidor decide.
    import { api } from '@/lib/trackeat';
    import { formatoFolio } from '@/lib/folio';

    let { order, user, motivos = [], compact = false, oncancelled = () => {} } = $props();
    let abierto = $state(false);
    let motivo = $state('');
    let enviando = $state(false);
    let error = $state('');

    const personal = $derived(['mesero', 'cajero', 'administrador'].includes(user?.rol));
    const visible = $derived(
        !!order &&
            !['entregado', 'cancelado'].includes(order.estado) &&
            !order.estaciones?.some((e) => e.estado === 'entregado') &&
            (personal || (user?.rol === 'cliente' && order.id_usuario === user.id && order.estaciones?.every((e) => e.estado === 'recibido'))),
    );

    function cerrar() {
        abierto = false;
        motivo = '';
        error = '';
    }

    async function confirmar() {
        if (enviando || (personal && !motivo)) return;
        enviando = true;
        error = '';
        try {
            const actualizado = await api(`pedidos/${order.id}/cancelar`, { method: 'POST', body: personal ? { motivo } : {} });
            cerrar();
            oncancelled(actualizado);
        } catch (e) {
            error = e.message;
        } finally {
            enviando = false;
        }
    }
</script>

{#if visible}
    {#if !abierto}
        <button
            type="button"
            onclick={() => (abierto = true)}
            class={compact
                ? 'text-xs font-semibold text-destructive underline-offset-4 hover:underline'
                : 'rounded-lg border border-destructive/40 px-3 py-2 text-sm font-semibold text-destructive hover:bg-destructive/10'}
            >Cancelar pedido</button
        >
    {:else}
        <div
            role="alertdialog"
            aria-labelledby={`cancelar-${order.id}`}
            class="space-y-3 rounded-xl border border-destructive/40 bg-destructive/5 {compact ? 'p-3 text-xs' : 'p-4 text-sm'}"
        >
            <p id={`cancelar-${order.id}`} class="font-semibold">
                ¿Cancelar el pedido {formatoFolio(order.numero_pedido)}? Esta acción no se puede deshacer.
            </p>
            {#if personal}<label class="block"
                    >Motivo<select
                        aria-label="Motivo"
                        bind:value={motivo}
                        required
                        class="mt-1 block w-full rounded-lg border border-input bg-background p-2"
                        ><option value="" disabled>Elige un motivo…</option>{#each motivos as m (m)}<option value={m}>{m}</option>{/each}</select
                    ></label
                >{/if}
            {#if error}<p role="alert" class="text-destructive">{error}</p>{/if}
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    onclick={confirmar}
                    disabled={enviando || (personal && !motivo)}
                    class="rounded-lg bg-destructive px-3 py-2 font-semibold text-white disabled:opacity-50"
                    >{enviando ? 'Cancelando…' : 'Sí, cancelar'}</button
                >
                <button type="button" onclick={cerrar} disabled={enviando} class="rounded-lg border border-border px-3 py-2 font-semibold">No</button>
            </div>
        </div>
    {/if}
{/if}
