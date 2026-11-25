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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

    private function crearUsuario(string $clave, string $rol, ?string $estacion = null): User
    {
        $usuario = (new User)->forceFill(['name' => "$clave Prueba", 'primer_nombre' => $clave, 'primer_apellido' => 'Prueba',
            'email' => "$clave@prueba.test", 'password' => 'TrackEatDemo!2026', 'rol' => $rol, 'estacion' => $estacion]);
        $usuario->save();

        return $usuario->fresh();
    }

    public function test_un_cocinero_nuevo_sin_estacion_queda_en_general(): void
    {
        $this->assertSame('general', $this->crearUsuario('cocinero_nuevo', 'cocinero')->estacion);
        $this->assertSame('barra', $this->crearUsuario('cocinero_barra', 'cocinero', 'barra')->estacion);
        $this->assertSame('cocina', $this->crearUsuario('cocinero_cocina', 'cocinero', 'cocina')->estacion);
        $this->assertNull($this->crearUsuario('mesero_nuevo', 'mesero')->estacion);
    }

    public function test_cambiar_a_rol_cocinero_asigna_general_y_nunca_queda_sin_estacion(): void
    {
        $usuario = $this->crearUsuario('ascendido', 'mesero');
        DB::table('users')->where('id', $usuario->id)->update(['rol' => 'cocinero']);
        $this->assertSame('general', $usuario->fresh()->estacion);

        // Quitarle la estación a un cocinero lo regresa a `general`, nunca a nulo.
        DB::table('users')->where('id', $usuario->id)->update(['estacion' => 'barra']);
        DB::table('users')->where('id', $usuario->id)->update(['estacion' => null]);
        $this->assertSame('general', $usuario->fresh()->estacion);
    }

    public function test_la_migracion_hace_generales_a_los_cocineros_existentes_sin_estacion(): void
    {
        $migracion = require database_path('migrations/2026_11_25_000001_cocinero_estacion_general.php');
        $migracion->down();
        $sinEstacion = $this->crearUsuario('cocinero_viejo', 'cocinero');
        $deBarra = $this->crearUsuario('cocinero_viejo_barra', 'cocinero', 'barra');
        $this->assertNull($sinEstacion->estacion);
        $migracion->up();

        $this->assertSame('general', $sinEstacion->fresh()->estacion);
        $this->assertSame('barra', $deBarra->fresh()->estacion);
        $this->assertSame(0, User::where('rol', 'cocinero')->whereNull('estacion')->count());
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
        // El mesero no tiene límite de pedidos en curso (T-07); estas pruebas tratan del folio, no del dueño.
        return $this->levantarPedido($this->usuario('mesero'))->numero_pedido;
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

    // ---------- T-06 · Autorización por pedido al avanzar estación (A01) ----------

    private function pedidoCocinaYBarra(?string $sala = null): Pedido
    {
        $producto = fn (string $estacion) => Producto::whereHas('categoria', fn ($q) => $q->where('estacion', $estacion))->firstOrFail()->id;
        // Lo levanta el mesero: sin límite de pedidos en curso, así cada prueba puede crear los que necesite.
        $pedido = app(PedidoService::class)->crear($this->usuario('mesero'), ['tipo_entrega' => 'para_llevar', 'items' => [
            ['id_producto' => $producto('cocina'), 'cantidad' => 1], ['id_producto' => $producto('barra'), 'cantidad' => 1]]]);
        if ($sala) {
            $pedido->forceFill(['demo_room' => $sala])->save();
        }

        return $pedido;
    }

    private function avanzarApi(User $actor, Pedido $pedido, string $estacion, string $estado = 'preparacion')
    {
        return $this->actingAs($actor)->patchJson("/api/pedidos/{$pedido->id}/estacion", ['estacion' => $estacion, 'estado' => $estado]);
    }

    public function test_el_cocinero_de_cocina_o_barra_solo_avanza_su_estacion(): void
    {
        $pedido = $this->pedidoCocinaYBarra();
        $barra = $this->crearUsuario('cocinero_barra', 'cocinero', 'barra');
        $cocina = $this->crearUsuario('cocinero_cocina', 'cocinero', 'cocina');

        $this->avanzarApi($barra, $pedido, 'cocina')->assertForbidden()->assertJsonPath('message', 'Esta comanda es de la estación cocina.');
        $this->avanzarApi($cocina, $pedido, 'barra')->assertForbidden()->assertJsonPath('message', 'Esta comanda es de la estación barra.');
        $this->avanzarApi($barra, $pedido, 'barra')->assertOk();
        $this->avanzarApi($cocina, $pedido, 'cocina')->assertOk();
        $this->assertSame(['barra' => 'preparacion', 'cocina' => 'preparacion'],
            $pedido->estaciones()->orderBy('estacion')->pluck('estado', 'estacion')->all());
    }

    public function test_el_cocinero_general_y_el_administrador_avanzan_cualquier_estacion(): void
    {
        $pedido = $this->pedidoCocinaYBarra();
        $this->avanzarApi($this->usuario('cocinero'), $pedido, 'cocina')->assertOk();
        $this->avanzarApi($this->usuario('cocinero'), $pedido, 'barra')->assertOk();
        $this->avanzarApi($this->usuario('administrador'), $pedido, 'cocina', 'listo')->assertOk();
        $this->avanzarApi($this->usuario('administrador'), $pedido, 'barra', 'listo')->assertOk();
    }

    public function test_mesero_y_cajero_solo_entregan_y_el_cocinero_no_entrega(): void
    {
        $pedido = $this->pedidoCocinaYBarra();
        $this->avanzarApi($this->usuario('mesero'), $pedido, 'cocina')->assertForbidden();
        $this->avanzarApi($this->usuario('cajero'), $pedido, 'cocina')->assertForbidden();
        $this->avanzarApi($this->usuario('cocinero'), $pedido, 'cocina')->assertOk();
        $this->avanzarApi($this->usuario('cocinero'), $pedido, 'cocina', 'listo')->assertOk();
        $this->avanzarApi($this->usuario('cocinero'), $pedido, 'cocina', 'entregado')
            ->assertForbidden()->assertJsonPath('message', 'El rol no puede realizar esta transición.');
        $this->avanzarApi($this->usuario('cajero'), $pedido, 'cocina', 'entregado')->assertOk();
    }

    public function test_las_comandas_general_de_pedidos_anteriores_solo_las_avanza_el_cocinero_general(): void
    {
        $pedido = $this->levantarPedido($this->usuario('cliente1'));
        $pedido->estaciones()->create(['estacion' => 'general']);
        $this->avanzarApi($this->crearUsuario('cocinero_barra', 'cocinero', 'barra'), $pedido, 'general')->assertForbidden();
        $this->avanzarApi($this->usuario('cocinero'), $pedido, 'general')->assertOk();
    }

    public function test_la_regla_de_estacion_no_se_salta_llamando_al_servicio(): void
    {
        $pedido = $this->pedidoCocinaYBarra();
        try {
            app(PedidoService::class)->avanzar($this->crearUsuario('cocinero_barra', 'cocinero', 'barra'), $pedido->id, 'cocina', 'preparacion');
            $this->fail('El servicio debe aplicar la política aunque no pase por el controlador.');
        } catch (AuthorizationException $error) {
            $this->assertSame('Esta comanda es de la estación cocina.', $error->getMessage());
        }
        $this->assertSame('recibido', $pedido->estaciones()->where('estacion', 'cocina')->value('estado'));
    }

    public function test_un_cambio_de_estacion_aplica_sin_volver_a_iniciar_sesion(): void
    {
        $pedido = $this->pedidoCocinaYBarra();
        $cocinero = $this->crearUsuario('cocinero_barra', 'cocinero', 'barra');
        $this->avanzarApi($cocinero, $pedido, 'cocina')->assertForbidden();
        DB::table('users')->where('id', $cocinero->id)->update(['estacion' => 'cocina']);
        // La instancia en sesión sigue diciendo `barra`; el servidor relee la base.
        $this->avanzarApi($cocinero, $pedido, 'cocina')->assertOk();
    }

    public function test_un_usuario_de_una_sala_demo_no_toca_pedidos_de_otra_sala(): void
    {
        $sala = (string) Str::uuid();
        $cocinero = $this->crearUsuario('cocinero_demo', 'cocinero');
        $cocinero->forceFill(['demo_room' => $sala])->save();
        $ajeno = $this->pedidoCocinaYBarra((string) Str::uuid());
        $restaurante = $this->pedidoCocinaYBarra();

        foreach ([$ajeno, $restaurante] as $pedido) {
            try {
                app(PedidoService::class)->avanzar($cocinero, $pedido->id, 'cocina', 'preparacion');
                $this->fail('Un usuario de sala no debe avanzar pedidos fuera de su sala.');
            } catch (AuthorizationException $error) {
                $this->assertSame(404, $error->status());
            }
        }
        $propio = $this->pedidoCocinaYBarra($sala);
        app(PedidoService::class)->avanzar($cocinero, $propio->id, 'cocina', 'preparacion');
        $this->assertSame('preparacion', $propio->estaciones()->where('estacion', 'cocina')->value('estado'));
    }

    public function test_el_personal_del_restaurante_avanza_los_pedidos_de_la_demostracion(): void
    {
        $pedido = $this->pedidoCocinaYBarra((string) Str::uuid());
        $this->avanzarApi($this->usuario('administrador'), $pedido, 'cocina')->assertOk();
    }

    public function test_un_pedido_inexistente_responde_404(): void
    {
        $this->actingAs($this->usuario('cocinero'))
            ->patchJson('/api/pedidos/999999/estacion', ['estacion' => 'cocina', 'estado' => 'preparacion'])->assertNotFound();
    }

    public function test_la_sesion_expone_la_estacion_del_usuario(): void
    {
        $this->actingAs($this->usuario('cocinero'))->getJson('/api/sesion')->assertJsonPath('user.estacion', 'general');
        $this->actingAs($this->usuario('mesero'))->getJson('/api/sesion')->assertJsonPath('user.estacion', null);
    }

    // ---------- T-07 · Límites de negocio al crear pedido (A08) ----------

    private function intentar(User $actor, array $extra = []): array
    {
        try {
            return ['ok' => $this->levantarPedido($actor, $extra)];
        } catch (ValidationException $error) {
            return ['error' => $error->errors()];
        }
    }

    private function renglon(int $cantidad, ?Producto $producto = null): array
    {
        return ['id_producto' => ($producto ?? Producto::orderByDesc('precio')->firstOrFail())->id, 'cantidad' => $cantidad];
    }

    public function test_la_cantidad_por_producto_y_los_renglones_tienen_tope(): void
    {
        $cliente = $this->crearUsuario('cliente_limites', 'cliente');
        $barato = Producto::orderBy('precio')->firstOrFail();
        $this->assertArrayHasKey('items.0.cantidad', $this->intentar($cliente, ['items' => [$this->renglon(101, $barato)]])['error']);
        $this->assertArrayHasKey('items', $this->intentar($cliente, ['items' => array_fill(0, 51, $this->renglon(1, $barato))])['error']);
        // El controlador aplica los mismos topes que el servicio.
        $this->actingAs($cliente)->postJson('/api/pedidos', $this->cuerpoApi(['items' => [$this->renglon(101, $barato)]]))
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.cantidad');
        $this->assertSame(0, Pedido::where('id_usuario', $cliente->id)->count());
    }

    public function test_la_mesa_debe_estar_entre_1_y_el_maximo(): void
    {
        $mesero = $this->usuario('mesero');
        $this->assertArrayHasKey('num_mesa', $this->intentar($mesero, ['tipo_entrega' => 'comer_aqui', 'num_mesa' => 31])['error']);
        $this->assertArrayHasKey('num_mesa', $this->intentar($mesero, ['tipo_entrega' => 'comer_aqui', 'num_mesa' => 0])['error']);
        $this->assertSame(30, $this->intentar($mesero, ['tipo_entrega' => 'comer_aqui', 'num_mesa' => 30])['ok']->num_mesa);
    }

    public function test_el_total_maximo_es_de_5000_para_clientes_y_20000_para_el_personal(): void
    {
        $cliente = $this->crearUsuario('cliente_limites', 'cliente');
        $caro = Producto::orderByDesc('precio')->firstOrFail();
        $piezas = (int) floor(5000 / (float) $caro->precio) + 1;   // apenas por encima de $5,000
        $error = $this->intentar($cliente, ['items' => [$this->renglon($piezas)]])['error'];
        $this->assertSame(['El pedido supera el máximo de $5,000.00 MXN. Divídelo en varios pedidos o pide ayuda al personal.'], $error['total']);
        $this->assertArrayHasKey('ok', $this->intentar($this->usuario('mesero'), ['items' => [$this->renglon($piezas)]]));

        $porRenglon = min(100, (int) floor(20000 / (float) $caro->precio));
        $renglones = array_fill(0, (int) ceil(20000 / ($porRenglon * (float) $caro->precio)) + 1, $this->renglon($porRenglon));
        $this->assertArrayHasKey('total', $this->intentar($this->usuario('cajero'), ['items' => $renglones])['error']);
    }

    public function test_un_cliente_tiene_como_maximo_3_pedidos_en_curso(): void
    {
        $cliente = $this->crearUsuario('cliente_limites', 'cliente');
        $primero = $this->actingAs($cliente)->postJson('/api/pedidos', $cuerpo = $this->cuerpoApi())->assertOk()->json();
        $this->levantarPedido($cliente);
        $this->levantarPedido($cliente);

        $this->actingAs($cliente)->postJson('/api/pedidos', $this->cuerpoApi())->assertUnprocessable()
            ->assertJsonPath('errors.pedidos.0', 'Ya tienes 3 pedidos en curso. Espera a que se entregue alguno para hacer otro.');
        // Un reintento del primero devuelve el mismo pedido aunque el cliente esté en el límite.
        $this->actingAs($cliente)->postJson('/api/pedidos', $cuerpo)->assertOk()->assertJsonPath('id', $primero['id']);

        // Al entregarse uno, el cliente vuelve a poder pedir.
        $pedido = Pedido::findOrFail($primero['id']);
        foreach ($pedido->estaciones as $estacion) {
            foreach (['preparacion', 'listo'] as $estado) {
                app(PedidoService::class)->avanzar($this->usuario('cocinero'), $pedido->id, $estacion->estacion, $estado);
            }
            app(PedidoService::class)->avanzar($this->usuario('mesero'), $pedido->id, $estacion->estacion, 'entregado');
        }
        $this->actingAs($cliente)->postJson('/api/pedidos', $this->cuerpoApi())->assertOk();
    }

    public function test_un_pedido_cancelado_no_cuenta_como_en_curso(): void
    {
        $cliente = $this->crearUsuario('cliente_limites', 'cliente');
        $pedidos = [$this->levantarPedido($cliente), $this->levantarPedido($cliente), $this->levantarPedido($cliente)];
        app(PedidoService::class)->cancelar($cliente, $pedidos[0]->id);
        $this->assertArrayHasKey('ok', $this->intentar($cliente));
    }

    public function test_el_personal_no_tiene_limite_de_pedidos_en_curso(): void
    {
        $mesero = $this->usuario('mesero');
        foreach (range(1, 5) as $_) {
            $this->levantarPedido($mesero);
        }
        $this->assertSame(5, Pedido::where('id_usuario', $mesero->id)->whereIn('estado', ['recibido', 'preparacion', 'listo'])->count());
    }

    public function test_la_sesion_expone_los_limites_segun_el_rol(): void
    {
        $this->actingAs($this->usuario('cliente1'))->getJson('/api/sesion')
            ->assertJsonPath('limites', ['mesas_max' => 30, 'cantidad_max' => 100, 'total_max' => '5000.00']);
        $this->actingAs($this->usuario('mesero'))->getJson('/api/sesion')->assertJsonPath('limites.total_max', '20000.00');
    }

    public function test_la_estacion_no_se_puede_asignar_en_masa(): void
    {
        $usuario = (new User)->fill(['name' => 'Prueba', 'estacion' => 'cocina']);
        $this->assertSame('Prueba', $usuario->name);
        $this->assertNull($usuario->estacion);
    }
}
