<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Services\PedidoService;
use App\Support\OrderAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TrackEatController extends Controller
{
    private function staff(Request $request): bool
    {
        return in_array($request->user()->rol, ['administrador', 'cocinero', 'mesero', 'cajero'], true);
    }

    public function sesion(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => $user ? ['id' => $user->id, 'name' => $user->name, 'rol' => $user->rol,
                'demo_room' => $user->demo_room, 'demo_expires_at' => $user->demo_expires_at?->toISOString()] : null,
            'csrf' => csrf_token(),
            'socket' => ['key' => config('reverb.apps.apps.0.key'), 'url' => config('trackeat.socket_url')],
        ])->header('Cache-Control', 'no-store');
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

    public function crear(Request $request, PedidoService $service)
    {
        $data = $request->validate([
            'client_request_id' => ['required', 'uuid'], 'tipo_entrega' => ['required', 'string'],
            'num_mesa' => ['nullable', 'integer'], 'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id_producto' => ['required', 'integer'], 'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:100'],
            'items.*.notas' => ['nullable', 'string', 'max:1000', 'not_regex:/\x00/u'],
            'items.*.precio_confirmado' => ['sometimes', 'required', 'numeric', 'min:0', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
        ]);
        // The caller cannot choose another customer or submit their own total.
        $data['id_usuario'] = $request->user()->id;
        $order = $service->crear($request->user(), $data);

        return response()->json($order->load('items.producto', 'estaciones'));
    }

    public function avanzar(Request $request, Pedido $pedido, PedidoService $service)
    {
        OrderAccess::find($request->user(), $pedido->id);
        $data = $request->validate(['estacion' => ['required', 'string'], 'estado' => ['required', 'string']]);

        return response()->json($service->avanzar($request->user(), $pedido->id, $data['estacion'], $data['estado']));
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
