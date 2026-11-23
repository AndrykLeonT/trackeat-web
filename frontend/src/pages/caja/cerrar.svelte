<script>
    import { Link, useForm } from '@inertiajs/svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import PageTitle from '@/components/page-title.svelte';
    import Aviso from '@/components/caja/aviso.svelte';
    import Totales from '@/components/caja/totales.svelte';
    import Button from '@/components/ui/button.svelte';
    import Input from '@/components/ui/input.svelte';
    import Label from '@/components/ui/label.svelte';
    import InputError from '@/components/input-error.svelte';
    import { centavos, deCentavos, diferencia, dinero, fecha } from '@/lib/caja';

    let { corte, resumen } = $props();

    let form = useForm({ total_caja_final: '', total_tarjeta_real: '', total_transferencia_real: '' });
    let revisando = $state(false);

    // Las mismas fórmulas que CajaService::cerrar y ::resumen, en centavos:
    //   efectivo esperado = fondo inicial + cobros en efectivo; tarjeta y transferencia se comparan directo.
    //   Diferencia positiva = faltante; negativa = sobrante.
    let calculo = $derived.by(() => {
        const contado = centavos($form.total_caja_final);
        const tarjeta = centavos($form.total_tarjeta_real);
        const transferencia = centavos($form.total_transferencia_real);
        if (contado === null || tarjeta === null || transferencia === null) return null;
        const inicial = centavos(corte.total_caja_inicial);
        return {
            esperadoEfectivo: inicial + centavos(resumen.total_efectivo),
            efectivo: inicial + centavos(resumen.total_efectivo) - contado,
            tarjeta: centavos(resumen.total_tarjeta) - tarjeta,
            transferencia: centavos(resumen.total_transferencia) - transferencia,
            real: contado - inicial + tarjeta + transferencia,
        };
    });

    function revisar(e) {
        e.preventDefault();
        if (calculo) revisando = true;
    }

    function confirmar() {
        $form.post(route('caja.cerrar.store'), { onFinish: () => (revisando = false) });
    }
</script>

<PageTitle title="Cerrar corte" />
<AppLayout headerTitle="Caja">
    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4 border-b border-border pb-5">
            <div>
                <h1 class="text-2xl font-bold">Cerrar corte</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Turno abierto desde {fecha(corte.fecha_apertura)}. Al cerrar, los totales quedan congelados y ya no se pueden modificar.
                </p>
            </div>
            <Link href={route('caja.turno')} class="text-sm text-primary underline-offset-4 hover:underline">← Turno actual</Link>
        </div>

        <Aviso campos={['corte']} />

        <Totales {resumen} inicial={corte.total_caja_inicial} />

        <form class="grid gap-6 lg:grid-cols-2" onsubmit={revisar}>
            <section class="space-y-4 rounded-xl border border-border bg-card p-5">
                <h2 class="font-semibold">Lo que hay en caja</h2>
                <div class="grid gap-2">
                    <Label for="total_caja_final">Efectivo contado (incluye el fondo)</Label>
                    <Input
                        id="total_caja_final"
                        inputmode="decimal"
                        autocomplete="off"
                        required
                        bind:value={$form.total_caja_final}
                        disabled={revisando}
                    />
                    <InputError message={$form.errors.total_caja_final} />
                </div>
                <div class="grid gap-2">
                    <Label for="total_tarjeta_real">Suma de vouchers de tarjeta</Label>
                    <Input
                        id="total_tarjeta_real"
                        inputmode="decimal"
                        autocomplete="off"
                        required
                        bind:value={$form.total_tarjeta_real}
                        disabled={revisando}
                    />
                    <InputError message={$form.errors.total_tarjeta_real} />
                </div>
                <div class="grid gap-2">
                    <Label for="total_transferencia_real">Suma de comprobantes de transferencia</Label>
                    <Input
                        id="total_transferencia_real"
                        inputmode="decimal"
                        autocomplete="off"
                        required
                        bind:value={$form.total_transferencia_real}
                        disabled={revisando}
                    />
                    <InputError message={$form.errors.total_transferencia_real} />
                </div>
                {#if !revisando}
                    <Button type="submit" class="w-full" disabled={!calculo}>Revisar diferencias</Button>
                {/if}
            </section>

            <section class="space-y-3 rounded-xl border border-border bg-card p-5">
                <h2 class="font-semibold">Cuadre por método</h2>
                {#if calculo}
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs uppercase tracking-wide text-muted-foreground">
                            <tr><th class="py-2">Método</th><th class="py-2 text-right">Sistema</th><th class="py-2 text-right">Diferencia</th></tr>
                        </thead>
                        <tbody>
                            {#each [['Efectivo (con fondo)', deCentavos(calculo.esperadoEfectivo), calculo.efectivo], ['Tarjeta', resumen.total_tarjeta, calculo.tarjeta], ['Transferencia', resumen.total_transferencia, calculo.transferencia]] as [nombre, sistema, dif] (nombre)}
                                {@const d = diferencia(dif)}
                                <tr class="border-t border-border">
                                    <td class="py-2">{nombre}</td>
                                    <td class="py-2 text-right tabular-nums">{dinero(sistema)}</td>
                                    <td class="py-2 text-right font-medium {d.tono}">{d.texto}</td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                    <p class="text-sm text-muted-foreground">
                        Total real del turno (sin el fondo): <b>{dinero(deCentavos(calculo.real))}</b> contra {dinero(resumen.total_sistema)} del sistema.
                    </p>
                {:else}
                    <p class="text-sm text-muted-foreground">
                        Captura los tres importes con hasta dos decimales para ver la diferencia de cada método.
                    </p>
                {/if}

                {#if revisando}
                    <div
                        role="alertdialog"
                        aria-labelledby="cerrar-titulo"
                        class="space-y-3 rounded-lg border border-amber-500/40 bg-amber-500/10 p-4"
                    >
                        <p id="cerrar-titulo" class="text-sm">
                            <b>¿Cerrar el corte con estos importes?</b> Después ya no se podrá corregir ni reembolsar ningún pago de este turno.
                        </p>
                        <div class="flex gap-2">
                            <Button type="button" variant="outline" size="sm" onclick={() => (revisando = false)} disabled={$form.processing}
                                >Corregir importes</Button
                            >
                            <Button type="button" variant="destructive" size="sm" onclick={confirmar} disabled={$form.processing}>Cerrar corte</Button
                            >
                        </div>
                    </div>
                {/if}
            </section>
        </form>
    </div>
</AppLayout>
