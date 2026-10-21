<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Pedido;
use App\Models\Producto;
use App\Services\PedidoService;
use Illuminate\Http\Request;

class TrackEatController extends Controller
{
    private function staff(Request $request): bool
    {
        return in_array($request->user()->rol, ['administrador', 'cocinero', 'mesero', 'cajero'], true);
    }

    public function catalogo()
    {
        return response()->json([
            'categorias' => Categoria::activos()->where('disponible', true)->orderBy('nombre')->get(['id', 'nombre', 'estacion']),
            'productos' => Producto::activos()->where('disponible', true)
                ->whereHas('categoria', fn ($q) => $q->activos()->where('disponible', true))
                ->with('imagenes')->orderBy('nombre')->get()->map(fn ($p) => [
                    'id' => $p->id, 'id_categoria' => $p->id_categoria, 'nombre' => $p->nombre,
                    'descripcion' => $p->descripcion, 'precio' => $p->precio,
                    'imagen' => $p->imagenes->first(fn ($i) => ! $i->eliminado)?->url ?? '/logo.svg',
                ]),
            'actualizado' => now()->toISOString(),
        ])->header('Cache-Control', 'no-store');
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
        $query = Pedido::activos()->with(['items.producto', 'estaciones']);
        if (! $this->staff($request)) {
            $query->where('id_usuario', $request->user()->id);
        }

        return response()->json($query->orderByDesc('id')->limit(200)->get());
    }

    public function crear(Request $request, PedidoService $service)
    {
        $data = $request->validate([
            'client_request_id' => ['required', 'uuid'], 'tipo_entrega' => ['required', 'string'],
            'num_mesa' => ['nullable', 'integer'], 'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id_producto' => ['required', 'integer'], 'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:100'],
            'items.*.notas' => ['nullable', 'string', 'max:1000'],
        ]);
        // The caller cannot choose another customer or submit their own total.
        $data['id_usuario'] = $request->user()->id;
        $order = $service->crear($request->user(), $data);

        return response()->json($order->load('items.producto', 'estaciones'));
    }

    public function avanzar(Request $request, Pedido $pedido, PedidoService $service)
    {
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
