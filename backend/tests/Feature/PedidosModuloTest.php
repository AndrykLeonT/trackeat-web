<?php

namespace Tests\Feature;

use App\Events\PedidoActualizado;
use App\Models\Categoria;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use App\Services\PedidoService;
use Carbon\Carbon;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

// Módulo 2 · Pedidos y comandas. T-08 completa este archivo con el resto de reglas del módulo.
class PedidosModuloTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    private function usuario(string $clave): User
    {
        return User::where('email', "$clave@example.test")->firstOrFail();
    }

    private function levantarPedido(User $actor, array $extra = []): Pedido
    {
        return app(PedidoService::class)->crear($actor, [...['tipo_entrega' => 'para_llevar',
            'items' => [['id_producto' => Producto::firstOrFail()->id, 'cantidad' => 1]]], ...$extra]);
    }

    private function cuerpoApi(array $extra = []): array
    {
        return [...['client_request_id' => (string) Str::uuid(), 'tipo_entrega' => 'para_llevar',
            'items' => [['id_producto' => Producto::firstOrFail()->id, 'cantidad' => 1]]], ...$extra];
    }

    public function test_un_pedido_siempre_tiene_dueno_y_la_llave_foranea_sigue_vigente(): void
    {
        $base = ['numero_pedido' => 'TE-PRUEBA-1', 'tipo_entrega' => 'comer_aqui', 'num_mesa' => 4, 'total' => '10.00'];
        foreach ([[null, '23502'], [99999, '23503']] as [$dueno, $codigo]) {
            try {
                DB::transaction(fn () => Pedido::create([...$base, 'id_usuario' => $dueno]));
                $this->fail('La base debe rechazar un pedido sin dueño o con un dueño inexistente.');
            } catch (QueryException $error) {
                $this->assertSame($codigo, $error->getCode());
            }
        }
        $cliente = $this->usuario('cliente1');
        $this->assertSame($cliente->id, Pedido::create([...$base, 'id_usuario' => $cliente->id])->id_usuario);
    }

    public function test_cliente_mesero_cajero_y_administrador_levantan_pedidos_a_su_nombre(): void
    {
        foreach (['cliente1', 'mesero', 'cajero', 'administrador'] as $clave) {
            $usuario = $this->usuario($clave);
            $this->assertSame($usuario->id, $this->levantarPedido($usuario)->id_usuario, $clave);
        }
    }

    public function test_nadie_puede_registrar_un_pedido_a_nombre_de_otra_cuenta(): void
    {
        $cliente = $this->usuario('cliente1');
        $otro = $this->usuario('cliente2');
        $this->assertSame($cliente->id, $this->levantarPedido($cliente, ['id_usuario' => $otro->id])->id_usuario);
        $this->assertSame($this->usuario('mesero')->id, $this->levantarPedido($this->usuario('mesero'), ['id_usuario' => $otro->id])->id_usuario);

        $this->actingAs($cliente)->postJson('/api/pedidos', $this->cuerpoApi(['id_usuario' => $otro->id]))
            ->assertOk()->assertJsonPath('id_usuario', $cliente->id);
    }

    public function test_el_personal_levanta_pedidos_desde_la_api_y_quedan_a_su_nombre(): void
    {
        foreach (['mesero', 'cajero'] as $clave) {
            $usuario = $this->usuario($clave);
            $this->actingAs($usuario)->postJson('/api/pedidos', $this->cuerpoApi(['tipo_entrega' => 'comer_aqui', 'num_mesa' => 3]))
                ->assertOk()->assertJsonPath('id_usuario', $usuario->id)->assertJsonPath('num_mesa', 3);
        }
    }

    public function test_un_reintento_del_personal_no_duplica_el_pedido(): void
    {
        $mesero = $this->usuario('mesero');
        $solicitud = (string) Str::uuid();
        $primero = $this->levantarPedido($mesero, ['client_request_id' => $solicitud]);
        $segundo = $this->levantarPedido($mesero, ['client_request_id' => $solicitud]);
        $this->assertSame($primero->id, $segundo->id);
        $this->assertSame(1, Pedido::where('client_request_id', $solicitud)->count());

        // El índice único (id_usuario, client_request_id) cubre al personal igual que a los clientes.
        try {
            DB::transaction(fn () => Pedido::create(['id_usuario' => $mesero->id, 'client_request_id' => $solicitud, 'numero_pedido' => 'TE-PRUEBA-2',
                'tipo_entrega' => 'para_llevar', 'total' => '10.00']));
            $this->fail('La base debe rechazar un reintento duplicado.');
        } catch (QueryException $error) {
            $this->assertSame('23505', $error->getCode());
        }
    }

    public function test_el_motivo_de_cancelacion_se_guarda(): void
    {
        $pedido = Pedido::where('numero_pedido', 'DEMO-001')->firstOrFail();
        $this->assertNull($pedido->motivo_cancelacion);
        $pedido->update(['motivo_cancelacion' => 'error_de_captura']);
        $this->assertSame('error_de_captura', $pedido->fresh()->motivo_cancelacion);
    }

    public function test_el_evento_avisa_al_canal_de_quien_levanto_el_pedido(): void
    {
        $mesero = $this->usuario('mesero');
        $canales = array_map(fn ($canal) => $canal->name, PedidoActualizado::forOrder($this->levantarPedido($mesero))->broadcastOn());
        $this->assertSame(['private-trackeat.personal', 'private-trackeat.usuario.'.$mesero->id], $canales);
    }

    public function test_el_seeder_asigna_estacion_solo_al_cocinero_de_ejemplo(): void
    {
        // `general`: el cocinero de ejemplo ve y atiende las comandas de cocina y de barra.
        $this->assertSame('general', User::where('email', 'cocinero@example.test')->firstOrFail()->estacion);
        $this->assertSame(0, User::where('email', '!=', 'cocinero@example.test')->whereNotNull('estacion')->count());
    }

    public function test_la_base_rechaza_una_estacion_invalida(): void
    {
        try {
            DB::transaction(fn () => DB::table('users')->where('email', 'cocinero@example.test')->update(['estacion' => 'postres']));
            $this->fail('Una estación fuera de general, cocina y barra debe ser rechazada.');
        } catch (QueryException $error) {
            $this->assertSame('23514', $error->getCode());
        }
    }

    public function test_las_categorias_solo_admiten_cocina_o_barra(): void
    {
        $this->assertSame(['barra', 'cocina'], Categoria::orderBy('estacion')->distinct()->pluck('estacion')->all());

        try {
            DB::transaction(fn () => Categoria::where('nombre', 'Hamburguesas')->update(['estacion' => 'general']));
            $this->fail('La base debe rechazar una categoría de estación general.');
        } catch (QueryException $error) {
            $this->assertSame('23514', $error->getCode());
        }

        $admin = User::where('rol', 'administrador')->firstOrFail();
        $this->actingAs($admin)->postJson('/admin/categorias', ['nombre' => 'Nueva', 'estacion' => 'general', 'disponible' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('estacion');
        $this->postJson('/admin/categorias', ['nombre' => 'Nueva', 'estacion' => 'barra', 'disponible' => true])->assertRedirect('/admin/categorias');
    }

    public function test_la_migracion_reclasifica_las_categorias_general_por_su_nombre(): void
    {
        $migracion = require database_path('migrations/2026_11_24_000003_categorias_solo_cocina_y_barra.php');
        $migracion->down();
        $esperado = ['Jugos naturales' => 'barra', 'Café' => 'barra', 'Aguas frescas' => 'barra', 'Bebidas frías' => 'barra',
            'Tacos' => 'cocina', 'Aguacates' => 'cocina', 'Postres' => 'cocina'];
        foreach (array_keys($esperado) as $nombre) {
            Categoria::create(['nombre' => $nombre, 'estacion' => 'general']);
        }
        $migracion->up();

        foreach ($esperado as $nombre => $estacion) {
            $this->assertSame($estacion, Categoria::where('nombre', $nombre)->firstOrFail()->estacion, $nombre);
        }
        $this->assertSame('cocina', Categoria::where('nombre', 'Hamburguesas')->firstOrFail()->estacion);
        $this->assertSame('barra', Categoria::where('nombre', 'Bebidas')->firstOrFail()->estacion);
    }

    private function crearPedido(): string
    {
        return $this->levantarPedido($this->usuario('cliente1'))->numero_pedido;
    }

    private function numeroDeFolio(string $folio): int
    {
        return (int) substr($folio, strrpos($folio, '-') + 1);
    }

    public function test_el_folio_es_consecutivo_y_lleva_la_fecha_del_dia(): void
    {
        $hoy = now(config('trackeat.zona_folio'))->format('Ymd');
        $primero = $this->crearPedido();
        $segundo = $this->crearPedido();
        $this->assertMatchesRegularExpression("/^TE-$hoy-\\d{3,}$/", $primero);
        $this->assertSame($this->numeroDeFolio($primero) + 1, $this->numeroDeFolio($segundo));
    }

    public function test_el_folio_reinicia_a_medianoche_de_la_zona_del_restaurante(): void
    {
        try {
            // 05:30 UTC del 25 es todavía el 24 en Mazatlán (22:30): el folio conserva la fecha del 24.
            Carbon::setTestNow(Carbon::parse('2026-11-25 05:30:00', 'UTC'));
            $this->assertMatchesRegularExpression('/^TE-20261124-\d{3,}$/', $this->crearPedido());
            // 07:30 UTC del 25 son las 00:30 del 25 en Mazatlán: cambia el día y el contador vuelve a 001.
            Carbon::setTestNow(Carbon::parse('2026-11-25 07:30:00', 'UTC'));
            $this->assertSame('TE-20261125-001', $this->crearPedido());
            $this->assertSame('TE-20261125-002', $this->crearPedido());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_una_creacion_revertida_no_consume_folio(): void
    {
        $antes = $this->numeroDeFolio($this->crearPedido());
        try {
            DB::transaction(function () {
                $this->crearPedido();
                throw new \RuntimeException('Fallo forzado después de asignar el folio.');
            });
        } catch (\RuntimeException) {
        }
        $this->assertSame($antes + 1, $this->numeroDeFolio($this->crearPedido()));
    }

    public function test_la_estacion_no_se_puede_asignar_en_masa(): void
    {
        $usuario = (new User)->fill(['name' => 'Prueba', 'estacion' => 'cocina']);
        $this->assertSame('Prueba', $usuario->name);
        $this->assertNull($usuario->estacion);
    }
}
