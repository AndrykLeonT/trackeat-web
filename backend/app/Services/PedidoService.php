<?php

namespace App\Services;

use App\Events\PedidoActualizado;
use App\Models\Categoria;
use App\Models\Corte;
use App\Models\HistorialEstado;
use App\Models\Pedido;
use App\Models\PedidoEstacion;
use App\Models\Producto;
use App\Models\User;
use App\Support\DomainAccess;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PedidoService
{
    private const FLOW = ['recibido', 'preparacion', 'listo', 'entregado'];

    public function crear(User $actor, array $data): Pedido
    {
        DomainAccess::requireRole($actor, ['cliente', 'mesero', 'cajero', 'administrador']);
        $data = Validator::make($data, [
            'id_usuario' => ['required', 'integer'],
            'tipo_entrega' => ['required', 'in:para_llevar,comer_aqui'],
            'num_mesa' => ['nullable', 'integer', 'min:1', 'required_if:tipo_entrega,comer_aqui', 'prohibited_if:tipo_entrega,para_llevar'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id_producto' => ['required', 'integer'],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:10000'],
            'items.*.notas' => ['nullable', 'string', 'max:1000'],
            'client_request_id' => ['nullable', 'uuid'],
        ])->validate();
        if ($actor->fresh()->rol === 'cliente' && (int) $data['id_usuario'] !== $actor->id) {
            throw new AuthorizationException('Solo puede ordenar para su propia cuenta.');
        }

        return DB::transaction(function () use ($data, $actor) {
            if (! empty($data['client_request_id'])) {
                // Serialize retries of the same request, including simultaneous HTTP requests.
                DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$actor->id.':'.$data['client_request_id']]);
                $existing = Pedido::where('id_usuario', $data['id_usuario'])->where('client_request_id', $data['client_request_id'])->first();
                if ($existing) {
                    return $existing->load('items', 'estaciones');
                }
            }
            $client = User::whereKey($data['id_usuario'])->where('rol', 'cliente')->where('eliminado', false)->sharedLock()->first();
            if (! $client) {
                $this->invalid('id_usuario', 'Se requiere un cliente registrado y activo.');
            }
            $lines = [];
            $total = '0.00';
            // Orden estable de bloqueos para carritos con múltiples productos.
            $products = Producto::whereIn('id', array_column($data['items'], 'id_producto'))->orderBy('id')->sharedLock()->get()->keyBy('id');
            $categories = Categoria::whereIn('id', $products->pluck('id_categoria'))->orderBy('id')->sharedLock()->get()->keyBy('id');
            foreach ($data['items'] as $item) {
                $product = $products->get($item['id_producto']);
                $category = $product ? $categories->get($product->id_categoria) : null;
                if (! $product || $product->eliminado || ! $product->disponible || ! $category || $category->eliminado || ! $category->disponible) {
                    $this->invalid('items', 'Uno de los productos no está disponible.');
                }
                $lines[] = ['id_producto' => $product->id, 'cantidad' => $item['cantidad'],
                    'precio_unitario' => $product->precio, 'estacion' => $category->estacion, 'notas' => $item['notas'] ?? null];
                $total = Money::add($total, Money::multiply($product->precio, $item['cantidad']));
            }
            Money::parse($total, 'total');
            $pedido = Pedido::create(['id_usuario' => $client->id, 'numero_pedido' => 'TE-'.Str::ulid(),
                'tipo_entrega' => $data['tipo_entrega'], 'num_mesa' => $data['num_mesa'] ?? null, 'total' => $total,
                'client_request_id' => $data['client_request_id'] ?? null, 'demo_room' => $actor->demo_room]);
            $pedido->items()->createMany($lines);
            foreach (array_unique(array_column($lines, 'estacion')) as $station) {
                $pedido->estaciones()->create(['estacion' => $station]);
            }

            $pedido->refresh()->load('items', 'estaciones');
            event(PedidoActualizado::forOrder($pedido));

            return $pedido;
        }, 3);
    }

    public function avanzar(User $actor, int $pedidoId, string $station, string $newState): Pedido
    {
        DomainAccess::requireRole($actor, ['cocinero', 'mesero', 'cajero', 'administrador']);
        Validator::make(['estacion' => $station, 'estado' => $newState], [
            'estacion' => 'required|in:general,cocina,barra', 'estado' => 'required|in:preparacion,listo,entregado',
        ])->validate();
        $role = $actor->fresh()->rol;
        if (($role === 'cocinero' && $newState === 'entregado') || (in_array($role, ['mesero', 'cajero'], true) && $newState !== 'entregado')) {
            throw new AuthorizationException('El rol no puede realizar esta transición.');
        }

        return DB::transaction(function () use ($actor, $pedidoId, $station, $newState) {
            $pedido = Pedido::lockForUpdate()->findOrFail($pedidoId);
            $this->active($pedido);
            $line = $pedido->estaciones()->where('estacion', $station)->lockForUpdate()->firstOrFail();
            $index = array_search($line->estado, self::FLOW, true);
            if ($index === false || (self::FLOW[$index + 1] ?? null) !== $newState) {
                $this->invalid('estado', 'Solo se permite avanzar al siguiente estado.');
            }
            $this->transition($actor, $line, $newState);
            $states = $pedido->estaciones()->pluck('estado')->all();
            $pedido->estado = self::FLOW[min(array_map(fn ($state) => array_search($state, self::FLOW, true), $states))];
            $pedido->save();
            event(PedidoActualizado::forOrder($pedido));

            return $pedido->load('estaciones', 'historial');
        }, 3);
    }

    public function cancelar(User $actor, int $pedidoId): Pedido
    {
        DomainAccess::requireRole($actor, ['cliente', 'mesero', 'cajero', 'administrador']);

        return DB::transaction(function () use ($actor, $pedidoId) {
            // Mismo orden que cobros/correcciones: pedido -> corte -> pago.
            $pedido = Pedido::lockForUpdate()->findOrFail($pedidoId);
            $this->active($pedido);
            if ($actor->fresh()->rol === 'cliente' && $pedido->id_usuario !== $actor->id) {
                throw new AuthorizationException;
            }
            if ($pedido->estado === 'entregado') {
                $this->invalid('estado', 'No puede cancelar un pedido entregado.');
            }
            $cortes = Corte::whereIn('id', $pedido->pagos()->select('id_corte'))->orderBy('id')->lockForUpdate()->get();
            if ($cortes->contains('estado', 'cerrado') || $pedido->pagos()->activos()->where('reembolso', false)->exists()) {
                $this->invalid('pagos', 'Primero corrija o reembolse los cobros; no puede cancelar pagos de cortes cerrados.');
            }
            foreach ($pedido->estaciones()->orderBy('id')->lockForUpdate()->get() as $station) {
                $this->transition($actor, $station, 'cancelado');
            }
            $pedido->update(['estado' => 'cancelado']);
            event(PedidoActualizado::forOrder($pedido));

            return $pedido->load('estaciones', 'historial');
        }, 3);
    }

    private function transition(User $actor, PedidoEstacion $station, string $state): void
    {
        HistorialEstado::create(['id_pedido' => $station->id_pedido, 'cambiado_por' => $actor->id,
            'estacion' => $station->estacion, 'estado_anterior' => $station->estado, 'estado_nuevo' => $state]);
        $station->update(['estado' => $state]);
    }

    private function active(Pedido $pedido): void
    {
        if ($pedido->eliminado || $pedido->estado === 'cancelado') {
            $this->invalid('pedido', 'El pedido no está activo.');
        }
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
