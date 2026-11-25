<?php

namespace App\Http\Controllers\Pedidos;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Services\PedidoService;
use App\Support\DomainAccess;
use App\Support\HistorialPedidos;
use App\Support\OrderAccess;
use App\Support\PedidosPorEntregar;
use App\Support\TableroComandas;
use Illuminate\Http\Request;
use Inertia\Inertia;

// Módulo 2 · Pedidos y comandas.
class PedidoController extends Controller
{
    private function staff(Request $request): bool
    {
        return in_array($request->user()->rol, ['administrador', 'cocinero', 'mesero', 'cajero'], true);
    }

    public function pedidos(Request $request)
    {
        $query = OrderAccess::query($request->user())->with(['items.producto', 'estaciones']);

        return response()->json($query->orderByDesc('id')->limit(200)->get())->header('Cache-Control', 'no-store');
    }

    public function detalle(Request $request, int $pedido)
    {
        return response()->json(OrderAccess::find($request->user(), $pedido)->load('items.producto', 'estaciones'))->header('Cache-Control', 'no-store');
    }

    public function paginaPedido(Request $request, int $pedido)
    {
        return Inertia::render('public/pedido', ['pedido' => OrderAccess::find($request->user(), $pedido)->load('items.producto', 'estaciones')]);
    }

    // HU-04 · Mis pedidos por día. Siempre filtra por quien hace la petición; `?dia=` solo cambia la fecha.
    public function misPedidos(Request $request)
    {
        $dia = HistorialPedidos::normalizarDia($request->query('dia'));
        $pagina = HistorialPedidos::delUsuario($request->user(), $dia === 'todos' ? null : $dia)
            ->paginate(HistorialPedidos::POR_PAGINA)->withQueryString()
            ->through(fn (Pedido $p) => ['id' => $p->id, 'numero_pedido' => $p->numero_pedido, 'estado' => $p->estado,
                'total' => $p->total, 'tipo_entrega' => $p->tipo_entrega, 'num_mesa' => $p->num_mesa,
                'created_at' => $p->created_at?->toISOString(), 'dia' => $p->dia]);

        return Inertia::render('public/mis-pedidos', ['pedidos' => $pagina, 'dia' => $dia, 'hoy' => HistorialPedidos::hoy()]);
    }

    public function crear(Request $request, PedidoService $service)
    {
        // Mismos límites que PedidoService (config `trackeat.pedidos`); el servicio aplica además mesa, total y pedidos en curso.
        $limites = config('trackeat.pedidos');
        $data = $request->validate([
            'client_request_id' => ['required', 'uuid'], 'tipo_entrega' => ['required', 'string'],
            'num_mesa' => ['nullable', 'integer'], 'items' => ['required', 'array', 'min:1', 'max:'.$limites['renglones_max']],
            'items.*.id_producto' => ['required', 'integer'], 'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:'.$limites['cantidad_max']],
            'items.*.notas' => ['nullable', 'string', 'max:1000', 'not_regex:/\x00/u'],
            'items.*.precio_confirmado' => ['sometimes', 'required', 'numeric', 'min:0', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
        ]);
        // El dueño del pedido lo fija el servicio (quien lo levanta); el cuerpo no puede elegirlo ni enviar el total.
        $order = $service->crear($request->user(), $data);

        return response()->json($order->load('items.producto', 'estaciones'));
    }

    public function avanzar(Request $request, Pedido $pedido, PedidoService $service)
    {
        OrderAccess::find($request->user(), $pedido->id);
        $data = $request->validate(['estacion' => ['required', 'string'], 'estado' => ['required', 'string']]);

        return response()->json($service->avanzar($request->user(), $pedido->id, $data['estacion'], $data['estado']));
    }

    // HU-05. 404 si el pedido no es visible para el usuario; el servicio aplica la política y las reglas del pedido.
    public function cancelar(Request $request, Pedido $pedido, PedidoService $service)
    {
        OrderAccess::find($request->user(), $pedido->id);
        $data = $request->validate(['motivo' => ['nullable', 'string', 'max:50']]);

        return response()->json($service->cancelar($request->user(), $pedido->id, $data['motivo'] ?? null));
    }

    // HU-06 · Tablero de comandas (cocineros y administrador). El filtro solo lo usa el administrador;
    // al cocinero el servidor le impone su estación aunque envíe otra.
    public function comandas(Request $request)
    {
        DomainAccess::requireRole($request->user(), ['cocinero', 'administrador']);
        $actor = $request->user()->fresh();

        return response()->json([
            'estaciones' => TableroComandas::estacionesVisibles($actor, $request->query('estacion')),
            'comandas' => TableroComandas::para($actor, $request->query('estacion'), $request->boolean('entregados')),
        ])->header('Cache-Control', 'no-store');
    }

    // HU-09 · Pedidos por entregar (mesero, cajero y administrador), a nivel de pedido.
    public function paginaPorEntregar(Request $request)
    {
        DomainAccess::requireRole($request->user(), ['mesero', 'cajero', 'administrador']);

        return Inertia::render('mesero/pedidos');
    }

    public function porEntregar(Request $request)
    {
        DomainAccess::requireRole($request->user(), ['mesero', 'cajero', 'administrador']);
        $entrega = $request->query('entrega') === 'todos' ? 'todos' : 'comer_aqui';

        return response()->json(['entrega' => $entrega, 'pedidos' => PedidosPorEntregar::lista($entrega)])->header('Cache-Control', 'no-store');
    }

    // La entrega es todo o nada; 404 si el pedido no es visible para el usuario.
    public function entregar(Request $request, Pedido $pedido, PedidoService $service)
    {
        OrderAccess::find($request->user(), $pedido->id);

        return response()->json($service->entregar($request->user(), $pedido->id));
    }

    public function tablero(Request $request)
    {
        $query = Pedido::activos();
        if ($request->user()->demo_room) {
            $query->where('demo_room', $request->user()->demo_room);
        } else {
            abort_unless($this->staff($request), 403);
        }

        return response()->json([
            'estados' => (clone $query)->toBase()->selectRaw('estado, count(*) AS total')->groupBy('estado')->pluck('total', 'estado')->map(fn ($total) => (int) $total),
            'pedidos' => $query->orderByDesc('id')->limit(100)->get(['id', 'numero_pedido', 'estado', 'updated_at']),
        ]);
    }
}
