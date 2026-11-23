<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Support\DomainAccess;
use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CajaController extends Controller
{
    use PresentaCaja;

    // Pedidos por cobrar: cualquier pedido no cancelado con saldo, sin importar su estado
    // (para llevar se cobra antes; comer aquí, después).
    public function index(Request $request): Response
    {
        $cajero = $request->user();
        DomainAccess::requireRole($cajero, ['cajero']);

        $pagosValidos = 'FROM pagos WHERE pagos.id_pedido = pedidos.id AND NOT pagos.eliminado AND NOT pagos.reembolso';
        $pedidos = Pedido::activos()
            ->where('estado', '!=', 'cancelado')
            ->whereRaw("pedidos.total > (SELECT COALESCE(SUM(monto), 0) $pagosValidos)")
            ->withSum(['pagos as pagado' => fn ($q) => $q->activos()->where('reembolso', false)], 'monto')
            ->with('usuario:id,name')
            ->orderBy('created_at')
            ->limit(200)
            ->get()
            ->map(function (Pedido $pedido) {
                // PostgreSQL regresa la suma como texto ("60.00"): se conserva sin pasar por float.
                $pagado = Money::parse((string) ($pedido->pagado ?? '0'));

                return [
                    'id' => $pedido->id,
                    'numero_pedido' => $pedido->numero_pedido,
                    'cliente' => $pedido->usuario?->name,
                    'estado' => $pedido->estado,
                    'tipo_entrega' => $pedido->tipo_entrega,
                    'num_mesa' => $pedido->num_mesa,
                    'total' => $pedido->total,
                    'pagado' => $pagado,
                    'saldo' => Money::subtract($pedido->total, $pagado),
                    'fecha' => $pedido->created_at?->toIso8601String(),
                ];
            });

        $corte = $this->corteAbierto();

        return Inertia::render('caja/index', [
            'pedidos' => $pedidos,
            'corte' => $corte ? [...$this->corte($corte), 'es_mio' => $corte->id_usuario === $cajero->id] : null,
        ]);
    }
}
