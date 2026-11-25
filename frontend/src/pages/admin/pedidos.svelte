<script>
    // HU-10 · Administración de pedidos: lista con filtros (estado, día, mesa, entrega, cliente y corte). Solo consulta.
    import { Link, router } from '@inertiajs/svelte';
    import AppLayout from '@/layouts/AppLayout.svelte';
    import PageTitle from '@/components/page-title.svelte';
    import Button from '@/components/ui/button.svelte';
    import Input from '@/components/ui/input.svelte';
    import { states } from '@/lib/trackeat';
    import { formatoFolio } from '@/lib/folio';

    let { pedidos, filtros, cortes, mesasMax } = $props();
    // El servidor devuelve `dia` ya resuelto: una fecha o `todos`.
    let form = $state({
        estado: filtros.estado ?? '',
        todosLosDias: filtros.dia === 'todos',
        dia: filtros.dia && filtros.dia !== 'todos' ? filtros.dia : '',
        mesa: filtros.mesa ?? '',
        entrega: filtros.entrega ?? '',
        cliente: filtros.cliente ?? '',
        corte: filtros.corte ?? '',
    });
    const money = (value) => `$${Number(value).toFixed(2)}`;
    const fecha = new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' });
    const corteEtiqueta = (c) =>
        `#${c.id} · ${fecha.format(new Date(c.fecha_apertura))}${c.cajero ? ` · ${c.cajero}` : ''}${c.estado === 'abierto' ? ' (abierto)' : ''}`;

    function filtrar(event) {
        event.preventDefault();
        const params = {
            estado: form.estado,
            dia: form.todosLosDias ? 'todos' : form.dia,
            mesa: form.mesa,
            entrega: form.entrega,
            cliente: form.cliente.trim(),
            corte: form.corte,
        };
        // Los filtros vacíos no viajan en la dirección.
        for (const key of Object.keys(params)) if (params[key] === '' || params[key] === null) delete params[key];
        router.get('/admin/pedidos', params, { preserveState: true, replace: true });
    }

    function limpiar() {
        router.get('/admin/pedidos', {}, { replace: true });
    }
</script>

<PageTitle title="Pedidos — TrackEat" />
<AppLayout headerTitle="Pedidos">
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">Pedidos</h1>
            <p class="text-sm text-muted-foreground">{pedidos.total} {pedidos.total === 1 ? 'pedido' : 'pedidos'}</p>
        </div>
        <form onsubmit={filtrar} class="flex flex-wrap items-end gap-3">
            <div class="grid gap-1.5">
                <label for="f-estado" class="text-sm font-medium">Estado</label>
                <select id="f-estado" class="h-9 rounded-md border border-input bg-background px-3 text-sm" bind:value={form.estado}>
                    <option value="">Todos</option>
                    {#each Object.entries(states) as [valor, etiqueta] (valor)}
                        <option value={valor}>{etiqueta}</option>
                    {/each}
                </select>
            </div>
            <div class="grid gap-1.5">
                <label for="f-dia" class="text-sm font-medium">Día</label>
                <Input id="f-dia" type="date" bind:value={form.dia} disabled={form.todosLosDias} />
            </div>
            <label class="flex h-9 items-center gap-2 text-sm"><input type="checkbox" bind:checked={form.todosLosDias} />Todos los días</label>
            <div class="grid gap-1.5">
                <label for="f-mesa" class="text-sm font-medium">Mesa</label>
                <Input id="f-mesa" type="number" min="1" max={mesasMax} class="w-24" bind:value={form.mesa} />
            </div>
            <div class="grid gap-1.5">
                <label for="f-entrega" class="text-sm font-medium">Entrega</label>
                <select id="f-entrega" class="h-9 rounded-md border border-input bg-background px-3 text-sm" bind:value={form.entrega}>
                    <option value="">Todas</option>
                    <option value="comer_aqui">Comer aquí</option>
                    <option value="para_llevar">Para llevar</option>
                </select>
            </div>
            <div class="grid gap-1.5">
                <label for="f-cliente" class="text-sm font-medium">Cliente</label>
                <Input id="f-cliente" maxlength={120} placeholder="Nombre, correo o teléfono" bind:value={form.cliente} />
            </div>
            <div class="grid gap-1.5">
                <label for="f-corte" class="text-sm font-medium">Corte</label>
                <select id="f-corte" class="h-9 max-w-64 rounded-md border border-input bg-background px-3 text-sm" bind:value={form.corte}>
                    <option value="">Todos</option>
                    {#each cortes as corte (corte.id)}
                        <option value={String(corte.id)}>{corteEtiqueta(corte)}</option>
                    {/each}
                </select>
            </div>
            <Button type="submit" variant="outline">Filtrar</Button>
            <Button type="button" variant="outline" onclick={limpiar}>Limpiar</Button>
        </form>
        <div class="overflow-x-auto rounded-xl border border-border bg-card">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-border bg-muted/40">
                    <tr>
                        <th scope="col" class="px-4 py-3">Folio</th>
                        <th scope="col" class="px-4 py-3">Cliente</th>
                        <th scope="col" class="px-4 py-3">Fecha</th>
                        <th scope="col" class="px-4 py-3">Entrega</th>
                        <th scope="col" class="px-4 py-3">Estado</th>
                        <th scope="col" class="px-4 py-3 text-right">Total</th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    {#each pedidos.data as pedido (pedido.id)}
                        <tr>
                            <td class="px-4 py-3 font-semibold">{formatoFolio(pedido.numero_pedido)}</td>
                            <td class="px-4 py-3"
                                >{#if pedido.cliente}<span class="font-medium">{pedido.cliente.name}</span><br /><span class="text-muted-foreground"
                                        >{pedido.cliente.email}</span
                                    >{:else}—{/if}</td
                            >
                            <td class="whitespace-nowrap px-4 py-3">{fecha.format(new Date(pedido.created_at))}</td>
                            <td class="px-4 py-3">{pedido.tipo_entrega === 'comer_aqui' ? `Mesa ${pedido.num_mesa}` : 'Para llevar'}</td>
                            <td class="px-4 py-3">{states[pedido.estado] ?? pedido.estado}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">{money(pedido.total)}</td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <Link href={`/admin/pedidos/${pedido.id}`} aria-label={`Ver pedido ${formatoFolio(pedido.numero_pedido)}`} class="underline">Ver detalle</Link>
                            </td>
                        </tr>
                    {:else}
                        <tr><td colspan="7" class="px-4 py-6 text-center text-muted-foreground">No hay pedidos que coincidan.</td></tr>
                    {/each}
                </tbody>
            </table>
        </div>
        {#if pedidos.last_page > 1}
            <nav aria-label="Paginación de pedidos" class="flex items-center gap-4 text-sm">
                {#if pedidos.prev_page_url}<Link href={pedidos.prev_page_url} class="underline">Anterior</Link>{/if}
                <span>Página {pedidos.current_page} de {pedidos.last_page}</span>
                {#if pedidos.next_page_url}<Link href={pedidos.next_page_url} class="underline">Siguiente</Link>{/if}
            </nav>
        {/if}
    </div>
</AppLayout>
