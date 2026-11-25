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
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PedidoService
{
    private const FLOW = ['recibido', 'preparacion', 'listo', 'entregado'];

    public function crear(User $actor, array $data): Pedido
    {
        DomainAccess::requireRole($actor, ['cliente', 'mesero', 'cajero', 'administrador']);
        $limites = config('trackeat.pedidos');
        $data = Validator::make($data, [
            'tipo_entrega' => ['required', 'in:para_llevar,comer_aqui'],
            'num_mesa' => ['nullable', 'integer', 'min:1', 'max:'.$limites['mesas_max'], 'required_if:tipo_entrega,comer_aqui', 'prohibited_if:tipo_entrega,para_llevar'],
            'items' => ['required', 'array', 'min:1', 'max:'.$limites['renglones_max']],
            'items.*.id_producto' => ['required', 'integer'],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:'.$limites['cantidad_max']],
            'items.*.notas' => ['nullable', 'string', 'max:1000', 'not_regex:/\x00/u'],
            'items.*.precio_confirmado' => ['sometimes', 'required', 'numeric', 'min:0', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'client_request_id' => ['nullable', 'uuid'],
        ])->validate();

        return DB::transaction(function () use ($data, $actor, $limites) {
            if (! empty($data['client_request_id'])) {
                // Serialize retries of the same request, including simultaneous HTTP requests.
                DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$actor->id.':'.$data['client_request_id']]);
                $existing = Pedido::where('id_usuario', $actor->id)->where('client_request_id', $data['client_request_id'])->first();
                if ($existing) {
                    return $existing->load('items', 'estaciones');
                }
            }
            // El dueño del pedido es siempre quien lo levanta: el cliente en la web o el personal que lo captura.
            // Nadie puede registrarlo a nombre de otra cuenta ni vincular un cliente aparte.
            // Bloqueo exclusivo (no compartido): serializa los pedidos simultáneos del mismo usuario, aun en nodos
            // distintos, para que el conteo de pedidos en curso sea exacto. Va después del reintento: un reintento
            // devuelve su pedido aunque el usuario ya esté en el límite.
            $owner = User::whereKey($actor->id)->where('eliminado', false)->lockForUpdate()->first();
            if (! $owner) {
                throw new AuthorizationException('No tiene permiso para esta operación.');
            }
            $esCliente = $owner->rol === 'cliente';
            if ($esCliente) {
                $abiertos = Pedido::where('id_usuario', $owner->id)->where('eliminado', false)
                    ->whereIn('estado', ['recibido', 'preparacion', 'listo'])->count();
                if ($abiertos >= $limites['abiertos_max_cliente']) {
                    $this->invalid('pedidos', "Ya tienes {$abiertos} pedidos en curso. Espera a que se entregue alguno para hacer otro.");
                }
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
                abort_if(isset($item['precio_confirmado']) && bccomp((string) $item['precio_confirmado'], $product->precio, 2) !== 0, 409, 'Cambió el precio. Actualice el menú y revise el carrito.');
                $lines[] = ['id_producto' => $product->id, 'cantidad' => $item['cantidad'],
                    'precio_unitario' => $product->precio, 'estacion' => $category->estacion, 'notas' => $item['notas'] ?? null];
                $total = Money::add($total, Money::multiply($product->precio, $item['cantidad']));
            }
            Money::parse($total, 'total');
            $totalMax = Money::parse($limites[$esCliente ? 'total_max_cliente' : 'total_max_personal'], 'total');
            if (bccomp($total, $totalMax, 2) > 0) {
                $this->invalid('total', 'El pedido supera el máximo de $'.number_format((float) $totalMax, 2).' MXN. Divídelo en varios pedidos o pide ayuda al personal.');
            }
            // El folio se toma al final, con productos ya validados: el bloqueo de su fila dura hasta el commit.
            $pedido = Pedido::create(['id_usuario' => $owner->id, 'numero_pedido' => $this->siguienteFolio(),
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
        // Rol, estación y sala se leen de la base, no de la sesión: un cambio del administrador aplica de inmediato.
        $actor = $actor->fresh();

        return DB::transaction(function () use ($actor, $pedidoId, $station, $newState) {
            $pedido = Pedido::lockForUpdate()->findOrFail($pedidoId);
            Gate::forUser($actor)->authorize('avanzar', [$pedido, $station, $newState]);
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

    /**
     * Folio consecutivo que reinicia cada día (`TE-AAAAMMDD-NNN`). El día es el de la zona del restaurante.
     * El INSERT ... ON CONFLICT es atómico y deja la fila del día bloqueada hasta el commit, así que dos
     * pedidos simultáneos (incluso en nodos distintos) nunca reciben el mismo número; si la transacción se
     * revierte, el contador también, y no quedan huecos.
     */
    private function siguienteFolio(): string
    {
        $dia = now(config('trackeat.zona_folio'))->toDateString();
        $numero = DB::selectOne(
            'INSERT INTO folios_diarios (fecha, ultimo) VALUES (?, 1) ON CONFLICT (fecha) DO UPDATE SET ultimo = folios_diarios.ultimo + 1 RETURNING ultimo',
            [$dia],
        )->ultimo;

        return sprintf('TE-%s-%03d', str_replace('-', '', $dia), $numero);
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
