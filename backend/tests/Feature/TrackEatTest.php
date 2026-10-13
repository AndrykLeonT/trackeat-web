<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Corte;
use App\Models\HistorialEstado;
use App\Models\ItemPedido;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\PedidoEstacion;
use App\Models\Producto;
use App\Models\User;
use App\Services\CajaService;
use App\Services\PedidoService;
use App\Services\PurgaService;
use Database\Seeders\DemoSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TrackEatTest extends TestCase
{
    use RefreshDatabase;

    private CajaService $caja;

    private PedidoService $pedidos;

    private User $cajero;

    private User $mesero;

    private User $cocinero;

    private User $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->caja = app(CajaService::class);
        $this->pedidos = app(PedidoService::class);
        $this->cajero = User::where('email', 'cajero@example.test')->firstOrFail();
        $this->mesero = User::where('email', 'mesero@example.test')->firstOrFail();
        $this->cocinero = User::where('email', 'cocinero@example.test')->firstOrFail();
        $this->cliente = User::where('email', 'cliente1@example.test')->firstOrFail();
    }

    private function order(string $number): Pedido
    {
        return Pedido::where('numero_pedido', $number)->firstOrFail();
    }

    private function open(): Corte
    {
        return Corte::where('estado', 'abierto')->firstOrFail();
    }

    private function rejected(callable $action, string $exception = ValidationException::class): void
    {
        try {
            DB::transaction($action);
        } catch (\Throwable $error) {
            $this->assertInstanceOf($exception, $error);

            return;
        }
        $this->fail('Se esperaba que la operación fuera rechazada.');
    }

    public function test_demo_data_is_coherent_and_idempotent(): void
    {
        $before = [User::count(), Categoria::count(), Producto::count(), Pedido::count(), ItemPedido::count(), PedidoEstacion::count(), HistorialEstado::count(), Pago::count(), Corte::count()];
        $this->seed(DemoSeeder::class);
        $this->assertSame($before, [User::count(), Categoria::count(), Producto::count(), Pedido::count(), ItemPedido::count(), PedidoEstacion::count(), HistorialEstado::count(), Pago::count(), Corte::count()]);
        $this->assertSame([6, 3, 4, 4, 6, 6, 9, 4, 2], $before);
        $this->assertSame('pagado', $this->order('DEMO-003')->estadoPago());
        $this->assertSame('parcial', $this->order('DEMO-002')->estadoPago());
        $this->assertSame('90.00', $this->order('DEMO-002')->saldo());
        $this->assertSame('pendiente', $this->order('DEMO-001')->estadoPago());
        $this->assertSame('cancelado', $this->order('DEMO-004')->estado);
    }

    public function test_seeder_does_not_reopen_or_overwrite_used_examples(): void
    {
        $this->caja->cerrar($this->cajero, $this->open()->id, '360', '0', '0');
        Producto::where('nombre', 'Hamburguesa')->update(['precio' => '140.00']);
        $this->seed(DemoSeeder::class);
        $this->assertSame(0, Corte::where('estado', 'abierto')->count());
        $this->assertSame('140.00', Producto::where('nombre', 'Hamburguesa')->first()->precio);
    }

    public function test_seed_is_blocked_outside_development(): void
    {
        $this->app->instance('env', 'production');
        $this->rejected(fn () => app(DemoSeeder::class)->run(), \RuntimeException::class);
    }

    public function test_price_and_station_are_frozen_and_client_cannot_set_total(): void
    {
        $old = $this->order('DEMO-002');
        $product = Producto::where('nombre', 'Hamburguesa')->firstOrFail();
        $product->update(['precio' => '140.00']);
        $product->categoria->update(['estacion' => 'general']);
        $new = $this->pedidos->crear($this->cliente, ['id_usuario' => $this->cliente->id, 'tipo_entrega' => 'para_llevar', 'total' => '1',
            'items' => [['id_producto' => $product->id, 'cantidad' => 2, 'precio_unitario' => '1']]]);
        $this->assertSame('150.00', $old->fresh()->total);
        $this->assertSame('120.00', $old->items()->where('id_producto', $product->id)->first()->precio_unitario);
        $this->assertSame('cocina', $old->items()->where('id_producto', $product->id)->first()->estacion);
        $this->assertSame('280.00', $new->total);
        $this->assertSame('general', $new->items->first()->estacion);
    }

    public function test_creation_is_atomic_and_rejects_missing_or_unavailable_products(): void
    {
        $data = ['id_usuario' => $this->cliente->id, 'tipo_entrega' => 'para_llevar',
            'items' => [['id_producto' => Producto::first()->id, 'cantidad' => 1], ['id_producto' => 99999, 'cantidad' => 1]]];
        $this->rejected(fn () => $this->pedidos->crear($this->mesero, $data));
        $this->assertSame(4, Pedido::count());
        $data['id_usuario'] = null;
        $this->rejected(fn () => $this->pedidos->crear($this->mesero, $data));
        $data['id_usuario'] = $this->cliente->id;
        $data['items'] = [['id_producto' => Producto::first()->id, 'cantidad' => 1]];
        Producto::first()->update(['disponible' => false]);
        $this->rejected(fn () => $this->pedidos->crear($this->mesero, $data));
    }

    public function test_orders_require_valid_delivery_information(): void
    {
        $data = ['id_usuario' => $this->cliente->id, 'tipo_entrega' => 'comer_aqui',
            'items' => [['id_producto' => Producto::first()->id, 'cantidad' => 1]]];
        $this->rejected(fn () => $this->pedidos->crear($this->mesero, $data));
        $data['num_mesa'] = 4;
        $this->assertSame(4, $this->pedidos->crear($this->mesero, $data)->num_mesa);
        $data['tipo_entrega'] = 'para_llevar';
        $this->rejected(fn () => $this->pedidos->crear($this->mesero, $data));
    }

    public function test_global_state_follows_slowest_station_and_records_actor(): void
    {
        $id = $this->order('DEMO-002')->id;
        $this->assertSame('preparacion', $this->pedidos->avanzar($this->cocinero, $id, 'cocina', 'listo')->estado);
        $ready = $this->pedidos->avanzar($this->cocinero, $id, 'barra', 'listo');
        $this->assertSame('listo', $ready->estado);
        $event = $ready->historial->last();
        $this->assertSame($this->cocinero->id, $event->cambiado_por);
        $this->assertSame('preparacion', $event->estado_anterior);
        $this->assertSame('listo', $event->estado_nuevo);
        $this->assertSame('barra', $event->estacion);
        $this->rejected(fn () => $this->pedidos->avanzar($this->cocinero, $id, 'cocina', 'preparacion'));
        $this->assertSame('listo', $this->order('DEMO-002')->estado);
    }

    public function test_general_station_can_complete_without_other_stations(): void
    {
        $id = $this->order('DEMO-001')->id;
        $this->pedidos->avanzar($this->cocinero, $id, 'general', 'preparacion');
        $this->pedidos->avanzar($this->cocinero, $id, 'general', 'listo');
        $this->assertSame('entregado', $this->pedidos->avanzar($this->mesero, $id, 'general', 'entregado')->estado);
        $this->assertSame(1, $this->order('DEMO-001')->estaciones()->count());
    }

    public function test_roles_and_deleted_users_cannot_bypass_services(): void
    {
        $this->rejected(fn () => $this->caja->abrir($this->cliente, '10'), AuthorizationException::class);
        $this->rejected(fn () => $this->pedidos->avanzar($this->cliente, $this->order('DEMO-002')->id, 'cocina', 'listo'), AuthorizationException::class);
        $this->rejected(fn () => $this->pedidos->crear($this->cliente, ['id_usuario' => User::where('email', 'cliente2@example.test')->first()->id,
            'tipo_entrega' => 'para_llevar', 'items' => [['id_producto' => Producto::first()->id, 'cantidad' => 1]]]), AuthorizationException::class);
        $this->cajero->forceFill(['eliminado' => true])->save();
        $this->rejected(fn () => $this->caja->cobrar($this->cajero, $this->order('DEMO-001')->id, $this->open()->id, 'efectivo', '10'), AuthorizationException::class);
    }

    public function test_overpayment_corrections_and_refunds(): void
    {
        $order = $this->order('DEMO-001');
        $cut = $this->open();
        $this->rejected(fn () => $this->caja->cobrar($this->cajero, $order->id, $cut->id, 'efectivo', 0.1));
        $this->rejected(fn () => $this->caja->cobrar($this->cajero, $order->id, $cut->id, 'efectivo', '0'));
        $this->rejected(fn () => $this->caja->cobrar($this->cajero, $order->id, $cut->id, 'efectivo', '26'));
        $pago = $this->caja->cobrar($this->cajero, $order->id, $cut->id, 'efectivo', '25', '5');
        $this->assertSame('pagado', $order->estadoPago());
        $this->caja->corregir($this->cajero, $pago->id);
        $this->assertSame('25.00', $order->saldo());
        $replacement = $this->caja->cobrar($this->cajero, $order->id, $cut->id, 'tarjeta', '25');
        $this->caja->reembolsar($this->cajero, $replacement->id);
        $this->assertSame('25.00', $order->saldo());
        $this->assertTrue($replacement->fresh()->reembolso);
        $this->assertFalse($replacement->fresh()->eliminado);
        $this->assertSame('60.00', $this->caja->resumen($this->cajero, $cut->id)['total_sistema']);
        $this->rejected(fn () => $this->caja->corregir($this->cajero, $replacement->id));
    }

    public function test_cancellation_requires_refund_and_cancels_all_stations(): void
    {
        $order = $this->order('DEMO-002');
        $this->rejected(fn () => $this->pedidos->cancelar($this->mesero, $order->id));
        $this->caja->reembolsar($this->cajero, $order->pagos()->first()->id);
        $cancelled = $this->pedidos->cancelar($this->mesero, $order->id);
        $this->assertSame(['cancelado'], $cancelled->estaciones->pluck('estado')->unique()->values()->all());
        $this->rejected(fn () => $this->pedidos->avanzar($this->cocinero, $order->id, 'cocina', 'listo'));
        $this->rejected(fn () => $this->caja->cobrar($this->cajero, $order->id, $this->open()->id, 'efectivo', '1'));
    }

    public function test_only_one_open_cut_and_only_owner_can_operate_it(): void
    {
        $other = User::factory()->create(['rol' => 'cajero']);
        $this->rejected(fn () => $this->caja->abrir($other, '10'));
        $this->rejected(fn () => $this->caja->cobrar($other, $this->order('DEMO-001')->id, $this->open()->id, 'efectivo', '10'), AuthorizationException::class);
        $this->rejected(fn () => Corte::create(['id_usuario' => $other->id, 'fecha_apertura' => now(), 'total_caja_inicial' => '10']), QueryException::class);
    }

    public function test_closed_cut_separates_tips_and_freezes_financial_data(): void
    {
        $cut = Corte::where('estado', 'cerrado')->firstOrFail();
        $summary = $this->caja->resumen($this->cajero, $cut->id);
        $this->assertSame('100.00', $summary['total_efectivo']);
        $this->assertSame('50.00', $summary['total_tarjeta']);
        $this->assertSame('10.00', $summary['total_propinas']);
        $this->assertSame('150.00', $cut->total_real);
        $this->assertSame('0.00', $summary['diferencia_efectivo']);
        $payment = $cut->pagos()->first();
        $this->rejected(fn () => $this->caja->reembolsar($this->cajero, $payment->id));
        $this->rejected(fn () => $payment->update(['eliminado' => true]), QueryException::class);
        $this->rejected(fn () => $cut->update(['total_sistema' => '1']), QueryException::class);
        $this->rejected(fn () => $payment->delete(), QueryException::class);
    }

    public function test_cut_reports_shortages_separately_and_allows_next_shift(): void
    {
        $cut = $this->open();
        $this->caja->cobrar($this->cajero, $this->order('DEMO-002')->id, $cut->id, 'tarjeta', '40');
        $closed = $this->caja->cerrar($this->cajero, $cut->id, '350', '35', '0');
        $summary = $this->caja->resumen($this->cajero, $cut->id);
        $this->assertSame('10.00', $summary['diferencia_efectivo']);
        $this->assertSame('5.00', $summary['diferencia_tarjeta']);
        $this->assertSame('100.00', $closed->total_sistema);
        $this->assertSame('85.00', $closed->total_real);
        $this->assertSame('abierto', $this->caja->abrir($this->cajero, '100')->fresh()->estado);
    }

    public function test_database_constraints_and_history_immutability(): void
    {
        $order = $this->order('DEMO-001');
        $this->rejected(fn () => $order->estaciones()->create(['estacion' => 'general']), QueryException::class);
        $this->rejected(fn () => Producto::first()->update(['precio' => '-1']), QueryException::class);
        $this->rejected(fn () => $order->update(['id_usuario' => 99999]), QueryException::class);
        $this->rejected(fn () => $order->items()->first()->update(['cantidad' => 2]), QueryException::class);
        $this->rejected(fn () => HistorialEstado::first()->delete(), QueryException::class);
        $this->rejected(fn () => $order->items()->create(['id_producto' => Producto::first()->id, 'cantidad' => 0, 'precio_unitario' => '1', 'estacion' => 'general']), QueryException::class);
        $this->rejected(fn () => Categoria::first()->update(['estacion' => 'invalida']), QueryException::class);
        $this->rejected(fn () => Producto::first()->imagenes()->create(['orden' => 0, 'url' => '/logo.svg']), QueryException::class);
    }

    public function test_admin_purge_requires_password_and_only_deleted_open_payments(): void
    {
        $admin = User::where('rol', 'administrador')->firstOrFail();
        $purge = app(PurgaService::class);
        $payment = $this->caja->cobrar($this->cajero, $this->order('DEMO-001')->id, $this->open()->id, 'efectivo', '10');
        $this->rejected(fn () => $purge->pago($admin, $payment->id, 'TrackEatDemo!2026'));
        $this->caja->corregir($this->cajero, $payment->id);
        $this->rejected(fn () => $purge->pago($admin, $payment->id, 'incorrecta'));
        $this->rejected(fn () => $purge->pago($this->cajero, $payment->id, 'TrackEatDemo!2026'), AuthorizationException::class);
        $purge->pago($admin, $payment->id, 'TrackEatDemo!2026');
        $this->assertNull(Pago::find($payment->id));
    }
}
