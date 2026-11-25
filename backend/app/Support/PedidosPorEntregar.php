<?php

namespace App\Support;

use App\Models\ItemPedido;
use App\Models\Pedido;
use App\Models\PedidoEstacion;

// Módulo 2 · HU-09. Pedidos por entregar, a nivel de PEDIDO (mesero, cajero y administrador).
// Un pedido solo está "listo" cuando todas sus estaciones lo están (estado global = el de la estación más atrasada),
// y la entrega es todo o nada (PedidoService::entregar). Los listos van primero; dentro de cada grupo, el más antiguo.
final class PedidosPorEntregar
{
    public const MAXIMO = 200;

    /** @param string $entrega 'comer_aqui' (por defecto) o 'todos' */
    public static function lista(string $entrega): array
    {
        return Pedido::query()
            ->select('pedidos.*')
            // Lo cobrado sale de la misma regla que caja (Pedido::saldo): pagos activos que no son reembolso.
            // En una subconsulta, para no hacer una consulta por pedido.
            ->selectRaw('(SELECT COALESCE(SUM(pa.monto), 0) FROM pagos pa WHERE pa.id_pedido = pedidos.id
                AND pa.eliminado = false AND pa.reembolso = false) AS cobrado')
            ->where('pedidos.eliminado', false)
            ->whereIn('pedidos.estado', ['recibido', 'preparacion', 'listo'])
            ->when($entrega !== 'todos', fn ($q) => $q->where('pedidos.tipo_entrega', 'comer_aqui'))
            ->orderByRaw("CASE WHEN pedidos.estado = 'listo' THEN 0 ELSE 1 END")
            ->orderBy('pedidos.created_at')->orderBy('pedidos.id')
            ->limit(self::MAXIMO)
            ->with(['estaciones' => fn ($q) => $q->orderBy('id'), 'items' => fn ($q) => $q->where('eliminado', false)->orderBy('id'), 'items.producto:id,nombre'])
            ->get()
            ->map(function (Pedido $p) {
                $estadoDe = $p->estaciones->pluck('estado', 'estacion');
                $saldo = Money::subtract($p->total, (string) $p->cobrado);

                return [
                    'id' => $p->id,
                    'numero_pedido' => $p->numero_pedido,
                    'estado' => $p->estado,
                    'tipo_entrega' => $p->tipo_entrega,
                    'num_mesa' => $p->num_mesa,
                    'total' => $p->total,
                    'saldo' => bccomp($saldo, '0', 2) > 0 ? $saldo : '0.00',
                    'created_at' => $p->created_at?->toISOString(),
                    'estaciones' => $p->estaciones->map(fn (PedidoEstacion $e) => ['estacion' => $e->estacion, 'estado' => $e->estado])->all(),
                    'items' => $p->items->map(fn (ItemPedido $i) => [
                        'id' => $i->id, 'cantidad' => $i->cantidad, 'nombre' => $i->producto?->nombre ?? 'Producto',
                        'notas' => $i->notas, 'estacion' => $i->estacion, 'estado' => $estadoDe[$i->estacion] ?? null,
                    ])->all(),
                ];
            })
            ->all();
    }
}
