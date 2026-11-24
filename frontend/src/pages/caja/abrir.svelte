<script>
    import { useForm } from '@inertiajs/svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import PageTitle from '@/components/page-title.svelte';
    import Aviso from '@/components/caja/aviso.svelte';
    import Button from '@/components/ui/button.svelte';
    import InputDinero from '@/components/caja/input-dinero.svelte';
    import Label from '@/components/ui/label.svelte';
    import InputError from '@/components/input-error.svelte';
    // Si el cajero ya tiene un turno abierto, el servidor lo manda a su turno: aquí solo se abre uno nuevo.
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
                Captura el efectivo con el que empieza tu caja. Cada cajero tiene su propio turno, y no puedes abrir otro hasta hacer el corte de
                este. Al cerrar, el efectivo contado se compara contra este fondo más lo cobrado.
            </p>
        </div>

        <Aviso campos={['corte']} />

        <form class="space-y-4 rounded-xl border border-border bg-card p-5" onsubmit={submit}>
            <div class="grid gap-2">
                <Label for="total_caja_inicial">Fondo inicial (MXN)</Label>
                <InputDinero
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
    </div>
</AppLayout>
