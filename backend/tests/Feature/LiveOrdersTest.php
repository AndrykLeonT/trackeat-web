<?php

namespace Tests\Feature;

use App\Events\PedidoActualizado;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use App\Services\PedidoService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class LiveOrdersTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([PedidoActualizado::class]);
        $this->seed(DemoSeeder::class);
        Event::fake([PedidoActualizado::class]);
        config(['trackeat.demo_enabled' => true]);
        // The log driver deliberately does not authorize channels; exercise the real driver.
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'test',
            'broadcasting.connections.reverb.secret' => 'test-secret', 'broadcasting.connections.reverb.app_id' => 'test']);
    }

    private function user(string $role = 'cliente'): User
    {
        return User::where('rol', $role)->firstOrFail();
    }

    private function payload(): array
    {
        return ['client_request_id' => (string) Str::uuid(), 'tipo_entrega' => 'para_llevar',
            'items' => [['id_producto' => Producto::firstOrFail()->id, 'cantidad' => 2]]];
    }

    public function test_catalog_and_session_expose_only_public_configuration(): void
    {
        $this->getJson('/api/catalogo')->assertOk()->assertJsonCount(4, 'productos')->assertHeader('Cache-Control', 'no-store, private');
        $data = $this->getJson('/api/sesion')->assertOk()->json();
        $this->assertNull($data['user']);
        $this->assertArrayHasKey('csrf', $data);
        $this->assertSame(['key', 'url'], array_keys($data['socket']));
        Producto::first()->update(['disponible' => false]);
        $this->getJson('/api/catalogo')->assertJsonCount(3, 'productos');
    }

    public function test_request_retries_create_exactly_one_order_with_server_prices(): void
    {
        $client = $this->user();
        $data = [...$this->payload(), 'id_usuario' => $this->user('administrador')->id, 'total' => '0.01'];
        $first = $this->actingAs($client)->postJson('/api/pedidos', $data)->assertOk()->json();
        $second = $this->postJson('/api/pedidos', $data)->assertOk()->json();
        $this->assertSame($first['id'], $second['id']);
        $this->assertSame($client->id, $first['id_usuario']);
        $this->assertSame(1, Pedido::where('client_request_id', $data['client_request_id'])->count());
        $this->assertSame(bcmul(Producto::first()->precio, '2', 2), $first['total']);
        Event::assertDispatchedTimes(PedidoActualizado::class, 1);
    }

    public function test_client_cannot_see_others_orders_change_states_or_open_staff_channel(): void
    {
        $client = $this->user();
        $this->actingAs($client)->getJson('/api/pedidos')->assertOk()->assertJsonMissing(['numero_pedido' => 'DEMO-001']);
        $this->patchJson('/api/pedidos/'.Pedido::first()->id.'/estacion', ['estacion' => 'general', 'estado' => 'preparacion'])->assertForbidden();
        $this->postJson('/broadcasting/auth', ['socket_id' => '1.2', 'channel_name' => 'private-trackeat.personal'])->assertForbidden();
        $this->getJson('/api/tablero')->assertForbidden();
        $client->forceFill(['eliminado' => true])->save();
        $this->getJson('/api/pedidos')->assertForbidden();
    }

    public function test_invalid_requests_do_not_save_orders(): void
    {
        $this->getJson('/api/pedidos')->assertUnauthorized();
        $this->actingAs($this->user());
        $data = $this->payload();
        $this->postJson('/api/pedidos', [...$data, 'items' => []])->assertUnprocessable();
        $this->postJson('/api/pedidos', [...$data, 'tipo_entrega' => 'comer_aqui'])->assertUnprocessable();
        $this->postJson('/api/pedidos', [...$data, 'num_mesa' => 3])->assertUnprocessable();
        Producto::first()->update(['disponible' => false]);
        $this->postJson('/api/pedidos', $data)->assertUnprocessable();
        $this->assertSame(4, Pedido::count());
    }

    public function test_staff_transitions_obey_station_flow_and_update_real_chart(): void
    {
        $order = Pedido::where('numero_pedido', 'DEMO-001')->firstOrFail();
        $this->actingAs($this->user('cocinero'));
        $url = '/api/pedidos/'.$order->id.'/estacion';
        $this->patchJson($url, ['estacion' => 'general', 'estado' => 'listo'])->assertUnprocessable();
        $this->patchJson($url, ['estacion' => 'general', 'estado' => 'preparacion'])->assertOk()->assertJsonPath('estado', 'preparacion');
        $this->patchJson($url, ['estacion' => 'general', 'estado' => 'listo'])->assertOk();
        $this->patchJson($url, ['estacion' => 'general', 'estado' => 'entregado'])->assertForbidden();
        $this->actingAs($this->user('mesero'))->patchJson($url, ['estacion' => 'general', 'estado' => 'entregado'])->assertOk();
        $this->getJson('/api/tablero')->assertOk()->assertJsonPath('estados.entregado', 2);
    }

    public function test_demo_links_are_signed_expiring_disabled_by_default_and_preserve_staff(): void
    {
        $room = (string) Str::uuid();
        $link = URL::temporarySignedRoute('demo.enter', now()->addHours(2), ['room' => $room]);
        config(['trackeat.demo_enabled' => false]);
        $this->get($link)->assertForbidden();
        config(['trackeat.demo_enabled' => true]);
        $this->get('/demo/'.$room)->assertForbidden();
        $this->get($link)->assertStatus(302)->assertHeader('Location', '/menu/');
        $guest = auth()->user();
        $this->assertSame('cliente', $guest->rol);
        $this->assertSame($room, $guest->demo_room);
        $this->get($link)->assertStatus(302)->assertHeader('Location', '/menu/');
        $this->assertSame($guest->id, auth()->id());
        $this->travel(121)->minutes();
        $this->get($link)->assertForbidden();
        $this->getJson('/api/pedidos')->assertForbidden();
        $this->travelBack();
        $staff = $this->user('administrador');
        $this->actingAs($staff)->get($link)->assertStatus(302)->assertHeader('Location', '/menu/');
        $this->assertSame($staff->id, auth()->id());
    }

    public function test_demo_board_is_anonymous_and_isolated_by_room_and_expiration(): void
    {
        $room = (string) Str::uuid();
        $client = $this->user();
        $client->forceFill(['demo_room' => $room, 'demo_expires_at' => now()->addHours(2)])->save();
        $data = $this->actingAs($client)->postJson('/api/pedidos', $this->payload())->assertOk()->json();
        $board = $this->getJson('/api/tablero')->assertOk()->assertJsonCount(1, 'pedidos')->json();
        $this->assertSame($data['id'], $board['pedidos'][0]['id']);
        $this->assertSame(['id', 'numero_pedido', 'estado', 'updated_at'], array_keys($board['pedidos'][0]));
        $this->postJson('/broadcasting/auth', ['socket_id' => '1.2', 'channel_name' => 'private-trackeat.demo.'.Str::uuid()])->assertForbidden();
        config(['trackeat.demo_enabled' => false]);
        $this->postJson('/api/pedidos', $this->payload())->assertForbidden();
        $this->postJson('/broadcasting/auth', ['socket_id' => '1.2', 'channel_name' => 'private-trackeat.demo.'.$room])->assertForbidden();
    }

    public function test_notifications_wait_for_commit_and_rollback_sends_nothing(): void
    {
        DB::beginTransaction();
        app(PedidoService::class)->crear($this->user(), [...$this->payload(), 'id_usuario' => $this->user()->id]);
        Event::assertNotDispatched(PedidoActualizado::class);
        DB::rollBack();
        Event::assertNotDispatched(PedidoActualizado::class);
        DB::beginTransaction();
        $order = app(PedidoService::class)->crear($this->user(), [...$this->payload(), 'id_usuario' => $this->user()->id]);
        Event::assertNotDispatched(PedidoActualizado::class);
        DB::commit();
        Event::assertDispatchedTimes(PedidoActualizado::class, 1);
        $event = PedidoActualizado::forOrder($order);
        $this->assertSame(['pedido'], array_keys($event->broadcastWith()));
        $this->assertSame(5, $event->tries);
    }
}
