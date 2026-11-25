<?php

namespace App\Http\Controllers\Pedidos;

use App\Http\Controllers\Controller;
use App\Models\Corte;
use App\Models\Pedido;
use App\Support\HistorialPedidos;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

// Módulo 2 · HU-10. Administración de pedidos: lista con filtros y detalle con bitácora. Solo consulta (sin exportar
// ni modificar); la ruta ya exige el rol administrador. Las salas de demostración quedan fuera.
class AdminPedidoController extends Controller
{
    private const POR_PAGINA = 20;

    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'estado' => ['nullable', Rule::in(['recibido', 'preparacion', 'listo', 'entregado', 'cancelado'])],
            'dia' => ['nullable', 'string', 'max:10'],
            'mesa' => ['nullable', 'integer', 'min:1', 'max:'.config('trackeat.pedidos.mesas_max')],
            'entrega' => ['nullable', Rule::in(['para_llevar', 'comer_aqui'])],
            'cliente' => ['nullable', 'string', 'max:120', 'not_regex:/\x00/u'],
            'corte' => ['nullable', 'integer', 'min:1', 'max:999999999', Rule::exists('cortes', 'id')],
        ]);
        // Sin día elegido: hoy; pero si se filtra por corte no se aplica día, porque el corte ya acota los pedidos.
        $filtros['dia'] = ($filtros['dia'] ?? null) === null && ($filtros['corte'] ?? null) !== null
            ? 'todos'
            : HistorialPedidos::normalizarDia($filtros['dia'] ?? null);

        $pedidos = HistorialPedidos::paraAdministracion($filtros)->paginate(self::POR_PAGINA)->withQueryString()
            ->through(fn (Pedido $p) => ['id' => $p->id, 'numero_pedido' => $p->numero_pedido, 'estado' => $p->estado,
                'total' => $p->total, 'tipo_entrega' => $p->tipo_entrega, 'num_mesa' => $p->num_mesa,
                'created_at' => $p->created_at?->toISOString(), 'dia' => $p->dia,
                'cliente' => $p->usuario ? ['name' => $p->usuario->name, 'email' => $p->usuario->email] : null]);

        // Cortes recientes para el selector; si se filtra por uno más antiguo, se incluye para que se vea elegido.
        $cortes = Corte::query()->activos()->with('usuario:id,name')
            ->when($filtros['corte'] ?? null, fn ($q, $corte) => $q->orWhere('cortes.id', $corte))
            ->orderByDesc('fecha_apertura')->orderByDesc('id')->limit(61)->get()
            ->map(fn (Corte $c) => ['id' => $c->id, 'estado' => $c->estado, 'fecha_apertura' => $c->fecha_apertura?->toISOString(),
                'cajero' => $c->usuario?->name]);

        return Inertia::render('admin/pedidos', [
            'pedidos' => $pedidos,
            'filtros' => (object) array_filter($filtros, fn ($valor) => $valor !== null && $valor !== ''),
            'cortes' => $cortes,
            'mesasMax' => (int) config('trackeat.pedidos.mesas_max'),
        ]);
    }

    public function show(int $pedido): Response
    {
        $pedido = Pedido::query()->activos()->whereNull('demo_room')
            ->with(['items.producto:id,nombre', 'estaciones', 'usuario:id,name,email,telefono', 'historial.responsable:id,name,rol'])
            ->findOrFail($pedido);

        return Inertia::render('admin/pedido', ['pedido' => $pedido]);
    }
}
