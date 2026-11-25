<?php

namespace App\Support;

use App\Models\Pedido;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

// Módulo 2 · Historial de pedidos por día (HU-04; la reutiliza HU-10).
// El día de un pedido sale del corte en que se cobró (corte → pagos → pedidos): la APERTURA del primer corte con un
// pago activo que no sea reembolso. Si todavía no tiene pago (o se canceló sin pagar), cuenta el día en que se creó.
// El día se calcula en la zona del restaurante, la misma del folio, para que "hoy" signifique lo mismo en ambos.
final class HistorialPedidos
{
    public const POR_PAGINA = 20;

    /** Expresión SQL del día del pedido (fecha local del restaurante). */
    public static function expresionDia(): string
    {
        return "((COALESCE((SELECT MIN(c.fecha_apertura) FROM pagos pa JOIN cortes c ON c.id = pa.id_corte
                 WHERE pa.id_pedido = pedidos.id AND pa.eliminado = false AND pa.reembolso = false),
               pedidos.created_at) AT TIME ZONE ?)::date)";
    }

    /** Pedidos que levantó el usuario, con su columna `dia`; si se indica `$dia` (AAAA-MM-DD), solo los de ese día. */
    public static function delUsuario(User $usuario, ?string $dia = null): Builder
    {
        $zona = config('trackeat.zona_folio');

        return Pedido::query()
            ->select('pedidos.*')
            ->selectRaw(self::expresionDia().' AS dia', [$zona])
            ->where('pedidos.id_usuario', $usuario->id)
            ->where('pedidos.eliminado', false)
            ->when($dia, fn (Builder $q) => $q->whereRaw(self::expresionDia().' = ?', [$zona, $dia]))
            ->orderByDesc('pedidos.created_at')
            ->orderByDesc('pedidos.id');
    }

    /** Hoy en la zona del restaurante (AAAA-MM-DD). */
    public static function hoy(): string
    {
        return now(config('trackeat.zona_folio'))->toDateString();
    }

    /** Valida el parámetro `?dia=`: una fecha real AAAA-MM-DD o `todos`; cualquier otra cosa es hoy. */
    public static function normalizarDia(?string $valor): string
    {
        if ($valor === 'todos') {
            return 'todos';
        }
        if ($valor && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $valor) && checkdate((int) substr($valor, 5, 2), (int) substr($valor, 8, 2), (int) substr($valor, 0, 4))) {
            return $valor;
        }

        return self::hoy();
    }
}
