<script>
    import { useForm } from '@inertiajs/svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import PageTitle from '@/components/page-title.svelte';
    import Aviso from '@/components/caja/aviso.svelte';
    import Button from '@/components/ui/button.svelte';
    import Input from '@/components/ui/input.svelte';
    import Label from '@/components/ui/label.svelte';
    import InputError from '@/components/input-error.svelte';
    import { dinero, fecha } from '@/lib/caja';

    // Si ya hay un corte abierto (de otro cajero), se muestra de quién es.
    let { corte = null } = $props();

    let form = useForm({ total_caja_inicial: '' });

    function submit(e) {
        e.preventDefault();
        $form.post(route('caja.abrir.store'));
    }
</script>

<PageTitle title="Abrir turno" />
<AppLayout headerTitle="Caja">
    <div class="mx-auto max-w-md space-y-6">
        <div class="border-b border-border pb-5">
            <h1 class="text-2xl font-bold">Abrir turno</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Captura el efectivo con el que empieza la caja. Al cerrar, el efectivo contado se compara contra este fondo más lo cobrado.
            </p>
        </div>

        <Aviso campos={['corte']} />

        {#if corte}
            <p class="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm">
                Ya hay un turno abierto por <b>{corte.cajero}</b> desde {fecha(corte.fecha_apertura)} (fondo {dinero(corte.total_caja_inicial)}). Debe
                cerrarse antes de abrir otro.
            </p>
        {:else}
            <form class="space-y-4 rounded-xl border border-border bg-card p-5" onsubmit={submit}>
                <div class="grid gap-2">
                    <Label for="total_caja_inicial">Fondo inicial (MXN)</Label>
                    <Input
                        id="total_caja_inicial"
                        inputmode="decimal"
                        autocomplete="off"
                        placeholder="300.00"
                        required
                        bind:value={$form.total_caja_inicial}
                    />
                    <InputError message={$form.errors.total_caja_inicial} />
                </div>
                <Button type="submit" class="w-full" disabled={$form.processing}>Abrir turno</Button>
            </form>
        {/if}
    </div>
</AppLayout>
