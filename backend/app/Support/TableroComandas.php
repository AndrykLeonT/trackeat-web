<?php

namespace App\Support;

use App\Models\ItemPedido;
use App\Models\PedidoEstacion;
use App\Models\User;
use Illuminate\Support\Carbon;

// Módulo 2 · HU-06. Tablero de comandas: cada comanda es un pedido en UNA estación (fila de `pedidos_estacion`)
// con solo los productos de esa estación (`items_pedido.estacion`, congelada al crear el pedido).
// Lo usan cocineros y administrador. Qué estaciones ve cada uno lo decide el servidor, no el navegador.
final class TableroComandas
{
    public const MAX_ACTIVAS = 200;

    public const MAX_ENTREGADAS = 50;

    /** Estaciones que el usuario ve; null = todas. El cocinero siempre ve la suya (o todas si es `general`). */
    public static function estacionesVisibles(User $usuario, ?string $filtro): ?array
    {
        if ($usuario->rol === 'cocinero') {
            return $usuario->estacion === 'general' ? null : [$usuario->estacion];
        }

        // Administrador: filtro opcional. `general` queda dentro de "todas" (solo existe en pedidos anteriores).
        return in_array($filtro, ['cocina', 'barra'], true) ? [$filtro] : null;
    }

    /** @return array<int, array<string, mixed>> */
    public static function para(User $usuario, ?string $filtro, bool $entregados): array
    {
        $estaciones = self::estacionesVisibles($usuario, $filtro);
        $base = fn () => PedidoEstacion::query()
            ->join('pedidos', 'pedidos.id', '=', 'pedidos_estacion.id_pedido')
            ->where('pedidos.eliminado', false)->where('pedidos.estado', '!=', 'cancelado')
            ->where('pedidos_estacion.eliminado', false)
            ->when($estaciones, fn ($q) => $q->whereIn('pedidos_estacion.estacion', $estaciones))
            ->select('pedidos_estacion.*', 'pedidos.numero_pedido', 'pedidos.tipo_entrega', 'pedidos.num_mesa',
                'pedidos.created_at as pedido_creado');

        // Activas: de la más antigua a la más nueva, como se atiende en cocina.
        $comandas = $base()->whereIn('pedidos_estacion.estado', ['recibido', 'preparacion', 'listo'])
            ->orderBy('pedidos.created_at')->orderBy('pedidos_estacion.id')->limit(self::MAX_ACTIVAS)->get();
        if ($entregados) {
            // Solo consulta: las entregadas HOY (zona del restaurante), las más recientes primero.
            $inicioDelDia = now(config('trackeat.zona_folio'))->startOfDay();
            $comandas = $comandas->concat($base()->where('pedidos_estacion.estado', 'entregado')
                ->where('pedidos_estacion.updated_at', '>=', $inicioDelDia)
                ->orderByDesc('pedidos_estacion.updated_at')->limit(self::MAX_ENTREGADAS)->get());
        }

        $items = ItemPedido::whereIn('id_pedido', $comandas->pluck('id_pedido')->unique())->where('eliminado', false)
            ->with('producto:id,nombre')->orderBy('id')->get()
            ->groupBy(fn (ItemPedido $item) => $item->id_pedido.'|'.$item->estacion);

        return $comandas->map(fn (PedidoEstacion $c) => [
            'id' => $c->id,
            'id_pedido' => $c->id_pedido,
            'numero_pedido' => $c->numero_pedido,
            'tipo_entrega' => $c->tipo_entrega,
            'num_mesa' => $c->num_mesa,
            'estacion' => $c->estacion,
            'estado' => $c->estado,
            'pedido_creado' => $c->pedido_creado ? Carbon::parse($c->pedido_creado)->toISOString() : null,
            'actualizado' => $c->updated_at?->toISOString(),
            'items' => ($items->get($c->id_pedido.'|'.$c->estacion) ?? collect())->map(fn (ItemPedido $item) => [
                'id' => $item->id, 'cantidad' => $item->cantidad, 'nombre' => $item->producto?->nombre ?? 'Producto', 'notas' => $item->notas,
            ])->values()->all(),
        ])->values()->all();
    }
}
