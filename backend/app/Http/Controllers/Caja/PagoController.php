<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Models\Pago;
use App\Models\Pedido;
use App\Services\CajaService;
use App\Support\DomainAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PagoController extends Controller
{
    use PresentaCaja;

    public function __construct(private readonly CajaService $caja) {}

    public function create(Request $request, Pedido $pedido): Response|RedirectResponse
    {
        DomainAccess::requireRole($request->user(), ['cajero']);
        if (! $this->corteDe($request->user())) {
            return to_route('caja.abrir')->with('status', 'Abre un turno antes de cobrar.');
        }
        $pedido->load(['usuario:id,name', 'items' => fn ($q) => $q->activos()->with('producto:id,nombre'),
            'pagos' => fn ($q) => $q->with(['pedido:id,numero_pedido', 'usuario:id,name'])->orderBy('id')]);

        return Inertia::render('caja/cobrar', [
            'pedido' => [
                'id' => $pedido->id,
                'numero_pedido' => $pedido->numero_pedido,
                'cliente' => $pedido->usuario?->name,
                'estado' => $pedido->estado,
                'tipo_entrega' => $pedido->tipo_entrega,
                'num_mesa' => $pedido->num_mesa,
                'total' => $pedido->total,
                'saldo' => $pedido->saldo(),
                'estado_pago' => $pedido->estadoPago(),
                'items' => $pedido->items->map(fn ($item) => [
                    'id' => $item->id,
                    'producto' => $item->producto?->nombre,
                    'cantidad' => $item->cantidad,
                    'precio_unitario' => $item->precio_unitario,
                    'notas' => $item->notas,
                ]),
            ],
            'pagos' => $pedido->pagos->map(fn (Pago $pago) => $this->pago($pago)),
        ]);
    }

    // Pago dividido: cada cobro registra un pago; se repite hasta que el saldo queda en cero.
    public function store(Request $request, Pedido $pedido): RedirectResponse
    {
        $request->validate([
            'metodo_pago' => ['required', 'in:efectivo,tarjeta,transferencia'],
            'monto' => $this->reglaDinero(),
            'propina' => $this->reglaDinero(obligatorio: false),
        ], $this->mensajesDinero(['monto', 'propina']));
        DomainAccess::requireRole($request->user(), ['cajero']);
        $corte = $this->corteDe($request->user());
        if (! $corte) {
            return to_route('caja.abrir')->with('status', 'Abre un turno antes de cobrar.');
        }
        $this->caja->cobrar($request->user(), $pedido->id, $corte->id, $request->input('metodo_pago'),
            $request->input('monto'), $request->input('propina') ?: '0');

        if ($pedido->estadoPago() === 'pagado') {
            return to_route('caja.index')->with('status', "Pedido {$pedido->numero_pedido} pagado.");
        }

        return back()->with('status', 'Pago registrado.');
    }

    // Error de captura: el pago deja de contar y el administrador puede purgarlo después.
    public function eliminar(Request $request, Pago $pago): RedirectResponse
    {
        $this->caja->corregir($request->user(), $pago->id);

        return back()->with('status', 'Pago eliminado por error de captura.');
    }

    // Devolución real al cliente: el registro se conserva para el corte.
    public function reembolsar(Request $request, Pago $pago): RedirectResponse
    {
        $this->caja->reembolsar($request->user(), $pago->id);

        return back()->with('status', 'Pago reembolsado.');
    }
}
