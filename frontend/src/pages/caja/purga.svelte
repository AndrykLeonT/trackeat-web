<script>
    import { router } from '@inertiajs/svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import PageTitle from '@/components/page-title.svelte';
    import Aviso from '@/components/caja/aviso.svelte';
    import PagosTabla from '@/components/caja/pagos-tabla.svelte';
    import Button from '@/components/ui/button.svelte';
    import Input from '@/components/ui/input.svelte';
    import Label from '@/components/ui/label.svelte';

    let { pagos = [] } = $props();

    let password = $state('');
    let confirmando = $state(null);
    let enviando = $state(false);

    // El servidor (PurgaService) vuelve a comprobar el rol y la contraseña antes de borrar.
    function purgar() {
        enviando = true;
        router.delete(route('admin.purga.destroy', confirmando.id), {
            data: { password },
            preserveScroll: true,
            onFinish: () => {
                enviando = false;
                confirmando = null;
                password = '';
            },
        });
    }
</script>

<PageTitle title="Purga de registros" />
<AppLayout headerTitle="Administración">
    <div class="space-y-6">
        <div class="border-b border-border pb-5">
            <h1 class="text-2xl font-bold">Purga de registros</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Borrado definitivo de pagos eliminados por error de captura. Solo se pueden purgar los de un corte que sigue abierto; lo que pertenece
                a un corte cerrado es inmutable.
            </p>
        </div>

        <Aviso campos={['password', 'pago']} />

        {#if confirmando}
            <div role="alertdialog" aria-labelledby="purga-titulo" class="rounded-xl border border-destructive/40 bg-destructive/5 p-4">
                <form
                    class="space-y-3"
                    onsubmit={(e) => {
                        e.preventDefault();
                        purgar();
                    }}
                >
                    <p id="purga-titulo" class="text-sm">
                        <b>Borrar definitivamente el pago #{confirmando.id} del pedido {confirmando.pedido}.</b> No se puede deshacer.
                    </p>
                    <div class="grid max-w-sm gap-2">
                        <Label for="password">Confirma tu contraseña</Label>
                        <Input id="password" type="password" autocomplete="current-password" required bind:value={password} />
                    </div>
                    <div class="flex gap-2">
                        <Button type="button" variant="outline" size="sm" onclick={() => (confirmando = null)} disabled={enviando}>Cancelar</Button>
                        <Button type="submit" variant="destructive" size="sm" disabled={enviando || !password}>Borrar definitivamente</Button>
                    </div>
                </form>
            </div>
        {/if}

        {#if pagos.length === 0}
            <p class="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                No hay pagos eliminados pendientes de purgar.
            </p>
        {:else}
            <PagosTabla {pagos}>
                {#snippet acciones(pago)}
                    <Button variant="destructive" size="sm" onclick={() => (confirmando = pago)}>Purgar</Button>
                {/snippet}
            </PagosTabla>
        {/if}
    </div>
</AppLayout>
